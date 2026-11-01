import { redirect } from 'next/navigation';
import Sidebar from '@/components/shop/Sidebar';
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
      <main className="flex-1 p-6">
        {daysLeft !== null && (
          <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
            Free trial: <strong>{daysLeft} {daysLeft === 1 ? 'day' : 'days'} left</strong>. To keep going after that, message ScriptDP on
            WhatsApp {process.env.CONTACT_PHONE || '83569 74324'}.
          </p>
        )}
        {children}
      </main>
    </div>
  );
}
