import Link from 'next/link';
import { MENU } from '@/components/shop/menu';
import Icon from '@/components/ui/Icon';
import DailySummaryCard from '@/features/shop/promises/DailySummaryCard';
import { getOverview, getSummary } from '@/features/shop/promises/queries';
import { getWorkspaceAccess } from '@/features/shop/workspace/queries';

export default async function DashboardPage() {
  const { ok, data } = await getWorkspaceAccess();

  // The layout already shows why (suspended, no access...); the page renders in parallel
  if (!ok) return null;

  const { workspace, role, permissions } = data.data;
  // Shortcut cards for every page this user may open
  const [summary, overview] = await Promise.all([
    permissions.includes('promises.view') ? getSummary() : null,
    permissions.includes('overview.view') ? getOverview() : null,
  ]);
  const shortcuts = MENU.filter((item) => item.hint && (!item.permission || permissions.includes(item.permission)));

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Welcome to {workspace.name}</h1>
        <p className="mt-1 text-slate-600">You are signed in as {role?.name ?? 'Super admin'}. Pick where you want to go.</p>
      </div>

      {summary?.ok && <DailySummaryCard summary={summary.data.data} overview={overview?.ok ? overview.data.data : null} />}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {shortcuts.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            className="group flex items-start gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md hover:ring-emerald-300"
          >
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
              <Icon name={item.icon} size={22} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block font-semibold">{item.label}</span>
              <span className="mt-0.5 block text-sm text-slate-600">{item.hint}</span>
            </span>
            <Icon name="arrow" size={18} className="mt-1 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-emerald-600" />
          </Link>
        ))}
      </div>
    </div>
  );
}
