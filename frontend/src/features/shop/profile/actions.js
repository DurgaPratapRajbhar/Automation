'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError, formValues } from '@/lib/forms';

export async function saveProfile(previousState, formData) {
  const values = formValues(formData);
  const { ok, data } = await backend('workspace/profile', { method: 'PUT', body: values });

  if (!ok) return formError(data, values);

  // Shop name is also shown in the sidebar
  revalidatePath('/', 'layout');

  return { message: data.message, success: true, values: data.data };
}
