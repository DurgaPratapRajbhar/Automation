'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { backend, backendForm } from '@/lib/backend';
import { formError } from '@/lib/forms';

// Create goes as multipart so a file can ride along; only the needed fields are forwarded
export async function createKnowledge(previousState, formData) {
  const type = formData.get('type') ?? 'text';
  const title = formData.get('title') ?? '';

  const payload = new FormData();
  payload.set('type', type);
  payload.set('title', title);

  if (type === 'text') {
    payload.set('content', formData.get('content') ?? '');
  } else {
    const file = formData.get('file');
    if (file && file.size > 0) payload.set('file', file);
  }

  const { ok, data } = await backendForm('knowledge-sources', payload);

  if (!ok) return formError(data, { type, title, content: formData.get('content') });

  revalidatePath('/knowledge');
  redirect('/knowledge');
}

// Edit changes only title (and content for text sources)
export async function updateKnowledge(knowledgeSourceId, previousState, formData) {
  const values = { title: formData.get('title') };
  if (formData.has('content')) values.content = formData.get('content') ?? '';

  const { ok, data } = await backend(`knowledge-sources/${knowledgeSourceId}`, { method: 'PUT', body: values });

  if (!ok) return formError(data, values);

  revalidatePath('/knowledge');
  redirect('/knowledge');
}

export async function deleteKnowledge(knowledgeSourceId) {
  const { ok, data } = await backend(`knowledge-sources/${knowledgeSourceId}`, { method: 'DELETE' });

  if (!ok) return formError(data);

  revalidatePath('/knowledge');
  redirect('/knowledge');
}
