import { backend } from '@/lib/backend';

export function getCustomers({ search, cursor }) {
  const params = new URLSearchParams();
  if (search) params.set('search', search);
  if (cursor) params.set('cursor', cursor);

  return backend(`customers?${params}`);
}

export function getCustomer(customerId) {
  return backend(`customers/${customerId}`);
}
