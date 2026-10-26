import { backend } from '@/lib/backend';

// Connected numbers + the public settings for the "Connect WhatsApp" popup
export function getChannels() {
  return backend('channels');
}
