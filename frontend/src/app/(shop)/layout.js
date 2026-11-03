import { redirect } from 'next/navigation';
import Sidebar from '@/components/shop/Sidebar';
import UserMenu from '@/components/shop/UserMenu';
import { getWorkspaceAccess } from '@/features/shop/workspace/queries';

export const metadata = { title: 'Shop' };

// Shell for every logged-in shop page
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

  const daysLeft = data.data.workspace.trial_days_left;

  return (
    <div className="md:flex">
      <Sidebar access={data.data} />
      <div className="min-w-0 flex-1">
        {/* Computer/tablet: profile menu at the top right. On a phone it sits in the top bar instead. */}
        <header className="hidden items-center justify-end border-b border-slate-200 bg-white px-6 py-3 md:flex">
          <UserMenu access={data.data} />
        </header>
        <main className="p-6">
          {daysLeft !== null && (
            <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
              Free trial: <strong>{daysLeft} {daysLeft === 1 ? 'day' : 'days'} left</strong>. To keep going after that, message ScriptDP on
              WhatsApp {process.env.CONTACT_PHONE || '83569 74324'}.
            </p>
          )}
          {children}
        </main>
      </div>
    </div>
  );
}
