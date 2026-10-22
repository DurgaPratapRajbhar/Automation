import ActionButton from '@/components/ui/ActionButton';
import { removeMember } from '@/features/shop/members/actions';
import RoleChanger from '@/features/shop/members/RoleChanger';

// roles = [{ value, label }]; canEdit/canDelete hide controls the user may not use
export default function MemberTable({ members, roles, canEdit, canDelete }) {
  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            <th className="px-4 py-3">Member</th>
            <th className="px-4 py-3">Role</th>
            <th className="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {members.map((member) => (
            <tr key={member.id}>
              <td className="px-4 py-3">
                <p className="font-medium">{member.name}</p>
                <p className="text-slate-500">{member.email}</p>
              </td>
              <td className="px-4 py-3">
                {canEdit && roles.length > 0
                  ? <RoleChanger userId={member.id} roleId={member.role.id} roles={roles} />
                  : member.role.name}
              </td>
              <td className="px-4 py-3 text-right">
                {canDelete && (
                  <ActionButton action={removeMember.bind(null, member.id)} label="Remove" confirm={`Remove ${member.name}?`} />
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
