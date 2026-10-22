'use client';

import { useActionState } from 'react';
import { saveProfile } from '@/features/shop/profile/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';

const GST_STATUSES = [
  { value: 'unregistered', label: 'Not registered' },
  { value: 'composition', label: 'Composition' },
  { value: 'regular', label: 'Regular' },
];

export default function ProfileForm({ profile, canEdit }) {
  const [state, formAction, pending] = useActionState(saveProfile, {});
  const values = { ...profile, ...state.values };
  const errors = state.errors ?? {};

  return (
    <form action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      <fieldset disabled={!canEdit} className="space-y-4">
        <Input label="Shop name" name="name" defaultValue={values.name} error={errors.name} required />
        <Input label="Phone" name="phone" defaultValue={values.phone} error={errors.phone} />
        <Input label="Address" name="address" defaultValue={values.address} error={errors.address} />

        <div className="grid gap-4 sm:grid-cols-2">
          <Select label="GST" name="gst_status" options={GST_STATUSES} defaultValue={values.gst_status} error={errors.gst_status} />
          <Input label="GSTIN" name="gstin" defaultValue={values.gstin} error={errors.gstin} />
          <Input label="State code" name="state_code" defaultValue={values.state_code} error={errors.state_code} placeholder="09" />
          <Input label="UPI ID" name="upi_id" defaultValue={values.upi_id} error={errors.upi_id} placeholder="shop@okaxis" />
        </div>
      </fieldset>

      {canEdit && <Button pending={pending}>Save</Button>}
    </form>
  );
}
