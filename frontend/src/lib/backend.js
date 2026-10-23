import { cookies } from 'next/headers';
import { cookiesFor } from '@/lib/cookies';

// Server only: the single place that calls the Laravel API.
// admin: true uses the super admin session instead of the shop session.
// Returns { ok, status, data } and never throws for API errors.
export async function backend(path, { method = 'GET', body, admin = false } = {}) {
  const store = await cookies();
  const names = cookiesFor(admin);
  const token = store.get(names.access)?.value;
  const workspaceId = names.workspace && store.get(names.workspace)?.value;

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
