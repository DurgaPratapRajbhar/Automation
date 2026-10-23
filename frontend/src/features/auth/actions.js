'use server';

import { redirect } from 'next/navigation';
import { backend } from '@/lib/backend';
import { formError, formValues } from '@/lib/forms';
import { saveSession } from '@/lib/session';

// Shop login: only shop users get in (the API rejects super admin accounts here)
export async function login(previousState, formData) {
  const input = formValues(formData);
  const { ok, data } = await backend('auth/login', { method: 'POST', body: input });

  if (!ok) return formError(data, { email: input.email });

  await saveSession(data.data, { workspaceId: data.data.workspaces[0]?.id });
  redirect('/dashboard');
}

// Super admin login: separate API route and separate session cookies
export async function adminLogin(previousState, formData) {
  const input = formValues(formData);
  const { ok, data } = await backend('admin/auth/login', { method: 'POST', body: input, admin: true });

  if (!ok) return formError(data, { email: input.email });

  await saveSession(data.data, { admin: true });
  redirect('/admin');
}
