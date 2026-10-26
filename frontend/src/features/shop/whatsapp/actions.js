'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError } from '@/lib/forms';

// Called right after the Meta popup. The code expires in ~30 seconds, so no delay here.
export async function connectWhatsApp({ code, wabaId, phoneNumberId }) {
  const { ok, data } = await backend('channels/connect', {
    method: 'POST',
    body: { code, waba_id: wabaId, phone_number_id: phoneNumberId },
  });

  if (!ok) return { ok: false, message: data.message };

  revalidatePath('/settings/whatsapp');

  return { ok: true, message: data.message };
}

export async function deleteChannel(channelId) {
  const { ok, data } = await backend(`channels/${channelId}`, { method: 'DELETE' });

  if (!ok) return formError(data);

  revalidatePath('/settings/whatsapp');

  return {};
}
