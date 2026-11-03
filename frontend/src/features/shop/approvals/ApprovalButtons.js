'use client';

import { useState, useTransition } from 'react';
import { decideAction } from '@/features/shop/approvals/actions';

// Yes / No on something an assistant wants to do. Nothing happens before Yes.
// `problem`: it failed after a Yes; only "Dismiss" is left (it is closed, and what was prepared is undone).
export default function ApprovalButtons({ actionId, problem = false }) {
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function decide(decision) {
    setError(null);
    startTransition(async () => {
      const response = await decideAction(actionId, decision);

      if (response.message) setError(response.message);
    });
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      {!problem && <button type="button" disabled={pending} onClick={() => decide('approve')} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60">
        {pending ? 'Please wait...' : 'Yes, do it'}
      </button>}
      <button type="button" disabled={pending} onClick={() => decide('reject')} className="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100 disabled:opacity-60">
        {problem ? 'Dismiss' : 'No'}
      </button>
      {error && <span className="text-sm text-red-600">{error}</span>}
    </div>
  );
}
