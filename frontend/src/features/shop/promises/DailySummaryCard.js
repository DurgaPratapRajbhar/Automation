import Link from 'next/link';

// "Today at a glance" on the Dashboard: only the numbers that are not zero are worth a look
const WORKFLOWS = {
  assistant: 'AI assistant on WhatsApp',
  orders: 'Order and booking confirmation',
  follow_ups: 'Booking reminders and feedback',
  leads: 'Questions to new customers',
  campaign: 'Marketing campaign',
};

const STATES = {
  active: ['Active', 'bg-green-100 text-green-700'],
  needs_ok: ['Needs your OK', 'bg-amber-100 text-amber-800'],
  off: ['Not set up', 'bg-slate-100 text-slate-600'],
};

export default function DailySummaryCard({ summary, overview }) {
  const items = [
    { label: 'Waiting for your OK', value: summary.needs_ok, href: '/approvals', tone: 'text-sky-700' },
    { label: 'Promises late', value: summary.overdue, href: '/promises', tone: 'text-red-600' },
    { label: 'Promises due today', value: summary.today, href: '/promises', tone: 'text-amber-700' },
    { label: 'Hot leads', value: summary.hot_leads, href: '/inbox', tone: 'text-red-600' },
    { label: 'Chats waiting for you', value: summary.unread, href: '/inbox', tone: 'text-emerald-700' },
    { label: 'Problems to check', value: summary.escalated, href: '/approvals?status=escalated', tone: 'text-red-600' },
  ];
  const nothing = items.every((item) => item.value === 0);

  return (
    <section className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <h2 className="font-semibold">Today at a glance</h2>

      {nothing ? (
        <p className="mt-2 text-sm text-slate-600">All clear. Nothing needs you right now.</p>
      ) : (
        <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
          {items.map((item) => (
            <li key={item.label}>
              <Link href={item.href} className="block rounded-lg p-2 hover:bg-slate-50">
                <span className={`block text-2xl font-semibold ${item.value ? item.tone : 'text-slate-300'}`}>{item.value}</span>
                <span className="text-sm text-slate-600">{item.label}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}

      {overview && (<>
      <ul className="mt-4 grid grid-cols-3 gap-3 border-t border-slate-100 pt-3 text-center">
        <li><span className="block text-xl font-semibold">{overview.stats.customers}</span><span className="text-xs text-slate-600">Customers</span></li>
        <li><span className="block text-xl font-semibold">{overview.stats.campaign_messages}</span><span className="text-xs text-slate-600">Campaign messages (30 days)</span></li>
        <li><span className="block text-xl font-semibold">{overview.stats.orders_this_month}</span><span className="text-xs text-slate-600">Orders and bookings (this month)</span></li>
      </ul>

      <ul className="mt-4 divide-y divide-slate-100 border-t border-slate-100">
        {overview.workflows.map((workflow) => {
          const [label, tone] = STATES[workflow.state];

          return (
            <li key={workflow.key}>
              <Link href={workflow.href} className="flex items-center justify-between gap-3 py-2 text-sm hover:bg-slate-50">
                <span>{WORKFLOWS[workflow.key]}</span>
                <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>
              </Link>
            </li>
          );
        })}
      </ul>
      </>)}
    </section>
  );
}
