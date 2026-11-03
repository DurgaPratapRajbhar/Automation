"""Operations agent: reading the model's reply, and the call with a fake model (no network)."""
import asyncio
import os
import sys
import unittest
from datetime import date

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.agents.operations import describe, parse, respond  # noqa: E402


class ParseTest(unittest.TestCase):
    def test_an_order_ready_to_confirm(self):
        reply = '{"reply": "Noted", "items": [{"product_id": 3, "quantity": 2}], "booking_at": null, "ready": true}'
        result = parse(reply)

        self.assertEqual(result["items"], [{"product_id": 3, "quantity": 2}])
        self.assertTrue(result["ready"])
        self.assertIsNone(result["booking_at"])

    def test_ready_without_items_or_booking_is_not_ready(self):
        self.assertFalse(parse('{"reply": "ok", "items": [], "ready": true}')["ready"])

    def test_a_booking_alone_can_be_ready(self):
        result = parse('{"reply": "ok", "items": [], "booking_at": "2026-11-07 17:00", "ready": true}')

        self.assertTrue(result["ready"])
        self.assertEqual(result["booking_at"], "2026-11-07 17:00")

    def test_unreadable_items_are_dropped_not_guessed(self):
        reply = '{"reply": "ok", "items": [{"product_id": "x", "quantity": 1}, {"product_id": 1, "quantity": 0}, {"product_id": 2, "quantity": "3"}], "ready": false}'

        self.assertEqual(parse(reply)["items"], [{"product_id": 2, "quantity": 3}])

    def test_the_address_is_kept_and_the_word_null_is_none(self):
        self.assertEqual(parse('{"reply": "ok", "address": " 12 MG Road, Pune "}')["address"], "12 MG Road, Pune")
        self.assertIsNone(parse('{"reply": "ok", "address": "null"}')["address"])
        self.assertIsNone(parse('{"reply": "ok"}')["address"])

    def test_the_word_null_is_no_booking(self):
        self.assertIsNone(parse('{"reply": "ok", "booking_at": "null"}')["booking_at"])

    def test_unusable_replies(self):
        self.assertIsNone(parse("just words"))
        self.assertIsNone(parse('{"reply": ""}'))

    def test_json_in_a_code_fence(self):
        self.assertEqual(parse('```json\n{"reply": "Hi"}\n```')["reply"], "Hi")


class RespondTest(unittest.TestCase):
    PRODUCTS = [
        {"id": 1, "name": "Solar light", "price": "2499.00", "unit": "piece", "stock": 8},
        {"id": 2, "name": "Installation", "price": "500.00", "unit": None, "stock": None},
    ]

    def test_the_model_reads_the_real_products_and_the_date(self):
        seen = {}

        async def fake(system, user):
            seen["system"], seen["user"] = system, user
            return '{"reply": "It is Rs 2499", "items": [], "ready": false}'

        result = asyncio.run(respond(self.PRODUCTS, "Customer: solar light ka rate?", date(2026, 11, 4), ask=fake))

        self.assertIn("1 | Solar light | Rs 2499.00 per piece | 8", seen["system"])
        self.assertIn("2 | Installation | Rs 500.00 | not counted", seen["system"])
        self.assertIn("2026-11-04 (Wednesday)", seen["system"])
        self.assertEqual(result["reply"], "It is Rs 2499")

    def test_a_delivering_business_makes_the_model_ask_for_the_address(self):
        seen = {}

        async def fake(system, user):
            seen["system"] = system
            return '{"reply": "ok"}'

        asyncio.run(respond(self.PRODUCTS, "Customer: hi", date(2026, 11, 4), needs_address=True, ask=fake))
        self.assertIn("This business delivers", seen["system"])

        asyncio.run(respond(self.PRODUCTS, "Customer: hi", date(2026, 11, 4), ask=fake))
        self.assertNotIn("This business delivers", seen["system"])

    def test_booking_rules_are_given_to_the_model_only_when_there_are_some(self):
        seen = {}

        async def fake(system, user):
            seen["system"] = system
            return '{"reply": "ok"}'

        asyncio.run(respond(self.PRODUCTS, "Customer: hi", date(2026, 11, 4), booking_rules="Bookings: open 10:00 to 19:00", ask=fake))
        self.assertIn("Bookings: open 10:00 to 19:00", seen["system"])

        asyncio.run(respond(self.PRODUCTS, "Customer: hi", date(2026, 11, 4), ask=fake))
        self.assertNotIn("Bookings: open", seen["system"])

    def test_describe_lists_one_product_per_line(self):
        self.assertEqual(len(describe(self.PRODUCTS).splitlines()), 2)


if __name__ == "__main__":
    unittest.main()
