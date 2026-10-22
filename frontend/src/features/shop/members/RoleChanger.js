'use client';

import { useActionState } from 'react';
import { changeMemberRole } from '@/features/shop/members/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Select from '@/components/ui/Select';

// Role dropdown + save for one member row
export default function RoleChanger({ userId, roleId, roles }) {
  const [state, formAction, pending] = useActionState(changeMemberRole.bind(null, userId), {});

  return (
    <form action={formAction} className="space-y-1">
      <div className="flex gap-2">
        <Select name="role_id" options={roles} defaultValue={roleId} />
        <Button variant="secondary" pending={pending}>Save</Button>
      </div>
      <Alert message={state.message} />
    </form>
  );
}
