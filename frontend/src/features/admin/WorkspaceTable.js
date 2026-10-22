import ActionButton from '@/components/ui/ActionButton';
import { setWorkspaceStatus } from '@/features/admin/actions';

export default function WorkspaceTable({ workspaces }) {
  if (workspaces.length === 0) {
    return <p className="text-slate-500">No workspaces found.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            <th className="px-4 py-3">Workspace</th>
            <th className="px-4 py-3">Members</th>
            <th className="px-4 py-3">Status</th>
            <th className="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {workspaces.map((workspace) => {
            const active = workspace.status === 'active';

            return (
              <tr key={workspace.id}>
                <td className="px-4 py-3 font-medium">{workspace.name}</td>
                <td className="px-4 py-3">{workspace.members_count}</td>
                <td className="px-4 py-3">
                  <span className={active ? 'text-green-700' : 'text-red-700'}>{workspace.status}</span>
                </td>
                <td className="px-4 py-3 text-right">
                  <ActionButton
                    action={setWorkspaceStatus.bind(null, workspace.id, active ? 'suspended' : 'active')}
                    label={active ? 'Suspend' : 'Activate'}
                    variant={active ? 'danger' : 'secondary'}
                    confirm={active ? `Suspend ${workspace.name}? Its users will be blocked.` : null}
                  />
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
