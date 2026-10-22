'use client';

import { useActionState } from 'react';
import Link from 'next/link';
import { login } from '@/features/auth/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

export default function LoginForm() {
  const [state, formAction, pending] = useActionState(login, {});

  return (
    <form action={formAction} className="space-y-4">
      <h1 className="text-2xl font-semibold">Login</h1>

      <Alert message={state.message} />

      <Input label="Email" name="email" type="email" defaultValue={state.values?.email} error={state.errors?.email} required />
      <Input label="Password" name="password" type="password" error={state.errors?.password} required />

      <Button pending={pending} className="w-full">Login</Button>

      <p className="text-center text-sm text-slate-600">
        New here? <Link href="/register" className="font-medium text-slate-900 underline">Create an account</Link>
      </p>
    </form>
  );
}
