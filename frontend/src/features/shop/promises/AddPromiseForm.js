'use client';

import { useActionState, useEffect, useRef } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';
import { addPromise } from '@/features/shop/promises/actions';
import { KINDS } from '@/features/shop/promises/promiseLabels';

// The owner notes a promise by hand ("Ramesh will pay 5000 on Friday")
export default function AddPromiseForm() {
  const [state, formAction, pending] = useActionState(addPromise, {});
  const formRef = useRef(null);
  const errors = state.errors ?? {};

  useEffect(() => {
    if (state.success) formRef.current?.reset();
  }, [state]);

  return (
    <details className="rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <summary className="cursor-pointer text-sm font-medium">Add a promise yourself</summary>

      <form ref={formRef} action={formAction} className="mt-4 max-w-lg space-y-3">
        <Alert message={state.message} type={state.success ? 'success' : 'error'} />
        <Input label="Customer name" name="customer_name" error={errors.customer_name} maxLength={255} />
        <Input label="Customer WhatsApp number" name="customer_phone" error={errors.customer_phone} placeholder="919876543210" />
        <Select label="What will they do?" name="kind" options={KINDS} defaultValue="payment" error={errors.kind} />
        <Input label="What did they say?" name="quote" error={errors.quote} placeholder="Will pay on Friday" maxLength={500} required />
        <Input label="Amount (optional)" name="amount" type="number" min="1" step="0.01" error={errors.amount} />
        <Input label="Date" name="due_on" type="date" error={errors.due_on} required />
        <Button pending={pending}>Save promise</Button>
      </form>
    </details>
  );
}
