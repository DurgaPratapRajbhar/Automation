import Link from 'next/link';

const MENU = [
  { label: 'Workspaces', href: '/admin/workspaces' },
  { label: 'Role templates', href: '/admin/role-templates' },
];

export default function AdminSidebar({ user }) {
  return (
    <aside className="flex w-full flex-col gap-1 border-b border-slate-800 bg-slate-900 p-4 text-slate-200 md:min-h-screen md:w-60 md:border-b-0">
      <p className="font-semibold text-white">Super Admin</p>
      <p className="mb-4 truncate text-sm text-slate-400">{user.email}</p>

      {MENU.map((item) => (
        <Link key={item.href} href={item.href} className="rounded-lg px-3 py-2 hover:bg-slate-800">
          {item.label}
        </Link>
      ))}

      <Link href="/dashboard" className="mt-auto rounded-lg px-3 py-2 text-slate-400 hover:bg-slate-800">Back to shop</Link>
      <a href="/logout" className="rounded-lg px-3 py-2 text-slate-400 hover:bg-slate-800">Logout</a>
    </aside>
  );
}
