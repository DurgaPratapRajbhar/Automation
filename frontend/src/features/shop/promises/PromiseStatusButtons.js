'use client';

import { useState, useTransition } from 'react';
import { setPromiseStatus } from '@/features/shop/promises/actions';

// Done / Not coming on an open promise; Open again on a closed one
export default function PromiseStatusButtons({ promiseId, status }) {
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function change(next) {
    setError(null);
    startTransition(async () => {
      const response = await setPromiseStatus(promiseId, next);

      if (response.message) setError(response.message);
    });
  }

  const link = 'text-xs font-medium hover:underline disabled:opacity-60';

  return (
    <div className="flex flex-wrap items-center gap-3">
      {status === 'open' ? (
        <>
          <button type="button" disabled={pending} onClick={() => change('done')} className={`${link} text-emerald-700`}>Done</button>
          <button type="button" disabled={pending} onClick={() => change('missed')} className={`${link} text-red-600`}>Not coming</button>
        </>
      ) : (
        <button type="button" disabled={pending} onClick={() => change('open')} className={`${link} text-sky-700`}>Open again</button>
      )}
      {error && <span className="text-xs text-red-600">{error}</span>}
    </div>
  );
}
