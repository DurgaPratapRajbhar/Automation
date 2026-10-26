'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError } from '@/lib/forms';

export async function sendReply(conversationId, previousState, formData) {
  const body = formData.get('body') ?? '';
  const { ok, data } = await backend(`conversations/${conversationId}/messages`, { method: 'POST', body: { body } });

  if (!ok) return formError(data, { body });

  revalidatePath(`/inbox/${conversationId}`);
  revalidatePath('/inbox');

  // A failed WhatsApp send is still saved; tell the user
  return data.data.status === 'failed' ? { message: data.message } : { sent: Date.now() };
}

// Approved template; value of the picker is "name|language"
export async function sendTemplateMessage(conversationId, previousState, formData) {
  const [name, language] = String(formData.get('template') ?? '').split('|');
  const parameters = formData.getAll('parameters');

  const { ok, data } = await backend(`conversations/${conversationId}/template`, {
    method: 'POST',
    body: { name, language, parameters },
  });

  if (!ok) return formError(data, { template: formData.get('template'), parameters });

  revalidatePath(`/inbox/${conversationId}`);
  revalidatePath('/inbox');

  return data.data.status === 'failed' ? { message: data.message } : { sent: Date.now() };
}
