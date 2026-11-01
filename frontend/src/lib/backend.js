import { cookies, headers as requestHeaders } from 'next/headers';
import { cookiesFor } from '@/lib/cookies';

// The visitor's IP, so the API's rate limits count each person, not this server.
// The last X-Forwarded-For entry is the one our own proxy (Caddy) added; earlier ones come from the client.
async function clientIp() {
  const incoming = await requestHeaders();
  const forwarded = incoming.get('x-forwarded-for');

  return forwarded ? forwarded.split(',').pop().trim() : incoming.get('x-real-ip');
}

async function baseHeaders(names, extra) {
  const store = await cookies();
  const token = store.get(names.access)?.value;
  const workspaceId = names.workspace && store.get(names.workspace)?.value;
  const ip = await clientIp();

  const headers = { Accept: 'application/json', ...extra };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (workspaceId) headers['X-Workspace-Id'] = workspaceId;
  if (ip) headers['X-Forwarded-For'] = ip;

  return headers;
}

// Server only: the single place that calls the Laravel API.
// admin: true uses the super admin session instead of the shop session.
// Returns { ok, status, data } and never throws for API errors.
export async function backend(path, { method = 'GET', body, admin = false } = {}) {
  const headers = await baseHeaders(cookiesFor(admin), { 'Content-Type': 'application/json' });

  const res = await fetch(`${process.env.BACKEND_URL}/api/${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
    cache: 'no-store',
  });

  const data = await res.json().catch(() => ({ success: false, message: 'Server error.' }));

  return { ok: res.ok, status: res.status, data };
}

// Shop session only: send multipart/form-data (file uploads). fetch sets the boundary itself.
export async function backendForm(path, formData, { method = 'POST' } = {}) {
  const headers = await baseHeaders(cookiesFor(false));

  const res = await fetch(`${process.env.BACKEND_URL}/api/${path}`, { method, headers, body: formData, cache: 'no-store' });

  const data = await res.json().catch(() => ({ success: false, message: 'Server error.' }));

  return { ok: res.ok, status: res.status, data };
}
