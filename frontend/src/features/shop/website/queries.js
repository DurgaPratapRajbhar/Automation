import { backend } from '@/lib/backend';

export const getApiKey = () => backend('workspace/api-key');
