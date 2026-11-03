'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError, formValues } from '@/lib/forms';

// Done / not coming / open again
export async function setPromiseStatus(promiseId, status) {
  const { ok, data } = await backend(`promises/${promiseId}`, { method: 'PUT', body: { status } });

  if (!ok) return { message: data.message };

  revalidatePath('/promises');
  revalidatePath('/dashboard');

  return {};
}

export async function addPromise(previousState, formData) {
  const values = formValues(formData);
  const { ok, data } = await backend('promises', { method: 'POST', body: values });

  if (!ok) return formError(data, values);

  revalidatePath('/promises');
  revalidatePath('/dashboard');

  return { message: data.message, success: true };
}
