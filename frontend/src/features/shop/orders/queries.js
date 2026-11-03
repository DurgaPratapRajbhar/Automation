import { backend } from '@/lib/backend';

// status: draft | confirmed | cancelled | all
export function getOrders({ status = 'all', cursor }) {
  const params = new URLSearchParams({ status });
  if (cursor) params.set('cursor', cursor);

  return backend(`orders?${params}`);
}
