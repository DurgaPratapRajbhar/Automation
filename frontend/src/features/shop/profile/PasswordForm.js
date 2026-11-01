'use client';

import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import { changePassword } from '@/features/shop/profile/passwordActions';

// hasPassword = false: the account was made with Google, so there is no current password to ask for
export default function PasswordForm({ hasPassword }) {
  const [state, formAction, pending] = useActionState(changePassword, {});
  const errors = state.errors ?? {};

  return (
    <form key={state.success ? 'done' : 'edit'} action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      {hasPassword ? (
        <Input label="Current password" name="current_password" type="password" autoComplete="current-password" error={errors.current_password} required />
      ) : (
        <p className="text-sm text-slate-600">You signed in with Google. You can also set a password, to log in with your email.</p>
      )}
      <Input label="New password (10+ characters)" name="password" type="password" autoComplete="new-password" error={errors.password} required />
      <Input label="Type it again" name="password_confirmation" type="password" autoComplete="new-password" required />

      <Button pending={pending}>Change password</Button>
    </form>
  );
}
