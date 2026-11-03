"""Translation: reading the model's reply, and the call with a fake model (no network)."""
import asyncio
import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.translation import parse, translate  # noqa: E402


class ParseTest(unittest.TestCase):
    def test_json_reply(self):
        self.assertEqual(
            parse('{"translation": "Namaste, aap kaise hain?", "language": "en"}'),
            {"translation": "Namaste, aap kaise hain?", "language": "en"},
        )

    def test_json_in_a_code_fence(self):
        reply = '```json\n{"translation": "नमस्ते", "language": "EN"}\n```'
        self.assertEqual(parse(reply), {"translation": "नमस्ते", "language": "en"})

    def test_plain_text_is_kept_as_the_translation(self):
        self.assertEqual(parse("Just some words"), {"translation": "Just some words", "language": None})

    def test_missing_language(self):
        self.assertEqual(parse('{"translation": "ok"}'), {"translation": "ok", "language": None})


class TranslateTest(unittest.TestCase):
    def test_asks_for_the_target_language_and_returns_the_parsed_reply(self):
        asked = {}

        async def fake_model(system, user):
            asked.update(system=system, user=user)
            return '{"translation": "Hello", "language": "hi"}'

        result = asyncio.run(translate("नमस्ते", "English", ask=fake_model))

        self.assertEqual(result, {"translation": "Hello", "language": "hi"})
        self.assertIn("into English", asked["system"])
        self.assertEqual(asked["user"], "नमस्ते")


if __name__ == "__main__":
    unittest.main()
