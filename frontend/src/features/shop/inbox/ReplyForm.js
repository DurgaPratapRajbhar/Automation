'use client';

import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { fieldClass } from '@/components/ui/Input';
import { sendReply, translateReply } from '@/features/shop/inbox/actions';

// Type a reply. Send it as written, or pick a language: it is translated, you check it, then it is sent.
// `languages` = { code: name }; `defaultLanguage` = the customer's language when it is known and not yours.
export default function ReplyForm({ conversationId, languages, defaultLanguage }) {
  const router = useRouter();
  const [text, setText] = useState('');
  const [language, setLanguage] = useState(defaultLanguage ?? '');
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  const translating = language !== '' && !preview;

  // A changed text or language needs a new translation
  function change(setter) {
    return (event) => {
      setter(event.target.value);
      setPreview(null);
    };
  }

  function submit(event) {
    event.preventDefault();
    setError(null);

    startTransition(async () => {
      if (translating) {
        const response = await translateReply(conversationId, text, language);

        return response.message ? setError(response.message) : setPreview(response.translation);
      }

      const response = await sendReply(conversationId, preview
        ? { body: preview.text, original: text, language: preview.to }
        : { body: text });

      if (response.message) return setError(response.message);

      setText('');
      setPreview(null);
      router.refresh();
    });
  }

  return (
    <form onSubmit={submit} className="space-y-2">
      <label htmlFor="reply" className="sr-only">Reply</label>
      <textarea
        id="reply"
        value={text}
        onChange={change(setText)}
        rows={3}
        required
        maxLength={4096}
        placeholder="Type a reply. It goes to the customer on WhatsApp."
        className={fieldClass}
      />

      <div className="flex flex-wrap items-center gap-2">
        <label htmlFor="reply-language" className="text-sm text-slate-600">Send in</label>
        <select id="reply-language" value={language} onChange={change(setLanguage)} className={`${fieldClass} w-auto`}>
          <option value="">The language I wrote</option>
          {Object.entries(languages).map(([code, name]) => (
            <option key={code} value={code}>{name}</option>
          ))}
        </select>
      </div>

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
        </div>
      )}

      <Alert message={error} />

      <Button pending={pending}>{translating ? `Translate to ${languages[language]}` : 'Send'}</Button>
    </form>
  );
}
