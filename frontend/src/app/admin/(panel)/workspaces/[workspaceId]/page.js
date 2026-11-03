import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import EditWorkspaceForm from '@/features/admin/EditWorkspaceForm';
import MemberPasswordForm from '@/features/admin/MemberPasswordForm';
import WorkspaceModulesForm from '@/features/admin/WorkspaceModulesForm';
import { getWorkspace } from '@/features/admin/queries';

export default async function EditWorkspacePage({ params }) {
  const { workspaceId } = await params;
  const { ok, data } = await getWorkspace(workspaceId);

  if (!ok) return <Alert message={data.message} />;

  const workspace = data.data;

  return (
    <div className="space-y-8">
      <div>
        <Link href="/admin/workspaces" className="text-sm text-slate-500 underline">Back to workspaces</Link>
        <PageHeader title={workspace.name} />
        <EditWorkspaceForm workspace={workspace} />
      </div>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">Features for this shop</h2>
        <p className="text-sm text-slate-600">
          Tick what this shop can use. A new shop starts with Inbox, WhatsApp and Knowledge; switch on more when the customer asks.
        </p>
        <WorkspaceModulesForm workspace={workspace} />
      </section>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold">People who can log in</h2>
        <p className="text-sm text-slate-600">
          Forgot a password? Set a new one here. The user is logged out everywhere and must use the new password.
        </p>
        <div className="grid max-w-3xl gap-4">
          {workspace.members.map((member) => (
            <MemberPasswordForm key={member.id} workspaceId={workspace.id} member={member} />
          ))}
        </div>
      </section>
    </div>
  );
}
