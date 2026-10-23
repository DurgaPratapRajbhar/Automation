import { backend } from '@/lib/backend';

export function getKnowledgeSources({ search, cursor }) {
  const params = new URLSearchParams();
  if (search) params.set('search', search);
  if (cursor) params.set('cursor', cursor);

  return backend(`knowledge-sources?${params}`);
}

export function getKnowledgeSource(knowledgeSourceId) {
  return backend(`knowledge-sources/${knowledgeSourceId}`);
}
