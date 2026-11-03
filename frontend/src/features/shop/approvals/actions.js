'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';

// decision: approve (it is done now) | reject
export async function decideAction(actionId, decision) {
  const { ok, data } = await backend(`actions/${actionId}`, { method: 'PUT', body: { decision } });

  revalidatePath('/approvals');
  revalidatePath('/dashboard');

  // An approved action that failed is still saved (escalated); say why
  return ok && data.data.status !== 'escalated' ? {} : { message: data.data?.error ?? data.message };
}
