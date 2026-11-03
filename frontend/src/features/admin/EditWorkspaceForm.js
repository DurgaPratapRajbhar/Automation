'use client';

import { useActionState } from 'react';
import { updateWorkspace } from '@/features/admin/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

export default function EditWorkspaceForm({ workspace }) {
  const [state, formAction, pending] = useActionState(updateWorkspace.bind(null, workspace.id), {});
  const errors = state.errors ?? {};

  return (
    <form action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />
      <Input label="Business name" name="workspace_name" defaultValue={state.values?.workspace_name ?? workspace.name} error={errors.workspace_name} required />
      <Button pending={pending}>Save name</Button>
    </form>
  );
}
