"""Finance agent: reading the model's reply, and the call with a fake model (no network)."""
import asyncio
import os
import sys
import unittest
from datetime import date

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.agents.finance import find_promises, parse  # noqa: E402


class ParseTest(unittest.TestCase):
    def test_a_promise(self):
        reply = '{"promises": [{"kind": "payment", "quote": "kal bhej dunga", "amount": 1200, "due_on": "2026-11-05"}]}'

        self.assertEqual(parse(reply), [{"kind": "payment", "quote": "kal bhej dunga", "amount": 1200.0, "due_on": "2026-11-05"}])

    def test_no_promise_is_an_empty_list_not_a_failure(self):
        self.assertEqual(parse('{"promises": []}'), [])

    def test_a_promise_without_a_real_date_is_dropped(self):
        reply = '{"promises": [{"kind": "payment", "quote": "jaldi", "due_on": "soon"}, {"kind": "callback", "quote": "x", "due_on": "2026-11-06"}]}'

        self.assertEqual([p["kind"] for p in parse(reply)], ["callback"])

    def test_unknown_kind_becomes_other_and_bad_amount_null(self):
        promise = parse('{"promises": [{"kind": "gift", "quote": "x", "amount": -5, "due_on": "2026-11-06"}]}')[0]

        self.assertEqual((promise["kind"], promise["amount"]), ("other", None))

    def test_json_in_a_code_fence(self):
        self.assertEqual(parse('```json\n{"promises": []}\n```'), [])

    def test_unusable_replies(self):
        self.assertIsNone(parse("just words"))
        self.assertIsNone(parse('{"promises": "none"}'))
        self.assertIsNone(parse('{"other": 1}'))


class FindPromisesTest(unittest.TestCase):
    def test_the_model_is_told_to_skip_requests_to_the_business(self):
        seen = {}

        async def fake(system, user):
            seen["system"] = system
            return '{"promises": []}'

        asyncio.run(find_promises("Customer: Haircut book karna hai kal 5 baje", date(2026, 11, 4), ask=fake))

        self.assertIn("REQUEST to the business", seen["system"])

    def test_the_model_is_told_todays_date(self):
        seen = {}

        async def fake(system, user):
            seen["system"], seen["user"] = system, user
            return '{"promises": []}'

        result = asyncio.run(find_promises("Customer: kal dunga", date(2026, 11, 4), ask=fake))

        self.assertIn("2026-11-04 (Wednesday)", seen["system"])
        self.assertEqual(seen["user"], "Customer: kal dunga")
        self.assertEqual(result, [])


if __name__ == "__main__":
    unittest.main()
