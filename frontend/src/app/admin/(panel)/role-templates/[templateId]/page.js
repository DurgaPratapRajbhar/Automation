import { notFound } from 'next/navigation';
import ActionButton from '@/components/ui/ActionButton';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import RoleForm from '@/components/ui/RoleForm';
import { deleteRoleTemplate, saveRoleTemplate } from '@/features/admin/actions';
import { getAdminPermissionTree, getRoleTemplates } from '@/features/admin/queries';

export default async function RoleTemplatePage({ params }) {
  const { templateId } = await params;
  const [templates, tree] = await Promise.all([getRoleTemplates(), getAdminPermissionTree()]);

  if (!templates.ok) return <Alert message={templates.data.message} />;
  if (!tree.ok) return <Alert message={tree.data.message} />;

  const template = templates.data.data.find((item) => String(item.id) === templateId);

  if (!template) notFound();

  return (
    <>
      <PageHeader title={template.name} />
      <RoleForm action={saveRoleTemplate.bind(null, template.id)} tree={tree.data.data} role={template} />

      <div className="mt-8">
        <ActionButton action={deleteRoleTemplate.bind(null, template.id)} label="Delete template" confirm={`Delete template ${template.name}?`} />
      </div>
    </>
  );
}
