'use server';

import { redirect } from 'next/navigation';
import { backend } from '@/lib/backend';
import { formError, formValues } from '@/lib/forms';

// customerId = null creates, a number updates
export async function saveCustomer(customerId, previousState, formData) {
  const values = formValues(formData);
  const { ok, data } = await backend(customerId ? `customers/${customerId}` : 'customers', {
    method: customerId ? 'PUT' : 'POST',
    body: values,
  });

  if (!ok) return formError(data, values);

  redirect('/customers');
}

export async function deleteCustomer(customerId) {
  const { ok, data } = await backend(`customers/${customerId}`, { method: 'DELETE' });

  if (!ok) return formError(data);

  redirect('/customers');
}
