import { backend } from '@/lib/backend';

// status: open | done | missed | all
export function getPromises({ status = 'open', cursor }) {
  const params = new URLSearchParams({ status });
  if (cursor) params.set('cursor', cursor);

  return backend(`promises?${params}`);
}

// The business numbers and workflows on the Dashboard (own permission: overview.view)
export function getOverview() {
  return backend('overview');
}

// Counts for the Dashboard card
export function getSummary() {
  return backend('promises/summary');
}
