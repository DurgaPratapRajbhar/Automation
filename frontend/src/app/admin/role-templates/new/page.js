import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import RoleForm from '@/components/ui/RoleForm';
import { saveRoleTemplate } from '@/features/admin/actions';
import { getAdminPermissionTree } from '@/features/admin/queries';

export default async function NewRoleTemplatePage() {
  const { ok, data } = await getAdminPermissionTree();

  if (!ok) return <Alert message={data.message} />;

  return (
    <>
      <PageHeader title="New role template" />
      <RoleForm action={saveRoleTemplate.bind(null, null)} tree={data.data} />
    </>
  );
}
