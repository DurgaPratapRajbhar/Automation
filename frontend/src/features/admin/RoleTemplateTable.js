import Link from 'next/link';

export default function RoleTemplateTable({ templates }) {
  if (templates.length === 0) {
    return <p className="text-slate-500">No role templates yet. New workspaces get only the Owner role.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            <th className="px-4 py-3">Role</th>
            <th className="px-4 py-3">Permissions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {templates.map((template) => (
            <tr key={template.id}>
              <td className="px-4 py-3">
                <Link href={`/admin/role-templates/${template.id}`} className="font-medium hover:underline">{template.name}</Link>
              </td>
              <td className="px-4 py-3">{template.permissions.length}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
