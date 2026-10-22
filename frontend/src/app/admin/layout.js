import { redirect } from 'next/navigation';
import AdminSidebar from '@/components/admin/Sidebar';
import { getMe } from '@/features/admin/queries';

// Shell for super admin pages. Only hides the UI: the API checks super admin again.
export default async function AdminLayout({ children }) {
  const { ok, status, data } = await getMe();

  if (status === 401) redirect('/logout');
  if (!ok || !data.data.user.is_super_admin) redirect('/dashboard');

  return (
    <div className="md:flex">
      <AdminSidebar user={data.data.user} />
      <main className="flex-1 p-6">{children}</main>
    </div>
  );
}
