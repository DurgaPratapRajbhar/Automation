import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import ApprovalButtons from '@/features/shop/approvals/ApprovalButtons';
import { AGENTS, STATUS_TABS } from '@/features/shop/approvals/approvalLabels';
import { getActions } from '@/features/shop/approvals/queries';
import { formatTime } from '@/features/shop/inbox/format';
import { can } from '@/features/shop/workspace/queries';

export const metadata = { title: 'Approvals' };

export default async function ApprovalsPage({ searchParams }) {
  const { status = 'proposed', cursor } = await searchParams;
  const { ok, data } = await getActions({ status, cursor });

  if (!ok) return <Alert message={data.message} />;

  const canDecide = await can('actions.edit');
  const nextPage = data.next_cursor && `/approvals?${new URLSearchParams({ status, cursor: data.next_cursor })}`;

  return (
    <div className="space-y-4">
      <PageHeader title="Approvals" />

      <p className="max-w-2xl text-sm text-slate-600">
        Your assistants never act on their own. When one wants to send a reminder, confirm an order or send a report, it waits here for your Yes.
      </p>

      <div className="flex flex-wrap gap-2">
        {STATUS_TABS.map((tab) => (
          <Link
            key={tab.value}
            href={`/approvals?status=${tab.value}`}
            className={`rounded-full px-3 py-1 text-sm ${status === tab.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300'}`}
          >
            {tab.label}
          </Link>
        ))}
      </div>

      {data.data.length === 0 ? (
        <p className="text-slate-500">Nothing here.</p>
      ) : (
        <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
          {data.data.map((action) => (
            <li key={action.id} className="space-y-2 px-4 py-3">
              <div className="flex items-center justify-between gap-3 text-xs text-slate-500">
                <span className="font-semibold">{AGENTS[action.agent] ?? action.agent}</span>
                <span>{formatTime(action.created_at)}</span>
              </div>
              <p className="text-sm font-medium">{action.summary}</p>
              {action.error && <p className="rounded bg-red-50 px-2 py-1 text-sm text-red-700">{action.error}</p>}
              <div className="flex flex-wrap items-center gap-4">
                {action.conversation_id && <Link href={`/inbox/${action.conversation_id}`} className="text-xs font-medium text-slate-600 hover:underline">Open chat</Link>}
                {canDecide && ['proposed', 'escalated'].includes(action.status) && <ApprovalButtons actionId={action.id} problem={action.status === 'escalated'} />}
              </div>
            </li>
          ))}
        </ul>
      )}

      {nextPage && <Link href={nextPage} className="text-sm underline">More</Link>}
    </div>
  );
}
