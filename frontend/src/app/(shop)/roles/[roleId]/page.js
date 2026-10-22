import ActionButton from '@/components/ui/ActionButton';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import RoleForm from '@/components/ui/RoleForm';
import { deleteRole, saveRole } from '@/features/shop/roles/actions';
import { getPermissionTree, getRole } from '@/features/shop/roles/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function RolePage({ params }) {
  const { roleId } = await params;
  const [role, tree] = await Promise.all([getRole(roleId), getPermissionTree()]);

  if (!role.ok) return <Alert message={role.data.message} />;
  if (!tree.ok) return <Alert message={tree.data.message} />;

  const { id, name } = role.data.data;

  return (
    <>
      <PageHeader title={name} />
      <RoleForm action={saveRole.bind(null, id)} tree={tree.data.data} role={role.data.data} />

      {(await can('roles.delete')) && (
        <div className="mt-8">
          <ActionButton action={deleteRole.bind(null, id)} label="Delete role" confirm={`Delete role ${name}?`} />
        </div>
      )}
    </>
  );
}
