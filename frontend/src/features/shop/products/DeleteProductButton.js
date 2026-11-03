'use client';

import { useState, useTransition } from 'react';
import { deleteProduct } from '@/features/shop/products/actions';

export default function DeleteProductButton({ productId, name }) {
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function remove() {
    if (!window.confirm(`Remove ${name}?`)) return;

    startTransition(async () => {
      const response = await deleteProduct(productId);

      if (response.message) setError(response.message);
    });
  }

  return (
    <>
      <button type="button" disabled={pending} onClick={remove} className="text-xs font-medium text-red-600 hover:underline disabled:opacity-60">Remove</button>
      {error && <span className="text-xs text-red-600">{error}</span>}
    </>
  );
}
