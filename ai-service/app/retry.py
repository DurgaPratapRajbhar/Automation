import asyncio
import random

import httpx

# Gemini limit lagne (429) ya busy hone (5xx) par seedha fail mat karo, thoda ruk kar dobara try karo.
RETRY_STATUSES = {429, 500, 502, 503, 504}
MAX_TRIES = 3
BASE_DELAY = 1.5
MAX_DELAY = 10.0


def _delay(response: httpx.Response, attempt: int) -> float:
    retry_after = response.headers.get("retry-after", "")
    if retry_after.isdigit():
        return min(float(retry_after), MAX_DELAY)
    return min(BASE_DELAY * 2 ** (attempt - 1), MAX_DELAY) + random.uniform(0, 0.5)


async def post_with_retry(client: httpx.AsyncClient, url: str, *, sleep=asyncio.sleep, **kwargs) -> httpx.Response:
    for attempt in range(1, MAX_TRIES + 1):
        response = await client.post(url, **kwargs)
        if response.status_code not in RETRY_STATUSES or attempt == MAX_TRIES:
            response.raise_for_status()
            return response
        await sleep(_delay(response, attempt))
