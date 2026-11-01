'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { backend } from '@/lib/backend';
import { formError, formValues, roleValues } from '@/lib/forms';

// New shop + owner login. The owner then adds staff and gives them roles from the shop app.
export async function createWorkspace(previousState, formData) {
  const values = formValues(formData);
  const { ok, data } = await backend('admin/workspaces', { method: 'POST', body: values, admin: true });

  if (!ok) {
    const { owner_password, ...safeValues } = values;

    return formError(data, safeValues);
  }

  revalidatePath('/admin/workspaces');
  redirect('/admin/workspaces');
}

// Rename a shop
export async function updateWorkspace(workspaceId, previousState, formData) {
  const values = formValues(formData);
  const { ok, data } = await backend(`admin/workspaces/${workspaceId}`, { method: 'PUT', body: values, admin: true });

  if (!ok) return formError(data, values);

  revalidatePath('/admin/workspaces');
  revalidatePath(`/admin/workspaces/${workspaceId}`);

  return { message: data.message, success: true, values };
}

// New password for one user of a shop (forgotten password). The passwords are never sent back to the form.
export async function resetMemberPassword(workspaceId, userId, previousState, formData) {
  const values = formValues(formData);
  const { ok, data } = await backend(`admin/workspaces/${workspaceId}/members/${userId}/password`, { method: 'PUT', body: values, admin: true });

  if (!ok) return formError(data);

  return { message: data.message, success: true };
}

export async function setWorkspaceStatus(workspaceId, status) {
  const { ok, data } = await backend(`admin/workspaces/${workspaceId}/status`, { method: 'PATCH', body: { status }, admin: true });

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
    admin: true,
  });

  if (!ok) return formError(data, values);

  revalidatePath('/admin/role-templates');
  redirect('/admin/role-templates');
}

export async function deleteRoleTemplate(templateId) {
  const { ok, data } = await backend(`admin/role-templates/${templateId}`, { method: 'DELETE', admin: true });

  if (!ok) return formError(data);

  revalidatePath('/admin/role-templates');
  redirect('/admin/role-templates');
}
