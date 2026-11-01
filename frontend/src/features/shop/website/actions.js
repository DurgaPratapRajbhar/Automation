'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';

// The full key comes back only here; the page shows it once
export async function createApiKey() {
  const { ok, data } = await backend('workspace/api-key', { method: 'POST' });

  if (!ok) return { message: data.message };

  revalidatePath('/settings/profile');

  return { message: data.message, success: true, key: data.data.key };
}

export async function revokeApiKey() {
  const { ok, data } = await backend('workspace/api-key', { method: 'DELETE' });

  if (!ok) return { message: data.message };

  revalidatePath('/settings/profile');

  return { message: data.message, success: true };
}
