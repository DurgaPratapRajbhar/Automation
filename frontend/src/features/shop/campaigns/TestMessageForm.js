'use client';

import { useState, useTransition } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { fieldClass } from '@/components/ui/Input';
import { sendTestMessage } from '@/features/shop/campaigns/actions';

// "Send a test to my own phone": the first person's message goes to a number you choose, before the real send
export default function TestMessageForm({ campaignId }) {
  const [phone, setPhone] = useState('');
  const [sent, setSent] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function submit(event) {
    event.preventDefault();
    setSent(null);
    setError(null);

    startTransition(async () => {
      const response = await sendTestMessage(campaignId, phone);

      if (response.message) return setError(response.message);

      response.sent.status === 'failed' ? setError(response.sent.error) : setSent(response.sent);
    });
  }

  return (
    <form onSubmit={submit} className="max-w-xl space-y-2">
      <h2 className="font-semibold">Send a test first</h2>
      <p className="text-sm text-slate-600">See the first message on your own phone before it goes to everyone.</p>
      <div className="flex gap-2">
        <input value={phone} onChange={(event) => setPhone(event.target.value)} required placeholder="Your WhatsApp number" inputMode="tel" className={fieldClass} />
        <Button pending={pending} className="shrink-0">Send test</Button>
      </div>
      <Alert message={error} />
      {sent && <Alert type="success" message={`Sent to ${sent.to}. Check your WhatsApp.`} />}
    </form>
  );
}
