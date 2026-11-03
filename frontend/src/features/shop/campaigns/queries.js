import { backend } from '@/lib/backend';

export function getCampaigns({ cursor }) {
  const params = new URLSearchParams();
  if (cursor) params.set('cursor', cursor);

  return backend(`campaigns?${params}`);
}

export function getCampaign(campaignId) {
  return backend(`campaigns/${campaignId}`);
}

// Connected numbers, the columns of the uploaded Excel, and how many people can get a message
export function getCampaignOptions() {
  return backend('campaigns/options');
}
