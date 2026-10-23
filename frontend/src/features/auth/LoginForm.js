'use client';

import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

// Used by the shop login and the super admin login; each passes its own action
export default function LoginForm({ action, title }) {
  const [state, formAction, pending] = useActionState(action, {});

  return (
    <form action={formAction} className="space-y-4">
      <h1 className="text-2xl font-semibold">{title}</h1>

      <Alert message={state.message} />

      <Input label="Email" name="email" type="email" defaultValue={state.values?.email} error={state.errors?.email} required />
      <Input label="Password" name="password" type="password" error={state.errors?.password} required />

      <Button pending={pending} className="w-full">Login</Button>

      <p className="text-center text-sm text-slate-500">Accounts are created by your shop owner or the platform admin.</p>
    </form>
  );
}
