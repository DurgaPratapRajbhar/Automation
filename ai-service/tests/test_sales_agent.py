"""Sales agent: reading the model's reply, and the call with a fake model (no network)."""
import asyncio
import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.agents.sales import parse, qualify  # noqa: E402


class ParseTest(unittest.TestCase):
    def test_a_normal_reply(self):
        reply = '{"answers": ["a website", null], "reply": "What is your budget?", "done": false, "score": "hot", "summary": "Wants a website"}'
        result = parse(reply, 2)

        self.assertEqual(result["answers"], ["a website", None])
        self.assertFalse(result["done"])
        self.assertEqual(result["score"], "hot")

    def test_done_is_decided_by_the_answers_not_the_model(self):
        reply = '{"answers": ["a website", "5000"], "reply": "Thanks", "done": false, "score": "warm", "summary": "x"}'

        self.assertTrue(parse(reply, 2)["done"])

    def test_an_answer_written_as_the_word_null_is_not_an_answer(self):
        reply = '{"answers": ["null", "N/A", "Not answered yet", "20000"], "reply": "ok"}'

        result = parse(reply, 4)

        self.assertEqual(result["answers"], [None, None, None, "20000"])
        self.assertFalse(result["done"])

    def test_missing_answers_are_filled_and_extra_ones_cut(self):
        self.assertEqual(parse('{"answers": ["a"], "reply": "ok"}', 3)["answers"], ["a", None, None])
        self.assertEqual(parse('{"answers": ["a", "b", "c"], "reply": "ok"}', 2)["answers"], ["a", "b"])

    def test_an_unknown_score_becomes_warm(self):
        self.assertEqual(parse('{"answers": [], "reply": "ok", "score": "great"}', 0)["score"], "warm")

    def test_json_in_a_code_fence(self):
        self.assertEqual(parse('```json\n{"answers": [null], "reply": "Hi"}\n```', 1)["reply"], "Hi")

    def test_unusable_replies(self):
        self.assertIsNone(parse("just words", 1))
        self.assertIsNone(parse('{"answers": [null], "reply": ""}', 1))
        self.assertIsNone(parse('{"reply": "Hi"}', 1))


class QualifyTest(unittest.TestCase):
    def test_the_questions_are_numbered_for_the_model(self):
        seen = {}

        async def fake(system, user):
            seen["system"], seen["user"] = system, user
            return '{"answers": [null, null], "reply": "Hello"}'

        result = asyncio.run(qualify(["What do you need?", "Budget?"], "Customer: Hi", ask=fake))

        self.assertIn("1. What do you need?", seen["system"])
        self.assertIn("2. Budget?", seen["system"])
        self.assertEqual(seen["user"], "Customer: Hi")
        self.assertEqual(result["reply"], "Hello")


if __name__ == "__main__":
    unittest.main()
