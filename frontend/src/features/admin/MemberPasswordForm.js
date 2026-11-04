'use client';

import { useActionState } from 'react';
import { resetMemberPassword } from '@/features/admin/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import { PASSWORD_MIN } from '@/lib/forms';

// One user of a shop: type a new password twice. The user is logged out everywhere and must use the new one.
export default function MemberPasswordForm({ workspaceId, member }) {
  const [state, formAction, pending] = useActionState(resetMemberPassword.bind(null, workspaceId, member.id), {});
  const errors = state.errors ?? {};

  return (
    <form action={formAction} className="space-y-3 rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <div>
        <p className="font-medium">{member.name} <span className="text-sm font-normal text-slate-500">· {member.role}</span></p>
        <p className="text-sm text-slate-600">{member.email}</p>
      </div>

      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      <div className="grid gap-3 sm:grid-cols-2">
        <Input label="New password (10+ characters)" name="password" type="password" minLength={PASSWORD_MIN} autoComplete="new-password" error={errors.password} required />
        <Input label="Type it again" name="password_confirmation" type="password" minLength={PASSWORD_MIN} autoComplete="new-password" required />
      </div>

      <Button pending={pending} variant="secondary">Set new password</Button>
    </form>
  );
}
