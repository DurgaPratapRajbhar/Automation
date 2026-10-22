'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { backend } from '@/lib/backend';
import { formError, roleValues } from '@/lib/forms';

export async function setWorkspaceStatus(workspaceId, status) {
  const { ok, data } = await backend(`admin/workspaces/${workspaceId}/status`, { method: 'PATCH', body: { status } });

  if (!ok) return formError(data);

  revalidatePath('/admin/workspaces');

  return {};
}

// templateId = null creates, a number updates
export async function saveRoleTemplate(templateId, previousState, formData) {
  const values = roleValues(formData);
  const { ok, data } = await backend(templateId ? `admin/role-templates/${templateId}` : 'admin/role-templates', {
    method: templateId ? 'PUT' : 'POST',
    body: values,
  });

  if (!ok) return formError(data, values);

  redirect('/admin/role-templates');
}

export async function deleteRoleTemplate(templateId) {
  const { ok, data } = await backend(`admin/role-templates/${templateId}`, { method: 'DELETE' });

  if (!ok) return formError(data);

  redirect('/admin/role-templates');
}
