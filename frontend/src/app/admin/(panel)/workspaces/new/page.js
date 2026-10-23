import PageHeader from '@/components/ui/PageHeader';
import WorkspaceForm from '@/features/admin/WorkspaceForm';

export default function NewWorkspacePage() {
  return (
    <>
      <PageHeader title="New shop" />
      <p className="mb-4 text-sm text-slate-600">
        Creates the shop and its owner login. The owner then adds staff and gives them roles.
      </p>
      <WorkspaceForm />
    </>
  );
}
