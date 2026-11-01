'use server';

import { backend } from '@/lib/backend';
import { formError, formValues } from '@/lib/forms';

// Change my own password. The passwords are never sent back to the form.
export async function changePassword(previousState, formData) {
  const { ok, data } = await backend('auth/password', { method: 'PUT', body: formValues(formData) });

  if (!ok) return formError(data);

  return { message: data.message, success: true };
}
