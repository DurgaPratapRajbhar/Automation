import { getWorkspaceAccess } from '@/features/shop/workspace/queries';

export default async function DashboardPage() {
  const { ok, data } = await getWorkspaceAccess();

  // The layout already shows why (suspended, no access...); the page renders in parallel
  if (!ok) return null;

  const { workspace, role, permissions } = data.data;

  return (
    <div className="space-y-2">
      <h1 className="text-2xl font-semibold">Welcome to {workspace.name}</h1>
      <p className="text-slate-600">
        Your role: {role?.name ?? 'Super admin'} · {permissions.length} permissions
      </p>
    </div>
  );
}
