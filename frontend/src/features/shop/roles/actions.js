'use server';

import { redirect } from 'next/navigation';
import { backend } from '@/lib/backend';
import { formError, roleValues } from '@/lib/forms';

// roleId = null creates, a number updates
export async function saveRole(roleId, previousState, formData) {
  const values = roleValues(formData);
  const { ok, data } = await backend(roleId ? `roles/${roleId}` : 'roles', {
    method: roleId ? 'PUT' : 'POST',
    body: values,
  });

  if (!ok) return formError(data, values);

  redirect('/roles');
}

export async function deleteRole(roleId) {
  const { ok, data } = await backend(`roles/${roleId}`, { method: 'DELETE' });

  if (!ok) return formError(data);

  redirect('/roles');
}
