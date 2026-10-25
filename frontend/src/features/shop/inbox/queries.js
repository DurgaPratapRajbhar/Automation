import { backend } from '@/lib/backend';

export function getConversations({ search, cursor }) {
  const params = new URLSearchParams();
  if (search) params.set('search', search);
  if (cursor) params.set('cursor', cursor);

  return backend(`conversations?${params}`);
}

// Opening a chat also marks it read on the server
export function getConversation(conversationId) {
  return backend(`conversations/${conversationId}`);
}
