import { redirect } from 'next/navigation';
import AdminSidebar from '@/components/admin/Sidebar';
import { getMe } from '@/features/admin/queries';

export const metadata = { title: 'Super Admin' };

// Shell for super admin pages. Only hides the UI: the API checks super admin again.
export default async function AdminLayout({ children }) {
  const { ok, status, data } = await getMe();

  // Expired or not a super admin: clear the admin session and show the admin login
  if (status === 401 || !ok || !data.data.user.is_super_admin) redirect('/admin/logout');

  return (
    <div className="md:flex">
      <AdminSidebar user={data.data.user} />
      <div className="flex-1">
        <p className="bg-amber-100 px-6 py-2 text-sm text-amber-900">
          Super Admin panel: settings for all shops on the platform
        </p>
        <main className="p-6">{children}</main>
      </div>
    </div>
  );
}
