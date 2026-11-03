import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import OrderButtons from '@/features/shop/orders/OrderButtons';
import { formatTime } from '@/features/shop/inbox/format';
import { STATUS_LOOK, STATUS_TABS } from '@/features/shop/orders/orderLabels';
import { getOrders } from '@/features/shop/orders/queries';

export const metadata = { title: 'Orders' };

export default async function OrdersPage({ searchParams }) {
  const { status = 'all', cursor } = await searchParams;
  const { ok, data } = await getOrders({ status, cursor });

  if (!ok) return <Alert message={data.message} />;

  const nextPage = data.next_cursor && `/orders?${new URLSearchParams({ status, cursor: data.next_cursor })}`;

  return (
    <div className="space-y-4">
      <PageHeader title="Orders" />

      <p className="max-w-2xl text-sm text-slate-600">
        Orders and bookings your assistant took in chats. A new one waits for your Yes in Approvals; after that the customer is told and the stock is updated.
      </p>

      <div className="flex flex-wrap gap-2">
        {STATUS_TABS.map((tab) => (
          <Link
            key={tab.value}
            href={`/orders?status=${tab.value}`}
            className={`rounded-full px-3 py-1 text-sm ${status === tab.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300'}`}
          >
            {tab.label}
          </Link>
        ))}
      </div>

      {data.data.length === 0 ? (
        <p className="text-slate-500">No orders here yet.</p>
      ) : (
        <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
          {data.data.map((order) => {
            const [label, tone] = STATUS_LOOK[order.status] ?? [order.status, 'bg-slate-100 text-slate-600'];

            return (
              <li key={order.id} className="space-y-1 px-4 py-3">
                <div className="flex items-center justify-between gap-3">
                  <p className="truncate font-medium">{order.customer_name ?? (order.customer_phone ? `+${order.customer_phone}` : 'Customer')}</p>
                  <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>
                </div>
                <p className="text-sm text-slate-700">{order.items.map((item) => `${item.quantity} × ${item.name}`).join(', ')}</p>
                {order.address && <p className="text-sm text-slate-600">Deliver to: {order.address}</p>}
                {order.booking_at && (
                  <p className="text-sm text-slate-600">Booking: {new Date(order.booking_at).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })}</p>
                )}
                <div className="flex items-center justify-between gap-3 text-sm">
                  <span className="font-semibold">₹{Number(order.total).toLocaleString('en-IN')}</span>
                  <span className="text-xs text-slate-500">{formatTime(order.created_at)}</span>
                </div>
                {order.delivery_status && <p className="text-sm text-slate-600">{order.delivery_status === 'delivered' ? 'Delivered' : 'On the way'}</p>}
                {order.status === 'confirmed' && <p className="text-sm text-slate-600">{order.paid ? 'Paid' : 'Not paid yet'}</p>}
                <OrderButtons order={order} />
                {order.conversation_id && <Link href={`/inbox/${order.conversation_id}`} className="text-xs font-medium text-slate-600 hover:underline">Open chat</Link>}
              </li>
            );
          })}
        </ul>
      )}

      {nextPage && <Link href={nextPage} className="text-sm underline">More</Link>}
    </div>
  );
}
