'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useState, useTransition } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { fieldClass } from '@/components/ui/Input';
import { createCampaign, loadTemplates } from '@/features/shop/campaigns/actions';

const SOURCES = [
  { value: 'name', label: 'Person’s name' },
  { value: 'phone', label: 'Phone number' },
  { value: 'column', label: 'A column from the Excel' },
  { value: 'text', label: 'The same text for everyone' },
];

// How many blanks ({{1}}, {{2}}...) the template has
const blanksIn = (body) => new Set(body.match(/\{\{\d+\}\}/g) ?? []).size;

// New campaign: pick the number and the approved template, say what goes into each blank, create.
// Nothing is sent here; the next page shows how the messages will read and has the Start button.
export default function CampaignForm({ options }) {
  const router = useRouter();
  const [name, setName] = useState('');
  const [channelId, setChannelId] = useState(options.channels[0]?.id ?? '');
  const [templates, setTemplates] = useState(null);
  const [template, setTemplate] = useState(null);
  const [sources, setSources] = useState([]);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  // The approved templates of the chosen number
  useEffect(() => {
    if (!channelId) return;

    setTemplates(null);
    loadTemplates(channelId).then((response) => {
      setError(response.message ?? null);
      setTemplates(response.templates ?? []);
      choose(response.templates?.[0] ?? null);
    });
  }, [channelId]); // eslint-disable-line react-hooks/exhaustive-deps

  function choose(next) {
    setTemplate(next);
    // First blank = the name, the others start on the first Excel column
    setSources(next ? Array.from({ length: blanksIn(next.body) }, (_, index) => (
      index === 0 ? { source: 'name' } : { source: 'column', value: options.columns[0] ?? '' }
    )) : []);
  }

  function change(index, patch) {
    setSources((current) => current.map((source, position) => (position === index ? { ...source, ...patch } : source)));
  }

  function submit(event) {
    event.preventDefault();
    setError(null);

    startTransition(async () => {
      const response = await createCampaign({
        name,
        channel_id: Number(channelId),
        template_name: template.name,
        template_language: template.language,
        parameters: sources,
      });

      if (response.campaign) return router.push(`/campaigns/${response.campaign.id}`);

      setError(response.message ?? Object.values(response.errors ?? {}).flat()[0]);
    });
  }

  if (options.channels.length === 0) {
    return <Alert message="No WhatsApp number is connected yet. Connect one first." />;
  }

  return (
    <form onSubmit={submit} className="max-w-2xl space-y-5">
      <p className="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700">
        <strong>{options.people.ready}</strong> of {options.people.total} saved people can get a campaign (they gave permission and have not said STOP).{' '}
        <Link href="/campaigns/import" className="font-medium underline">Import more people</Link>
      </p>

      <label className="block space-y-1 text-sm font-medium text-slate-700">
        Campaign name (only you see it)
        <input value={name} onChange={(event) => setName(event.target.value)} required maxLength={100} placeholder="Fees reminder, November" className={fieldClass} />
      </label>

      {options.channels.length > 1 && (
        <label className="block space-y-1 text-sm font-medium text-slate-700">
          Send from
          <select value={channelId} onChange={(event) => setChannelId(event.target.value)} className={fieldClass}>
            {options.channels.map((channel) => <option key={channel.id} value={channel.id}>{channel.name}</option>)}
          </select>
        </label>
      )}

      {templates === null && <p className="text-sm text-slate-500">Loading your approved messages…</p>}

      {templates?.length === 0 && (
        <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          This number has no approved message yet. WhatsApp only lets you message many people with a message it has approved.{' '}
          <Link href={`/settings/whatsapp/${channelId}/templates`} className="font-medium underline">Create one</Link>
        </p>
      )}

      {templates?.length > 0 && (
        <>
          <label className="block space-y-1 text-sm font-medium text-slate-700">
            Message
            <select
              value={`${template?.name}|${template?.language}`}
              onChange={(event) => choose(templates.find((item) => `${item.name}|${item.language}` === event.target.value))}
              className={fieldClass}
            >
              {templates.map((item) => <option key={`${item.name}|${item.language}`} value={`${item.name}|${item.language}`}>{item.name} ({item.language})</option>)}
            </select>
          </label>

          {template && <p className="whitespace-pre-line rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{template.body}</p>}

          {sources.length > 0 && (
            <fieldset className="space-y-3">
              <legend className="mb-1 text-sm font-medium text-slate-700">What goes into each blank?</legend>
              {sources.map((source, index) => (
                <div key={index} className="grid items-center gap-2 sm:grid-cols-[4rem_1fr_1fr]">
                  <span className="text-sm font-semibold text-slate-600">{`{{${index + 1}}}`}</span>
                  <select value={source.source} onChange={(event) => change(index, { source: event.target.value, value: '' })} className={fieldClass}>
                    {SOURCES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                  </select>
                  {source.source === 'column' && (
                    <select value={source.value ?? ''} onChange={(event) => change(index, { value: event.target.value })} className={fieldClass}>
                      <option value="">Choose a column…</option>
                      {options.columns.map((column) => <option key={column} value={column}>{column}</option>)}
                    </select>
                  )}
                  {source.source === 'text' && (
                    <input value={source.value ?? ''} onChange={(event) => change(index, { value: event.target.value })} placeholder="Text" maxLength={500} className={fieldClass} />
                  )}
                </div>
              ))}
              {options.columns.length === 0 && <p className="text-sm text-slate-500">No Excel columns yet. Import a file to use its columns.</p>}
            </fieldset>
          )}
        </>
      )}

      <Alert message={error} />

      <Button pending={pending} disabled={!template || !name}>Check and create</Button>
    </form>
  );
}
