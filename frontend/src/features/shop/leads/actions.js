'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError } from '@/lib/forms';

// The Sales agent's switch and questions (the form sends question_1, question_2, ...)
export async function saveLeadQuestions(previousState, formData) {
  const questions = [...formData.entries()].filter(([key]) => key.startsWith('question_')).map(([, value]) => value);
  const body = { lead_flow_enabled: formData.get('lead_flow_enabled') === 'on', lead_questions: questions };

  const { ok, data } = await backend('workspace/profile', { method: 'PUT', body });

  if (!ok) return formError(data, body);

  revalidatePath('/settings/profile');

  return { message: data.message, success: true };
}
