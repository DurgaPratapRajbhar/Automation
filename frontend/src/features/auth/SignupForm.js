'use client';

import Link from 'next/link';
import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import GoogleButton from '@/features/auth/GoogleButton';
import { signup } from '@/features/auth/actions';

export default function SignupForm({ googleClientId, trialDays }) {
  const [state, formAction, pending] = useActionState(signup, {});
  const values = state.values ?? {};
  const errors = state.errors ?? {};

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">Start your free trial</h1>
        <p className="mt-1 text-sm text-slate-600">{trialDays} days free. No card needed.</p>
      </div>

      <GoogleButton clientId={googleClientId} text="signup_with" />
      {googleClientId && <p className="text-center text-xs uppercase tracking-wide text-slate-400">or with your email</p>}

      <form action={formAction} className="space-y-4">
        <Alert message={state.message} />

        <Input label="Your name" name="name" autoComplete="name" defaultValue={values.name} error={errors.name} required />
        <Input label="Shop name" name="shop_name" defaultValue={values.shop_name} error={errors.shop_name} required />
        <Input label="Email" name="email" type="email" autoComplete="email" defaultValue={values.email} error={errors.email} required />
        <Input label="Password (10+ characters)" name="password" type="password" autoComplete="new-password" error={errors.password} required />
        <Input label="Type the password again" name="password_confirmation" type="password" autoComplete="new-password" required />

        <Button pending={pending} className="w-full">Create my account</Button>
      </form>

      <p className="text-center text-sm text-slate-500">
        Already have an account? <Link href="/login" className="font-medium text-slate-900 underline">Log in</Link>
      </p>
    </div>
  );
}
