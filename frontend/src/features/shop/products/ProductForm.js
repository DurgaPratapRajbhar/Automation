'use client';

import { useActionState, useEffect, useRef } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

// Add (no `product`) or edit one product
export default function ProductForm({ action, product, resetOnSave = false }) {
  const [state, formAction, pending] = useActionState(action, {});
  const formRef = useRef(null);
  const errors = state.errors ?? {};
  const values = { ...product, ...state.values };

  useEffect(() => {
    if (state.success && resetOnSave) formRef.current?.reset();
  }, [state, resetOnSave]);

  return (
    <form ref={formRef} action={formAction} className="max-w-lg space-y-3">
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />
      <Input label="Name" name="name" defaultValue={values.name} error={errors.name} maxLength={255} required />
      <div className="grid gap-3 sm:grid-cols-3">
        <Input label="Price (₹)" name="price" type="number" min="0" step="0.01" defaultValue={values.price} error={errors.price} required />
        <Input label="Unit" name="unit" defaultValue={values.unit} error={errors.unit} placeholder="piece, kg, hour" maxLength={30} />
        <Input label="In stock" name="stock" type="number" min="0" step="1" defaultValue={values.stock} error={errors.stock} placeholder="Not counted" />
      </div>
      <Input
        label="Takes (minutes), only for a service customers book for a time"
        name="duration_minutes"
        type="number"
        min="5"
        max="1440"
        step="5"
        defaultValue={values.duration_minutes}
        error={errors.duration_minutes}
        placeholder="For example 30"
      />
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" name="active" defaultChecked={values.active ?? true} className="h-4 w-4" />
        Selling now
      </label>
      <Button pending={pending}>{product ? 'Save' : 'Add product'}</Button>
    </form>
  );
}
