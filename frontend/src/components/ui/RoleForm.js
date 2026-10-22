'use client';

import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import PermissionTree from '@/components/ui/PermissionTree';

// Name + permission tree. Used for workspace roles and for super admin role templates.
export default function RoleForm({ action, tree, role = {} }) {
  const [state, formAction, pending] = useActionState(action, {});

  return (
    <form action={formAction} className="max-w-3xl space-y-4">
      <Alert message={state.message} />

      <Input label="Role name" name="name" defaultValue={state.values?.name ?? role.name} error={state.errors?.name} required />

      <PermissionTree tree={tree} selected={role.permissions} />

      <Button pending={pending}>Save</Button>
    </form>
  );
}
