'use client';

import { useState, useTransition } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { fieldClass } from '@/components/ui/Input';
import { draftCampaign } from '@/features/shop/campaigns/actions';

// "Sunday grocery offer bhejna hai" -> the assistant writes the message; you read it, change it, send it to Meta
export default function AiDraftBox({ onDraft }) {
  const [brief, setBrief] = useState('');
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function write(event) {
    event.preventDefault();
    setError(null);

    startTransition(async () => {
      const response = await draftCampaign(brief);

      response.message ? setError(response.message) : onDraft(response.draft);
    });
  }

  return (
    <form onSubmit={write} className="space-y-3 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
      <h2 className="font-semibold">Write it with AI</h2>
      <p className="text-sm text-slate-700">Say what you want in a few words. The message is written from your Products and prices, and you can change it before it goes to Meta.</p>

      <label htmlFor="brief" className="sr-only">What do you want to say?</label>
      <textarea
        id="brief"
        value={brief}
        onChange={(event) => setBrief(event.target.value)}
        rows={2}
        required
        minLength={5}
        maxLength={500}
        placeholder="Sunday grocery offer bhejna hai customers ko"
        className={fieldClass}
      />

      <Alert message={error} />
      <Button pending={pending}>Write the message</Button>
    </form>
  );
}
