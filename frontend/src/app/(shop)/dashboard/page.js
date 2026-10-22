import { getWorkspaceAccess } from '@/features/shop/workspace/queries';

export default async function DashboardPage() {
  const { data } = await getWorkspaceAccess();
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
