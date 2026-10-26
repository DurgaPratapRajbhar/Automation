import { backend } from '@/lib/backend';

export function getChannel(channelId) {
  return backend(`channels/${channelId}`);
}

// Templates live at Meta; the backend fetches them for this number
export function getTemplates(channelId) {
  return backend(`channels/${channelId}/templates`);
}
