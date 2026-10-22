'use client';

import { useActionState } from 'react';
import { addMember } from '@/features/shop/members/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';

export default function AddMemberForm({ roles }) {
  const [state, formAction, pending] = useActionState(addMember, {});
  const values = state.values ?? {};
  const errors = state.errors ?? {};

  return (
    <form action={formAction} className="mb-8 space-y-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <h2 className="font-semibold">Add member</h2>
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      <div className="grid gap-4 sm:grid-cols-2">
        <Input label="Email" name="email" type="email" defaultValue={values.email} error={errors.email} required />
        <Select label="Role" name="role_id" options={roles} defaultValue={values.role_id} error={errors.role_id} />
        <Input label="Name (new user only)" name="name" defaultValue={values.name} error={errors.name} />
        <Input label="Password (new user only)" name="password" type="password" error={errors.password} />
      </div>

      <Button pending={pending}>Add member</Button>
    </form>
  );
}
