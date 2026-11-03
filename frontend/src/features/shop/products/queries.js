import { backend } from '@/lib/backend';

export function getProducts({ search, cursor }) {
  const params = new URLSearchParams();
  if (search) params.set('search', search);
  if (cursor) params.set('cursor', cursor);

  return backend(`products?${params}`);
}
