"""The HTTP endpoints of the agents: what the backend sends must reach the agent function (the model call is replaced)."""
import os
import sys
import unittest
from unittest.mock import AsyncMock, patch

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from fastapi.testclient import TestClient  # noqa: E402

from app.main import app  # noqa: E402

client = TestClient(app)  # without "with": the database set-up of the real start is not run


class EndpointTest(unittest.TestCase):
    def test_operations_passes_the_address_and_booking_rules_on(self):
        reply = {"reply": "ok", "items": [], "booking_at": None, "address": None, "ready": False}

        with patch("app.main.operations_respond", new=AsyncMock(return_value=reply)) as respond:
            response = client.post("/operations", json={
                "products": [{"id": 1, "name": "Haircut", "price": "200.00", "unit": None, "stock": None}],
                "transcript": "Customer: hi",
                "today": "2026-11-04",
                "needs_address": True,
                "booking_rules": "Bookings: open 10:00 to 19:00",
            })

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json(), reply)
        self.assertEqual(respond.call_args.args[3:], (True, "Bookings: open 10:00 to 19:00"))

    def test_operations_without_the_optional_fields(self):
        with patch("app.main.operations_respond", new=AsyncMock(return_value={"reply": "ok"})) as respond:
            response = client.post("/operations", json={"products": [], "transcript": "Customer: hi", "today": "2026-11-04"})

        self.assertEqual(response.status_code, 200)
        self.assertEqual(respond.call_args.args[3:], (False, None))

    def test_an_unusable_model_answer_is_a_502(self):
        with patch("app.main.operations_respond", new=AsyncMock(return_value=None)):
            response = client.post("/operations", json={"products": [], "transcript": "x", "today": "2026-11-04"})

        self.assertEqual(response.status_code, 502)

    def test_translate_takes_text_and_target(self):
        with patch("app.main.translate", new=AsyncMock(return_value={"translation": "x", "language": "en"})) as translate:
            response = client.post("/translate", json={"text": "Hello", "target": "Hindi"})

        self.assertEqual(response.status_code, 200)
        self.assertEqual(translate.call_args.args, ("Hello", "Hindi"))

    def test_ask_passes_the_chat_history_on(self):
        answer = {"answer": "ok", "handoff": False, "used": [], "attempts": 0, "relevant": True}

        with patch("app.main.answer_question", new=AsyncMock(return_value=answer)) as ask:
            response = client.post("/ask", json={
                "workspace_id": 3, "question": "Haan", "reply_style": "hinglish",
                "history": [{"from": "customer", "text": "Website chahiye"}, {"from": "business", "text": "Connect karun?"}],
            })

        self.assertEqual(response.status_code, 200)
        self.assertEqual(ask.call_args.args, (3, "Haan", "hinglish", [{"from": "customer", "text": "Website chahiye"}, {"from": "business", "text": "Connect karun?"}]))

    def test_ask_without_history_still_works(self):
        answer = {"answer": "ok", "handoff": False, "used": [], "attempts": 0, "relevant": True}

        with patch("app.main.answer_question", new=AsyncMock(return_value=answer)) as ask:
            self.assertEqual(client.post("/ask", json={"workspace_id": 3, "question": "Hi"}).status_code, 200)

        self.assertEqual(ask.call_args.args, (3, "Hi", "auto", []))

    def test_a_request_missing_a_field_is_refused_not_a_server_error(self):
        # the backend must send exactly these names: a missing one is a 422 here, never a silent 500
        self.assertEqual(client.post("/operations", json={"products": [], "today": "2026-11-04"}).status_code, 422)
        self.assertEqual(client.post("/qualify", json={"questions": ["q"]}).status_code, 422)
        self.assertEqual(client.post("/promises", json={"transcript": "t"}).status_code, 422)
        self.assertEqual(client.post("/campaign-draft", json={"business": "Shop", "products": []}).status_code, 422)

    def test_qualify_promises_and_campaign_draft_are_reachable(self):
        with patch("app.main.qualify", new=AsyncMock(return_value={"reply": "ok"})), \
                patch("app.main.find_promises", new=AsyncMock(return_value=[])), \
                patch("app.main.marketing_draft", new=AsyncMock(return_value={"name": "x", "language": "hi", "body": "b"})):
            qualify = client.post("/qualify", json={"questions": ["What do you need?"], "transcript": "Customer: hi"})
            promises = client.post("/promises", json={"transcript": "Customer: kal dunga", "today": "2026-11-04"})
            draft = client.post("/campaign-draft", json={"business": "Shop", "products": [{"name": "Atta", "price": "250.00"}], "brief": "Sunday offer"})

        self.assertEqual([qualify.status_code, promises.status_code, draft.status_code], [200, 200, 200])
        self.assertEqual(promises.json(), {"promises": []})


if __name__ == "__main__":
    unittest.main()
