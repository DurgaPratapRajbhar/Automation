import { cache } from 'react';
import { backend } from '@/lib/backend';

// What the user can do in the current workspace. cache() = one API call per page load.
export const getWorkspaceAccess = cache(() => backend('workspace/me'));

// Hides buttons the user may not use. The API checks the permission again.
export async function can(permission) {
  const { ok, data } = await getWorkspaceAccess();

  return ok && data.data.permissions.includes(permission);
}
