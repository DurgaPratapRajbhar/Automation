'use client';

import { useActionState } from 'react';
import { createWorkspace } from '@/features/admin/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

export default function WorkspaceForm() {
  const [state, formAction, pending] = useActionState(createWorkspace, {});
  const values = state.values ?? {};
  const errors = state.errors ?? {};

  return (
    <form action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} />

      <Input label="Shop name" name="workspace_name" defaultValue={values.workspace_name} error={errors.workspace_name} required />

      <fieldset className="space-y-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <legend className="px-1 text-sm font-semibold">Shop owner login</legend>
        <Input label="Owner email" name="owner_email" type="email" defaultValue={values.owner_email} error={errors.owner_email} required />
        <Input label="Owner name (new user only)" name="owner_name" defaultValue={values.owner_name} error={errors.owner_name} />
        <Input label="Owner password (new user only)" name="owner_password" type="password" error={errors.owner_password} />
      </fieldset>

      <Button pending={pending}>Create shop</Button>
    </form>
  );
}
