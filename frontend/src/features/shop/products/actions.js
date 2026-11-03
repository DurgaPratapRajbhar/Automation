'use server';

import { revalidatePath } from 'next/cache';
import { backend } from '@/lib/backend';
import { formError } from '@/lib/forms';

// The form's fields -> what the API wants (an empty stock means "not counted")
function body(formData) {
  return {
    name: formData.get('name'),
    price: formData.get('price') === '' ? null : formData.get('price'),
    unit: formData.get('unit') || null,
    stock: formData.get('stock') === '' ? null : formData.get('stock'),
    duration_minutes: formData.get('duration_minutes') === '' ? null : formData.get('duration_minutes'),
    active: formData.get('active') === 'on',
  };
}

async function save(path, method, formData) {
  const values = body(formData);
  const { ok, data } = await backend(path, { method, body: values });

  if (!ok) return formError(data, values);

  revalidatePath('/products');

  return { message: data.message, success: true };
}

export async function addProduct(previousState, formData) {
  return save('products', 'POST', formData);
}

export async function updateProduct(productId, previousState, formData) {
  return save(`products/${productId}`, 'PUT', formData);
}

export async function deleteProduct(productId) {
  const { ok, data } = await backend(`products/${productId}`, { method: 'DELETE' });

  if (!ok) return { message: data.message };

  revalidatePath('/products');

  return {};
}
