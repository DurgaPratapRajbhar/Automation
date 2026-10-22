'use client';

import { useActionState } from 'react';
import Link from 'next/link';
import { register } from '@/features/auth/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

export default function RegisterForm() {
  const [state, formAction, pending] = useActionState(register, {});
  const values = state.values ?? {};
  const errors = state.errors ?? {};

  return (
    <form action={formAction} className="space-y-4">
      <h1 className="text-2xl font-semibold">Create your account</h1>

      <Alert message={state.message} />

      <Input label="Your name" name="name" defaultValue={values.name} error={errors.name} required />
      <Input label="Business name" name="workspace_name" defaultValue={values.workspace_name} error={errors.workspace_name} required />
      <Input label="Email" name="email" type="email" defaultValue={values.email} error={errors.email} required />
      <Input label="Password" name="password" type="password" error={errors.password} required />
      <Input label="Confirm password" name="password_confirmation" type="password" required />

      <Button pending={pending} className="w-full">Create account</Button>

      <p className="text-center text-sm text-slate-600">
        Already registered? <Link href="/login" className="font-medium text-slate-900 underline">Login</Link>
      </p>
    </form>
  );
}
