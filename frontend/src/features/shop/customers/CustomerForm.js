'use client';

import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

export default function CustomerForm({ action, customer = {} }) {
  const [state, formAction, pending] = useActionState(action, {});
  const values = { ...customer, ...state.values };
  const errors = state.errors ?? {};
  const optIn = values.whatsapp_opt_in === true || values.whatsapp_opt_in === '1';

  return (
    <form action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} />

      <Input label="Name" name="name" defaultValue={values.name} error={errors.name} required />
      <Input label="Phone" name="phone" defaultValue={values.phone} error={errors.phone} placeholder="9876543210" />
      <Input label="Address" name="address" defaultValue={values.address} error={errors.address} />

      <div className="grid gap-4 sm:grid-cols-2">
        <Input label="GSTIN" name="gstin" defaultValue={values.gstin} error={errors.gstin} />
        <Input label="State code" name="state_code" defaultValue={values.state_code} error={errors.state_code} placeholder="09" />
      </div>

      <Input label="Credit limit (₹)" name="credit_limit" type="number" step="0.01" min="0" defaultValue={values.credit_limit} error={errors.credit_limit} />

      <label className="flex items-center gap-2 text-sm">
        {/* Hidden 0 is sent when the box is unchecked */}
        <input type="hidden" name="whatsapp_opt_in" value="0" />
        <input type="checkbox" name="whatsapp_opt_in" value="1" defaultChecked={optIn} />
        Customer agreed to WhatsApp messages
      </label>

      <Button pending={pending}>Save</Button>
    </form>
  );
}
