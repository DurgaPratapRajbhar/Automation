import { cookies } from 'next/headers';
import { COOKIES } from '@/lib/cookies';

// Server only: the single place that calls the Laravel API.
// Returns { ok, status, data } and never throws for API errors.
export async function backend(path, { method = 'GET', body } = {}) {
  const store = await cookies();
  const token = store.get(COOKIES.access)?.value;
  const workspaceId = store.get(COOKIES.workspace)?.value;

  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (workspaceId) headers['X-Workspace-Id'] = workspaceId;

  const res = await fetch(`${process.env.BACKEND_URL}/api/${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
    cache: 'no-store',
  });

  const data = await res.json().catch(() => ({ success: false, message: 'Server error.' }));

  return { ok: res.ok, status: res.status, data };
}
