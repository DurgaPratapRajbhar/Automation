import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import AddPromiseForm from '@/features/shop/promises/AddPromiseForm';
import PromiseStatusButtons from '@/features/shop/promises/PromiseStatusButtons';
import { STATUS_TABS, TIMING, formatDate, kindLabel } from '@/features/shop/promises/promiseLabels';
import { getPromises } from '@/features/shop/promises/queries';
import { can } from '@/features/shop/workspace/queries';

export const metadata = { title: 'Promises' };

export default async function PromisesPage({ searchParams }) {
  const { status = 'open', cursor } = await searchParams;
  const { ok, data } = await getPromises({ status, cursor });

  if (!ok) return <Alert message={data.message} />;

  const canEdit = await can('promises.edit');
  const nextPage = data.next_cursor && `/promises?${new URLSearchParams({ status, cursor: data.next_cursor })}`;

  return (
    <div className="space-y-4">
      <PageHeader title="Promises" />

      <p className="max-w-2xl text-sm text-slate-600">
        When a customer writes “kal payment kar dunga” or “Friday ko bhej dunga”, it is noted here with the date, so nothing is forgotten.
      </p>

      <div className="flex gap-2">
        {STATUS_TABS.map((tab) => (
          <Link
            key={tab.value}
            href={`/promises?status=${tab.value}`}
            className={`rounded-full px-3 py-1 text-sm ${status === tab.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300'}`}
          >
            {tab.label}
          </Link>
        ))}
      </div>

      {(await can('promises.create')) && <AddPromiseForm />}

      {data.data.length === 0 ? (
        <p className="text-slate-500">Nothing here.</p>
      ) : (
        <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
          {data.data.map((promise) => (
            <li key={promise.id} className="space-y-1 px-4 py-3">
              <div className="flex items-center justify-between gap-3">
                <p className="truncate font-medium">{promise.customer_name ?? (promise.customer_phone ? `+${promise.customer_phone}` : 'Customer')}</p>
                <span className="flex shrink-0 items-center gap-2 text-sm text-slate-600">
                  {promise.timing && (
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${TIMING[promise.timing].tone}`}>{TIMING[promise.timing].label}</span>
                  )}
                  {formatDate(promise.due_on)}
                </span>
              </div>
              <p className="text-sm text-slate-700">
                {kindLabel(promise.kind)}{promise.amount ? ` · ₹${Number(promise.amount).toLocaleString('en-IN')}` : ''}: “{promise.quote}”
              </p>
              <div className="flex flex-wrap items-center gap-4">
                {promise.conversation_id && (
                  <Link href={`/inbox/${promise.conversation_id}`} className="text-xs font-medium text-slate-600 hover:underline">Open chat</Link>
                )}
                {canEdit && <PromiseStatusButtons promiseId={promise.id} status={promise.status} />}
              </div>
            </li>
          ))}
        </ul>
      )}

      {nextPage && <Link href={nextPage} className="text-sm underline">More</Link>}
    </div>
  );
}
