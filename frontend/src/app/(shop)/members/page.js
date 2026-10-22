import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import AddMemberForm from '@/features/shop/members/AddMemberForm';
import MemberTable from '@/features/shop/members/MemberTable';
import { getMembers } from '@/features/shop/members/queries';
import { getRoles } from '@/features/shop/roles/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function MembersPage() {
  const [members, roles] = await Promise.all([getMembers(), getRoles()]);

  if (!members.ok) return <Alert message={members.data.message} />;

  // Role list needs roles.view; without it the role can only be read, not changed
  const roleOptions = roles.ok ? roles.data.data.map((role) => ({ value: role.id, label: role.name })) : [];

  return (
    <>
      <PageHeader title="Members" />

      {(await can('members.create')) && roleOptions.length > 0 && <AddMemberForm roles={roleOptions} />}

      <MemberTable
        members={members.data.data}
        roles={roleOptions}
        canEdit={await can('members.edit')}
        canDelete={await can('members.delete')}
      />
    </>
  );
}
