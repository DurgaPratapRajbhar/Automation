import Link from 'next/link';
import { MENU } from '@/components/shop/menu';

export default function Sidebar({ access }) {
  const links = MENU.filter((item) => !item.permission || access.permissions.includes(item.permission));

  return (
    <aside className="flex w-full flex-col gap-1 border-b border-slate-200 bg-white p-4 md:min-h-screen md:w-60 md:border-b-0 md:border-r">
      <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Shop</p>
      <p className="mb-4 truncate font-semibold">{access.workspace.name}</p>

      {links.map((item) => (
        <Link key={item.href} href={item.href} className="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100">
          {item.label}
        </Link>
      ))}

      {/* Plain link: /logout is a route that clears the session */}
      <a href="/logout" className="mt-auto rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">
        Logout
      </a>
    </aside>
  );
}
