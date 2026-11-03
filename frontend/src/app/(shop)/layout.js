import Link from 'next/link';
import { redirect } from 'next/navigation';
import Sidebar from '@/components/shop/Sidebar';
import Icon from '@/components/ui/Icon';
import InstallApp from '@/components/ui/InstallApp';
import { CONTACT_PHONE } from '@/lib/contact';
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
        <header className="hidden items-center justify-end gap-3 border-b border-slate-200 bg-white px-6 py-3 md:flex">
          <InstallApp />
          <Link
            href="/features"
            className="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-emerald-700 shadow-sm ring-1 ring-slate-200 transition hover:ring-2 hover:ring-emerald-300"
          >
            <Icon name="sparkle" size={18} />
            Features
          </Link>
          <UserMenu access={data.data} />
        </header>
        <main className="p-6">
          {daysLeft !== null && (
            <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
              Free trial: <strong>{daysLeft} {daysLeft === 1 ? 'day' : 'days'} left</strong>. To keep going after that, message ScriptDP on
              WhatsApp {CONTACT_PHONE}.
            </p>
          )}
          {children}
        </main>
        <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-6 py-4 text-sm text-slate-500">
          <span>
            Need more features? <Link href="/features" className="font-medium text-emerald-700 hover:underline">See what you can get</Link>
          </span>
          <span className="flex gap-4">
            <a href="https://scriptdp.com/en/terms" target="_blank" rel="noopener noreferrer" className="hover:underline">Terms</a>
            <a href="https://scriptdp.com/en/privacy" target="_blank" rel="noopener noreferrer" className="hover:underline">Privacy</a>
          </span>
        </footer>
      </div>
    </div>
  );
}
