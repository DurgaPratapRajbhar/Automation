import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import RoleForm from '@/components/ui/RoleForm';
import { saveRole } from '@/features/shop/roles/actions';
import { getPermissionTree } from '@/features/shop/roles/queries';

export default async function NewRolePage() {
  const { ok, data } = await getPermissionTree();

  if (!ok) return <Alert message={data.message} />;

  return (
    <>
      <PageHeader title="New role" />
      <RoleForm action={saveRole.bind(null, null)} tree={data.data} />
    </>
  );
}
