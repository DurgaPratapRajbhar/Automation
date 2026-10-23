import { cache } from 'react';
import { backend } from '@/lib/backend';

// Every call here uses the super admin session (separate from the shop session)
const adminBackend = (path) => backend(path, { admin: true });

// Logged-in super admin. cache() = one call per page load.
export const getMe = cache(() => adminBackend('auth/me'));

export function getWorkspaces({ search, cursor }) {
  const params = new URLSearchParams();
  if (search) params.set('search', search);
  if (cursor) params.set('cursor', cursor);

  return adminBackend(`admin/workspaces?${params}`);
}

export const getRoleTemplates = () => adminBackend('admin/role-templates');

export const getAdminPermissionTree = () => adminBackend('admin/permissions');
