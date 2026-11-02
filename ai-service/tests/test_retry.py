"""Gemini 429/5xx par retry: tests with a fake HTTP transport (no network, no real waiting)."""
import asyncio
import os
import sys
import unittest

import httpx

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.retry import MAX_TRIES, post_with_retry  # noqa: E402


def run(statuses, headers=None):
    calls = []
    waits = []

    def handler(request):
        calls.append(1)
        status = statuses[min(len(calls), len(statuses)) - 1]
        return httpx.Response(status, headers=headers or {}, json={"ok": status == 200})

    async def fake_sleep(seconds):
        waits.append(seconds)

    async def go():
        async with httpx.AsyncClient(transport=httpx.MockTransport(handler)) as client:
            return await post_with_retry(client, "https://example.test/x", sleep=fake_sleep, json={})

    return go, calls, waits


class RetryTests(unittest.TestCase):
    def test_success_needs_no_retry(self):
        go, calls, waits = run([200])
        self.assertEqual(asyncio.run(go()).status_code, 200)
        self.assertEqual((len(calls), waits), (1, []))

    def test_429_then_success_is_retried(self):
        go, calls, waits = run([429, 200])
        self.assertEqual(asyncio.run(go()).status_code, 200)
        self.assertEqual(len(calls), 2)
        self.assertEqual(len(waits), 1)

    def test_persistent_429_gives_up_after_max_tries(self):
        go, calls, _ = run([429])
        with self.assertRaises(httpx.HTTPStatusError):
            asyncio.run(go())
        self.assertEqual(len(calls), MAX_TRIES)

    def test_client_errors_are_not_retried(self):
        for status in (400, 401, 404):
            go, calls, _ = run([status])
            with self.assertRaises(httpx.HTTPStatusError):
                asyncio.run(go())
            self.assertEqual(len(calls), 1)

    def test_retry_after_header_is_respected_and_capped(self):
        go, _, waits = run([429, 200], headers={"retry-after": "4"})
        asyncio.run(go())
        self.assertEqual(waits, [4.0])
        go, _, waits = run([503, 200], headers={"retry-after": "999"})
        asyncio.run(go())
        self.assertEqual(waits, [10.0])


if __name__ == "__main__":
    unittest.main()
