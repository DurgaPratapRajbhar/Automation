'use client';

import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';

// One-click server action (delete, suspend...). Asks first when `confirm` text is given.
export default function ActionButton({ action, label, confirm, variant = 'danger' }) {
  const [state, formAction, pending] = useActionState(action, {});

  return (
    <form
      action={formAction}
      onSubmit={(event) => confirm && !window.confirm(confirm) && event.preventDefault()}
      className="inline-block space-y-2"
    >
      <Button variant={variant} pending={pending}>{label}</Button>
      <Alert message={state.message} />
    </form>
  );
}
