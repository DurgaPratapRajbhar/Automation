'use server';

import { redirect } from 'next/navigation';
import { backend } from '@/lib/backend';
import { formError, formValues } from '@/lib/forms';
import { saveSession } from '@/lib/session';

export async function login(previousState, formData) {
  const input = formValues(formData);
  const { ok, data } = await backend('auth/login', { method: 'POST', body: input });

  if (!ok) return formError(data, { email: input.email });

  const { workspaces } = data.data;

  await saveSession(data.data, workspaces[0]?.id);

  // A super admin may have no workspace of their own
  redirect(workspaces.length ? '/dashboard' : '/admin');
}

export async function register(previousState, formData) {
  const input = formValues(formData);
  const { ok, data } = await backend('auth/register', { method: 'POST', body: input });

  if (!ok) {
    // Never send passwords back to the browser
    const { password, password_confirmation, ...values } = input;

    return formError(data, values);
  }

  await saveSession(data.data, data.data.workspace.id);
  redirect('/dashboard');
}
