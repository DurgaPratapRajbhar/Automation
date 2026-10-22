import { backend } from '@/lib/backend';

export const getProfile = () => backend('workspace/profile');
