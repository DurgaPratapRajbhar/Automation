import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import RoleTemplateTable from '@/features/admin/RoleTemplateTable';
import { getRoleTemplates } from '@/features/admin/queries';

export default async function RoleTemplatesPage() {
  const { ok, data } = await getRoleTemplates();

  if (!ok) return <Alert message={data.message} />;

  return (
    <>
      <PageHeader title="Role templates" actionLabel="New template" actionHref="/admin/role-templates/new" />
      <p className="mb-4 text-sm text-slate-600">Every new workspace gets a copy of these roles. Existing workspaces are not changed.</p>
      <RoleTemplateTable templates={data.data} />
    </>
  );
}
