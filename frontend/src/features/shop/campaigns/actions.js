'use server';

import { revalidatePath } from 'next/cache';
import { backend, backendForm } from '@/lib/backend';
import { formError } from '@/lib/forms';

// The three steps of "Import contacts": look inside the file, then import it with the chosen columns.
// Both are called from the page with a FormData (the file must stay selected between the steps).
export async function previewContacts(formData) {
  const { ok, data } = await backendForm('contact-imports/preview', formData);

  return ok ? { preview: data.data } : { message: data.errors?.file?.[0] ?? data.message };
}

export async function importContacts(formData) {
  const { ok, data } = await backendForm('contact-imports', formData);

  if (!ok) return { message: data.errors?.phone_column?.[0] ?? data.errors?.file?.[0] ?? data.message };

  revalidatePath('/customers');

  return { result: data.data, message: data.message };
}

// The approved templates of one number (only those can be sent), in the shape the form needs
export async function loadTemplates(channelId) {
  const { ok, data } = await backend(`channels/${channelId}/templates`);

  if (!ok) return { message: data.message };

  return { templates: data.data.filter((template) => template.status === 'APPROVED') };
}

export async function createCampaign(payload) {
  const { ok, data } = await backend('campaigns', { method: 'POST', body: payload });

  if (!ok) return formError(data);

  revalidatePath('/campaigns');

  return { campaign: data.data };
}

export async function startCampaign(campaignId) {
  const { ok, data } = await backend(`campaigns/${campaignId}/start`, { method: 'POST' });

  revalidatePath(`/campaigns/${campaignId}`);

  return ok ? { message: data.message } : formError(data);
}

export async function cancelCampaign(campaignId) {
  const { ok, data } = await backend(`campaigns/${campaignId}/cancel`, { method: 'POST' });

  revalidatePath(`/campaigns/${campaignId}`);

  return ok ? { message: data.message } : formError(data);
}
