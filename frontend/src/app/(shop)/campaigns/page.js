import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import CampaignStatusBadge from '@/features/shop/campaigns/CampaignStatusBadge';
import { getCampaigns } from '@/features/shop/campaigns/queries';
import { can } from '@/features/shop/workspace/queries';

export const metadata = { title: 'Campaigns' };

export default async function CampaignsPage({ searchParams }) {
  const { cursor } = await searchParams;
  const { ok, data } = await getCampaigns({ cursor });

  if (!ok) return <Alert message={data.message} />;

  const canCreate = await can('campaigns.create');
  const nextPage = data.next_cursor && `/campaigns?${new URLSearchParams({ cursor: data.next_cursor })}`;

  return (
    <div className="space-y-4">
      <PageHeader title="Campaigns" actionLabel="New campaign" actionHref={canCreate && '/campaigns/new'} />

      <p className="max-w-2xl text-sm text-slate-600">
        Send one WhatsApp message to many people: fee reminders, payment follow-ups, offers, new leads. Each person gets their own name and details in it.
      </p>

      {(await can('contact-imports.create')) && (
        <Link href="/campaigns/import" className="inline-block rounded-lg px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100">
          Import contacts from Excel
        </Link>
      )}

      {data.data.length === 0 ? (
        <p className="text-slate-500">No campaigns yet. Import your contacts, then create your first one.</p>
      ) : (
        <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-slate-500">
              <tr>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.data.map((campaign) => (
                <tr key={campaign.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <Link href={`/campaigns/${campaign.id}`} className="font-medium hover:underline">{campaign.name}</Link>
                    <p className="text-slate-500">{campaign.template_name}</p>
                  </td>
                  <td className="px-4 py-3"><CampaignStatusBadge status={campaign.status} /></td>
                  <td className="whitespace-nowrap px-4 py-3 text-slate-500">{new Date(campaign.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {nextPage && <Link href={nextPage} className="text-sm underline">Older campaigns</Link>}
    </div>
  );
}
