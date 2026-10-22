import { backend } from '@/lib/backend';

export const getRoles = () => backend('roles');

export const getRole = (roleId) => backend(`roles/${roleId}`);

export const getPermissionTree = () => backend('permissions');
