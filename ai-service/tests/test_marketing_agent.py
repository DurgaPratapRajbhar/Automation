"""Marketing agent: reading the model's reply, and the call with a fake model (no network)."""
import asyncio
import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.agents.marketing import describe, draft, parse  # noqa: E402


class ParseTest(unittest.TestCase):
    def test_a_draft(self):
        result = parse('{"name": "sunday_offer", "language": "hi", "body": "Namaste {{1}}! Atta Rs 250"}')

        self.assertEqual(result, {"name": "sunday_offer", "language": "hi", "body": "Namaste {{1}}! Atta Rs 250"})

    def test_the_name_is_made_safe_for_meta(self):
        self.assertEqual(parse('{"name": "Sunday Offer!!", "body": "x"}')["name"], "sunday_offer")
        self.assertEqual(parse('{"name": "", "body": "x"}')["name"], "offer")

    def test_an_unknown_language_becomes_hindi(self):
        self.assertEqual(parse('{"language": "fr", "body": "x"}')["language"], "hi")

    def test_unusable_replies(self):
        self.assertIsNone(parse("just words"))
        self.assertIsNone(parse('{"body": ""}'))

    def test_json_in_a_code_fence(self):
        self.assertEqual(parse('```json\n{"body": "Hi"}\n```')["body"], "Hi")


class DraftTest(unittest.TestCase):
    PRODUCTS = [{"name": "Atta", "price": "250.00", "unit": "5 kg"}, {"name": "Oil", "price": "140.00", "unit": None}]

    def test_the_model_reads_the_business_the_products_and_the_request(self):
        seen = {}

        async def fake(system, user):
            seen["system"], seen["user"] = system, user
            return '{"name": "x", "body": "Hi"}'

        asyncio.run(draft("Ramesh Kirana", self.PRODUCTS, "Sunday grocery offer", ask=fake))

        self.assertIn("Ramesh Kirana", seen["system"])
        self.assertIn("Atta | Rs 250.00 per 5 kg", seen["system"])
        self.assertIn("Oil | Rs 140.00", seen["system"])
        self.assertIn("Namaste {{1}}!", seen["system"])
        self.assertEqual(seen["user"], "Sunday grocery offer")

    def test_no_products(self):
        self.assertEqual(describe([]), "(no products listed)")


if __name__ == "__main__":
    unittest.main()
