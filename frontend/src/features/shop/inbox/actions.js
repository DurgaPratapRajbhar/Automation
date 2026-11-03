'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError } from '@/lib/forms';

// Look at a reply in another language before it goes (the customer's language unless `to` is given)
export async function translateReply(conversationId, text, to) {
  const { ok, data } = await backend(`conversations/${conversationId}/translate`, { method: 'POST', body: { text, to } });

  return ok ? { translation: data.data } : { message: data.errors?.text?.[0] ?? data.message };
}

// "Translate" on one message: it is shown in the Inbox language
export async function translateMessage(conversationId, messageId) {
  const { ok, data } = await backend(`conversations/${conversationId}/messages/${messageId}/translate`, { method: 'POST' });

  return ok ? {} : { message: data.message };
}

// Sends a reply as it is, or in another language with what the owner wrote kept beside it ({ body, original, language })
export async function sendReply(conversationId, reply) {
  const { ok, data } = await backend(`conversations/${conversationId}/messages`, { method: 'POST', body: reply });

  if (!ok) return { message: data.errors?.body?.[0] ?? data.message };

  revalidatePath(`/inbox/${conversationId}`);
  revalidatePath('/inbox');

  // A failed WhatsApp send is still saved; tell the user
  return data.data.status === 'failed' ? { message: data.message } : { sent: true };
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
