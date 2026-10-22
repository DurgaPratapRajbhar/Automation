import { cookies } from 'next/headers';
import { COOKIES, COOKIE_OPTIONS, THIRTY_DAYS } from '@/lib/cookies';

export async function saveSession({ access_token, refresh_token, expires_in }, workspaceId) {
  const store = await cookies();

  // The access cookie expires with the token, so proxy.js knows when to refresh
  store.set(COOKIES.access, access_token, { ...COOKIE_OPTIONS, maxAge: expires_in });
  store.set(COOKIES.refresh, refresh_token, { ...COOKIE_OPTIONS, maxAge: THIRTY_DAYS });

  if (workspaceId) {
    store.set(COOKIES.workspace, String(workspaceId), { ...COOKIE_OPTIONS, maxAge: THIRTY_DAYS });
  }
}
