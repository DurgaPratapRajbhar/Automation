'use client';

import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { fieldClass } from '@/components/ui/Input';
import { sendTranslatedReply, translateReply } from '@/features/shop/inbox/actions';

// Reply in your own language, check the translation, send it in the customer's language.
// `bridge` = { customerLanguageName } (shown only when the customer writes in another language than yours).
export default function LanguageBridgeReply({ conversationId, bridge }) {
  const router = useRouter();
  const [text, setText] = useState('');
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function translate(event) {
    event.preventDefault();
    setError(null);

    startTransition(async () => {
      const response = await translateReply(conversationId, text);

      response.message ? setError(response.message) : setPreview(response.translation);
    });
  }

  function send() {
    setError(null);

    startTransition(async () => {
      const response = await sendTranslatedReply(conversationId, { body: preview.text, original: text, language: preview.to });

      if (response.message) return setError(response.message);

      setText('');
      setPreview(null);
      router.refresh();
    });
  }

  return (
    <form onSubmit={translate} className="space-y-2">
      <p className="text-sm font-medium text-slate-700">
        Reply in your language. It goes to the customer in <strong>{bridge.customerLanguageName}</strong>.
      </p>

      <textarea
        value={text}
        onChange={(event) => { setText(event.target.value); setPreview(null); }}
        rows={3}
        required
        maxLength={4096}
        placeholder="Write your reply"
        className={fieldClass}
      />

      {preview && (
        <div className="space-y-2 rounded-lg bg-emerald-50 p-3 ring-1 ring-emerald-200">
          <p className="text-xs font-semibold text-emerald-800">The customer will get ({preview.to_name}). You can change it.</p>
          <textarea
            value={preview.text}
            onChange={(event) => setPreview({ ...preview, text: event.target.value })}
            rows={3}
            maxLength={4096}
            className={fieldClass}
          />
          <Button type="button" onClick={send} pending={pending}>Send in {preview.to_name}</Button>
        </div>
      )}

      <Alert message={error} />

      {!preview && <Button pending={pending}>Translate to {bridge.customerLanguageName}</Button>}
    </form>
  );
}
