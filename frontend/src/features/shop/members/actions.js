'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError, formValues } from '@/lib/forms';

// Adds an existing user by email, or creates the user (name + password needed then)
export async function addMember(previousState, formData) {
  const values = formValues(formData);
  const { ok, data } = await backend('members', { method: 'POST', body: values });

  if (!ok) {
    const { password, ...safeValues } = values;

    return formError(data, safeValues);
  }

  revalidatePath('/members');

  return { message: data.message, success: true };
}

export async function changeMemberRole(userId, previousState, formData) {
  const { ok, data } = await backend(`members/${userId}`, { method: 'PUT', body: formValues(formData) });

  if (!ok) return formError(data);

  revalidatePath('/members');

  return {};
}

export async function removeMember(userId) {
  const { ok, data } = await backend(`members/${userId}`, { method: 'DELETE' });

  if (!ok) return formError(data);

  revalidatePath('/members');

  return {};
}
