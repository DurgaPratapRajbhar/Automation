"""Support (answer) agent: the chat it reads, the reply it parses, and the call with a fake model (no network)."""
import asyncio
import os
import sys
import unittest
from unittest.mock import AsyncMock, patch

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app import agent  # noqa: E402
from app.agent import FALLBACKS, parse, search_query, transcript_of  # noqa: E402
from app.language import ENGLISH, HINGLISH  # noqa: E402

HISTORY = [
    {"from": "customer", "text": "Mujhe website banwana hai"},
    {"from": "business", "text": "Kya main aapko owner se connect kar doon?"},
]


class ParseTest(unittest.TestCase):
    def test_a_reply_with_handoff(self):
        self.assertEqual(parse('{"reply": "Owner ko bata diya hai.", "handoff": true}'), {"reply": "Owner ko bata diya hai.", "handoff": True})

    def test_handoff_is_true_only_for_true(self):
        self.assertFalse(parse('{"reply": "ok", "handoff": "yes"}')["handoff"])
        self.assertFalse(parse('{"reply": "ok"}')["handoff"])

    def test_plain_text_from_a_model_that_ignored_json_is_the_reply(self):
        self.assertEqual(parse("Hum 10 baje khulte hain."), {"reply": "Hum 10 baje khulte hain.", "handoff": False})

    def test_unusable_replies(self):
        self.assertIsNone(parse('{"reply": ""}'))
        self.assertIsNone(parse('{"broken'))
        self.assertIsNone(parse("[1, 2]"))
        self.assertIsNone(parse(""))


class ChatContextTest(unittest.TestCase):
    def test_transcript_names_who_spoke(self):
        self.assertEqual(transcript_of(HISTORY), "Customer: Mujhe website banwana hai\nBusiness: Kya main aapko owner se connect kar doon?")

    def test_empty_lines_are_left_out(self):
        self.assertEqual(transcript_of([{"from": "customer", "text": "  "}]), "")

    def test_a_short_follow_up_searches_with_the_earlier_customer_messages(self):
        self.assertEqual(search_query("Haan", HISTORY), "Mujhe website banwana hai Haan")

    def test_without_history_the_search_is_the_question(self):
        self.assertEqual(search_query("Price kya hai", []), "Price kya hai")


class AnswerTest(unittest.TestCase):
    def run_answer(self, model_reply, docs, history=None):
        found = [{"content": d, "score": 0.9} for d in docs]

        with patch.object(agent, "search", new=AsyncMock(return_value=found)), \
                patch.object(agent, "chat_json", new=AsyncMock(return_value=model_reply)) as model:
            result = asyncio.run(agent.answer_question(3, "Haan", "auto", history or HISTORY))

        return result, model

    def test_the_model_sees_the_chat_and_the_latest_message(self):
        result, model = self.run_answer('{"reply": "Theek hai, owner ko bata diya.", "handoff": true}', ["We build websites."])

        user = model.call_args.args[1]
        self.assertIn("Chat so far:\nCustomer: Mujhe website banwana hai", user)
        self.assertIn("Customer's latest message: Haan", user)
        self.assertEqual(result["answer"], "Theek hai, owner ko bata diya.")
        self.assertTrue(result["handoff"])

    def test_the_first_message_is_marked_as_the_start(self):
        found = [{"content": "Open 10 to 7", "score": 0.9}]

        with patch.object(agent, "search", new=AsyncMock(return_value=found)), \
                patch.object(agent, "chat_json", new=AsyncMock(return_value='{"reply": "10 se 7", "handoff": false}')) as model:
            asyncio.run(agent.answer_question(3, "Timing?"))

        self.assertIn("(this is the first message)", model.call_args.args[1])

    def test_the_reply_language_is_told_to_the_model(self):
        _, model = self.run_answer('{"reply": "ok", "handoff": false}', ["We build websites."])
        self.assertIn("Reply language: Hinglish", model.call_args.args[1])

    def test_a_hinglish_shop_always_gets_hinglish(self):
        found = [{"content": "We build websites.", "score": 0.9}]

        with patch.object(agent, "search", new=AsyncMock(return_value=found)), \
                patch.object(agent, "chat_json", new=AsyncMock(return_value='{"reply": "ok", "handoff": false}')) as model:
            asyncio.run(agent.answer_question(3, "Do you build websites?", "hinglish"))

        self.assertIn("Reply language: Hinglish", model.call_args.args[1])

    def test_english_question_gets_english(self):
        found = [{"content": "We build websites.", "score": 0.9}]

        with patch.object(agent, "search", new=AsyncMock(return_value=found)), \
                patch.object(agent, "chat_json", new=AsyncMock(return_value='{"reply": "ok", "handoff": false}')) as model:
            asyncio.run(agent.answer_question(3, "Do you build mobile apps?"))

        self.assertIn("Reply language: English", model.call_args.args[1])

    def test_an_unusable_model_reply_hands_the_chat_to_the_owner(self):
        result, _ = self.run_answer('{"reply": ""}', ["We build websites."])

        self.assertEqual(result["answer"], FALLBACKS[HINGLISH])  # "Haan" after a Hinglish chat
        self.assertTrue(result["handoff"])

    def test_a_shop_with_no_knowledge_hands_the_chat_to_the_owner(self):
        with patch.object(agent, "search", new=AsyncMock(return_value=[])):
            result = asyncio.run(agent.answer_question(3, "Hi"))

        self.assertEqual(result["answer"], FALLBACKS[ENGLISH])
        self.assertTrue(result["handoff"])


if __name__ == "__main__":
    unittest.main()
