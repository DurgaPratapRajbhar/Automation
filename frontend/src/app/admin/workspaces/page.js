import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import WorkspaceTable from '@/features/admin/WorkspaceTable';
import { getWorkspaces } from '@/features/admin/queries';

export default async function AdminWorkspacesPage({ searchParams }) {
  const { search = '', cursor } = await searchParams;
  const { ok, data } = await getWorkspaces({ search, cursor });

  if (!ok) return <Alert message={data.message} />;

  const nextPage = data.next_cursor && `/admin/workspaces?${new URLSearchParams({ search, cursor: data.next_cursor })}`;

  return (
    <>
      <PageHeader title="Workspaces" />

      <form className="mb-4 flex gap-2">
        <input name="search" defaultValue={search} placeholder="Search by name" className="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2" />
        <Button variant="secondary">Search</Button>
      </form>

      <WorkspaceTable workspaces={data.data} />

      {nextPage && <Link href={nextPage} className="mt-4 inline-block text-sm underline">Next page</Link>}
    </>
  );
}
