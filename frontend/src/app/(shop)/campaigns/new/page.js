import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import CampaignForm from '@/features/shop/campaigns/CampaignForm';
import { getCampaignOptions } from '@/features/shop/campaigns/queries';

export const metadata = { title: 'New campaign' };

export default async function NewCampaignPage() {
  const { ok, data } = await getCampaignOptions();

  if (!ok) return <Alert message={data.message} />;

  return (
    <div className="space-y-4">
      <Link href="/campaigns" className="text-sm text-slate-500 underline">Back to campaigns</Link>
      <PageHeader title="New campaign" />
      <CampaignForm options={data.data} />
    </div>
  );
}
