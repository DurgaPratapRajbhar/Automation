import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import RoleTable from '@/features/shop/roles/RoleTable';
import { getRoles } from '@/features/shop/roles/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function RolesPage() {
  const { ok, data } = await getRoles();

  if (!ok) return <Alert message={data.message} />;

  return (
    <>
      <PageHeader title="Roles" actionLabel="New role" actionHref={(await can('roles.create')) && '/roles/new'} />
      <RoleTable roles={data.data} />
    </>
  );
}
