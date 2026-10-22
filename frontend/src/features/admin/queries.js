import { cache } from 'react';
import { backend } from '@/lib/backend';

// Logged-in user, including the super admin flag. cache() = one call per page load.
export const getMe = cache(() => backend('auth/me'));

export function getWorkspaces({ search, cursor }) {
  const params = new URLSearchParams();
  if (search) params.set('search', search);
  if (cursor) params.set('cursor', cursor);

  return backend(`admin/workspaces?${params}`);
}

export const getRoleTemplates = () => backend('admin/role-templates');

export const getAdminPermissionTree = () => backend('admin/permissions');
