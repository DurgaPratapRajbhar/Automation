'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError } from '@/lib/forms';

const pagePath = (channelId) => `/settings/whatsapp/${channelId}/templates`;

export async function createTemplate(channelId, previousState, formData) {
  const values = {
    name: formData.get('name'),
    category: formData.get('category'),
    language: formData.get('language'),
    body: formData.get('body'),
    // One sample per {{n}}, in order
    examples: formData.getAll('examples'),
  };

  const { ok, data } = await backend(`channels/${channelId}/templates`, { method: 'POST', body: values });

  if (!ok) return formError(data, values);

  revalidatePath(pagePath(channelId));

  return { message: data.message, done: Date.now() };
}

export async function deleteTemplate(channelId, name) {
  const { ok, data } = await backend(`channels/${channelId}/templates/${name}`, { method: 'DELETE' });

  if (!ok) return formError(data);

  revalidatePath(pagePath(channelId));

  return {};
}
