import { redirect } from 'next/navigation';
import Sidebar from '@/components/shop/Sidebar';
import { getWorkspaceAccess } from '@/features/shop/workspace/queries';

// Shell for every logged-in page
export default async function AppLayout({ children }) {
  const { ok, status, data } = await getWorkspaceAccess();

  if (status === 401) {
    redirect('/logout');
  }

  if (!ok) {
    return (
      <main className="p-6">
        <p className="mb-4">{data.message}</p>
        <a href="/logout" className="underline">Logout</a>
      </main>
    );
  }

  return (
    <div className="md:flex">
      <Sidebar access={data.data} />
      <main className="flex-1 p-6">{children}</main>
    </div>
  );
}
