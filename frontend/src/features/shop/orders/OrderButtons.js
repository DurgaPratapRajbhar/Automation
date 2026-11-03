'use client';

import { useState, useTransition } from 'react';
import { updateOrder } from '@/features/shop/orders/actions';

// What the owner can do with an order: delivery steps, paid, cancel
export default function OrderButtons({ order }) {
  const [note, setNote] = useState(null);
  const [pending, startTransition] = useTransition();

  function change(changes, ask) {
    if (ask && !window.confirm(ask)) return;

    startTransition(async () => setNote((await updateOrder(order.id, changes)).message));
  }

  const link = 'text-xs font-medium hover:underline disabled:opacity-60';
  const confirmed = order.status === 'confirmed';
  const delivers = confirmed && !order.booking_at;

  return (
    <div className="flex flex-wrap items-center gap-3">
      {delivers && !order.delivery_status && <button type="button" disabled={pending} onClick={() => change({ delivery_status: 'on_the_way' })} className={`${link} text-sky-700`}>On the way</button>}
      {delivers && order.delivery_status !== 'delivered' && <button type="button" disabled={pending} onClick={() => change({ delivery_status: 'delivered' })} className={`${link} text-emerald-700`}>Delivered</button>}
      {confirmed && <button type="button" disabled={pending} onClick={() => change({ paid: !order.paid })} className={`${link} text-slate-700`}>{order.paid ? 'Mark not paid' : 'Mark paid'}</button>}
      {order.status !== 'cancelled' && <button type="button" disabled={pending} onClick={() => change({ status: 'cancelled' }, 'Cancel this order?')} className={`${link} text-red-600`}>Cancel order</button>}
      {note && <span className="text-xs text-slate-600">{note}</span>}
    </div>
  );
}
