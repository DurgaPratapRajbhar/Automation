import { backend } from '@/lib/backend';

// status: proposed | executed | rejected | escalated
export function getActions({ status = 'proposed', cursor }) {
  const params = new URLSearchParams({ status });
  if (cursor) params.set('cursor', cursor);

  return backend(`actions?${params}`);
}
