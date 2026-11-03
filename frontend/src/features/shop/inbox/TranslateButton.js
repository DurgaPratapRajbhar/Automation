'use client';

import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { translateMessage } from '@/features/shop/inbox/actions';

// "Translate" under a message: one click, and the translation stays under it.
export default function TranslateButton({ conversationId, messageId }) {
  const router = useRouter();
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function translate() {
    setError(null);

    startTransition(async () => {
      const response = await translateMessage(conversationId, messageId);

      response.message ? setError(response.message) : router.refresh();
    });
  }

  return (
    <>
      <button type="button" onClick={translate} disabled={pending} className="mt-1 text-xs font-medium text-sky-700 hover:underline disabled:opacity-60">
        {pending ? 'Translating…' : 'Translate'}
      </button>
      {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </>
  );
}
