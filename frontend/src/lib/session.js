import { cookies } from 'next/headers';
import { COOKIE_OPTIONS, THIRTY_DAYS, cookiesFor } from '@/lib/cookies';

export async function saveSession({ access_token, refresh_token, expires_in }, { workspaceId, admin = false } = {}) {
  const store = await cookies();
  const names = cookiesFor(admin);

  // The access cookie expires with the token, so proxy.js knows when to refresh
  store.set(names.access, access_token, { ...COOKIE_OPTIONS, maxAge: expires_in });
  store.set(names.refresh, refresh_token, { ...COOKIE_OPTIONS, maxAge: THIRTY_DAYS });

  if (workspaceId) {
    store.set(names.workspace, String(workspaceId), { ...COOKIE_OPTIONS, maxAge: THIRTY_DAYS });
  }
}
