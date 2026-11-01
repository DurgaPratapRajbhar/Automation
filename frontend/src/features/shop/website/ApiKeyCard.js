'use client';

import { useActionState } from 'react';
import { createApiKey, revokeApiKey } from '@/features/shop/website/actions';
import ActionButton from '@/components/ui/ActionButton';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';

// Key for the shop's own website chatbot: the same AI and Knowledge as WhatsApp
export default function ApiKeyCard({ status, canCreate, canDelete }) {
  const [state, formAction, pending] = useActionState(createApiKey, {});
  const hasKey = status.has_key || Boolean(state.key);

  return (
    <section className="max-w-lg space-y-3 rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <h2 className="font-semibold">Website chatbot</h2>
      <p className="text-sm text-slate-600">
        Your website can answer visitors with the same AI and Knowledge as WhatsApp. Send questions to{' '}
        <code className="rounded bg-slate-100 px-1">POST /api/v1/ask</code> with this key in the{' '}
        <code className="rounded bg-slate-100 px-1">X-Api-Key</code> header.
      </p>

      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      {state.key ? (
        <p className="break-all rounded-lg bg-amber-50 p-3 font-mono text-sm ring-1 ring-amber-200">{state.key}</p>
      ) : (
        <p className="text-sm">{hasKey ? `Key in use: ••••${status.hint}` : 'No key yet.'}</p>
      )}

      <div className="flex flex-wrap gap-2">
        {canCreate && (
          <form
            action={formAction}
            onSubmit={(event) =>
              hasKey && !window.confirm('A new key stops the old one at once. Continue?') && event.preventDefault()
            }
          >
            <Button pending={pending}>{hasKey ? 'Make a new key' : 'Create key'}</Button>
          </form>
        )}
        {canDelete && hasKey && (
          <ActionButton action={revokeApiKey} label="Remove key" confirm="Websites using this key will stop getting answers. Remove it?" />
        )}
      </div>
    </section>
  );
}
