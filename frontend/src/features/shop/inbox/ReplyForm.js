'use client';

import { useActionState, useEffect, useRef } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';

export default function ReplyForm({ action }) {
  const [state, formAction, pending] = useActionState(action, {});
  const formRef = useRef(null);

  // Clear the box after a successful send
  useEffect(() => {
    if (state.sent) formRef.current?.reset();
  }, [state.sent]);

  return (
    <form ref={formRef} action={formAction} className="space-y-2">
      <Alert message={state.message} />
      <label htmlFor="reply" className="sr-only">Reply</label>
      <textarea
        id="reply"
        name="body"
        rows={3}
        required
        maxLength={4096}
        defaultValue={state.values?.body ?? ''}
        placeholder="Type a reply. It goes to the customer on WhatsApp."
        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
      />
      {state.errors?.body && <p className="text-sm text-red-600">{state.errors.body[0]}</p>}
      <Button pending={pending}>Send</Button>
    </form>
  );
}
