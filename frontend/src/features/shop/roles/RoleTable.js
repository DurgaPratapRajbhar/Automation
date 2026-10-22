import Link from 'next/link';

export default function RoleTable({ roles }) {
  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            <th className="px-4 py-3">Role</th>
            <th className="px-4 py-3">Members</th>
            <th className="px-4 py-3">Permissions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {roles.map((role) => (
            <tr key={role.id}>
              <td className="px-4 py-3">
                {role.is_system ? (
                  <span className="font-medium">{role.name} <span className="text-xs text-slate-500">(system)</span></span>
                ) : (
                  <Link href={`/roles/${role.id}`} className="font-medium hover:underline">{role.name}</Link>
                )}
              </td>
              <td className="px-4 py-3">{role.members_count}</td>
              <td className="px-4 py-3">{role.is_owner ? 'All' : role.permissions.length}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
