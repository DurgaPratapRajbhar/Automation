'use client';

import Link from 'next/link';
import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import GoogleButton from '@/features/auth/GoogleButton';
import { signup } from '@/features/auth/actions';

const SITE_URL = 'https://scriptdp.com';

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
      {googleClientId && (
        <p className="text-center text-xs text-slate-400">
          By continuing with Google you agree to the Terms of Service and Privacy Policy below.
        </p>
      )}
      {googleClientId && <p className="text-center text-xs uppercase tracking-wide text-slate-400">or with your email</p>}

      <form action={formAction} className="space-y-4">
        <Alert message={state.message} />

        <Input label="Your name" name="name" autoComplete="name" defaultValue={values.name} error={errors.name} required />
        <Input label="Shop name" name="shop_name" defaultValue={values.shop_name} error={errors.shop_name} required />
        <Input label="Email" name="email" type="email" autoComplete="email" defaultValue={values.email} error={errors.email} required />
        <Input label="Password (10+ characters)" name="password" type="password" autoComplete="new-password" error={errors.password} required />
        <Input label="Type the password again" name="password_confirmation" type="password" autoComplete="new-password" required />

        <div className="space-y-1">
          <label className="flex items-start gap-2 text-sm text-slate-600">
            <input type="checkbox" name="accepted_terms" required className="mt-1" />
            <span>
              I agree to the <a href={`${SITE_URL}/en/terms`} target="_blank" rel="noopener noreferrer" className="underline">Terms of Service</a> and{' '}
              <a href={`${SITE_URL}/en/privacy`} target="_blank" rel="noopener noreferrer" className="underline">Privacy Policy</a>.
            </span>
          </label>
          {errors.accepted_terms && <p className="text-sm text-red-600">{errors.accepted_terms[0]}</p>}
        </div>

        <Button pending={pending} className="w-full">Create my account</Button>
      </form>

      <p className="text-center text-sm text-slate-500">
        Already have an account? <Link href="/login" className="font-medium text-slate-900 underline">Log in</Link>
      </p>
    </div>
  );
}
