import Link from 'next/link';
import ActionButton from '@/components/ui/ActionButton';
import Alert from '@/components/ui/Alert';
import AutoRefresh from '@/components/ui/AutoRefresh';
import PageHeader from '@/components/ui/PageHeader';
import { cancelCampaign, startCampaign } from '@/features/shop/campaigns/actions';
import TestMessageForm from '@/features/shop/campaigns/TestMessageForm';
import CampaignStatusBadge from '@/features/shop/campaigns/CampaignStatusBadge';
import { getCampaign } from '@/features/shop/campaigns/queries';
import { can } from '@/features/shop/workspace/queries';

export const metadata = { title: 'Campaign' };

// [report key, label, hint]: from "people picked" down to what happened to each message
const NUMBERS = [
  ['total', 'People', 'Picked for this campaign'],
  ['sent', 'Sent', 'Handed to WhatsApp'],
  ['delivered', 'Delivered', 'Reached the phone'],
  ['read', 'Read', 'Opened the message'],
  ['replied', 'Replied', 'Wrote back'],
  ['failed', 'Failed', 'WhatsApp refused it'],
  ['skipped', 'Left out', 'Asked us to stop'],
  ['queued', 'Waiting', 'Not sent yet'],
];

export default async function CampaignPage({ params }) {
  const { campaignId } = await params;
  const { ok, data } = await getCampaign(campaignId);

  if (!ok) return <Alert message={data.message} />;

  const campaign = data.data;
  const { report } = campaign;

  return (
    <div className="space-y-6">
      {campaign.status === 'sending' && <AutoRefresh seconds={5} />}

      <Link href="/campaigns" className="text-sm text-slate-500 underline">Back to campaigns</Link>
      <PageHeader title={campaign.name} />

      <div className="flex flex-wrap items-center gap-3">
        <CampaignStatusBadge status={campaign.status} />
        {campaign.status === 'draft' && (await can('campaigns.start')) && (
          <ActionButton
            action={startCampaign.bind(null, campaign.id)}
            label={`Send to ${report.total} people`}
            variant="primary"
            confirm={`Send this message to ${report.total} people now?`}
          />
        )}
        {campaign.status === 'sending' && (await can('campaigns.cancel')) && (
          <ActionButton action={cancelCampaign.bind(null, campaign.id)} label="Stop sending" confirm="Stop? Messages not sent yet will not be sent." />
        )}
      </div>

      <SendingLimitNote limit={campaign.sending_limit} people={report.total} />

      <section className="space-y-2">
        <h2 className="font-semibold">The message</h2>
        <p className="max-w-2xl whitespace-pre-line rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{campaign.template_body}</p>
      </section>

      <section className="space-y-2">
        <h2 className="font-semibold">How the first messages will read</h2>
        <ul className="max-w-2xl space-y-2">
          {campaign.sample_messages.map((message) => (
            <li key={message.phone} className="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
              <span className="block text-xs text-slate-500">To {message.name} · {message.phone}</span>
              {message.text}
            </li>
          ))}
        </ul>
      </section>

      {campaign.status === 'draft' && (await can('campaigns.test')) && <TestMessageForm campaignId={campaign.id} />}

      <section className="space-y-2">
        <h2 className="font-semibold">Report</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {NUMBERS.map(([key, label, hint]) => (
            <div key={key} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
              <p className="text-2xl font-semibold">{report[key]}</p>
              <p className="text-sm font-medium">{label}</p>
              <p className="text-xs text-slate-500">{hint}</p>
            </div>
          ))}
        </div>
      </section>

      {campaign.failed_people.length > 0 && (
        <section className="space-y-2">
          <h2 className="font-semibold">Messages that did not go</h2>
          <ul className="max-w-2xl space-y-1 text-sm">
            {campaign.failed_people.map((person) => (
              <li key={person.phone} className="rounded-lg bg-red-50 px-3 py-2 text-red-800">
                <strong>{person.name}</strong> · {person.phone}
                <span className="block text-xs">{person.error}</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

// WhatsApp lets a number message only so many new people a day; say so before a bigger campaign starts
function SendingLimitNote({ limit, people }) {
  if (!limit || limit.per_day === null) return null;

  return limit.over_limit ? (
    <p className="max-w-2xl rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
      WhatsApp lets this number message about <strong>{limit.per_day}</strong> new people a day, and this campaign has <strong>{people}</strong>.
      Messages beyond that may not be delivered. Send it to fewer people, or send it in parts over a few days.
    </p>
  ) : (
    <p className="text-sm text-slate-500">This number can message about {limit.per_day} new people a day.</p>
  );
}
