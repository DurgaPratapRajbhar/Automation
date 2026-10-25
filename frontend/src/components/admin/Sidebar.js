import NavLink from '@/components/ui/NavLink';

const MENU = [
  { label: 'Workspaces', href: '/admin/workspaces' },
  { label: 'Role templates', href: '/admin/role-templates' },
];

export default function AdminSidebar({ user }) {
  return (
    <aside className="flex w-full flex-col gap-1 bg-indigo-950 p-4 text-indigo-100 md:min-h-screen md:w-60">
      <p className="text-xs font-semibold uppercase tracking-wide text-amber-300">Super Admin</p>
      <p className="mb-4 truncate text-sm text-indigo-300">{user.email}</p>

      {MENU.map((item) => (
        <NavLink
          key={item.href}
          href={item.href}
          className="rounded-lg px-3 py-2"
          activeClassName="bg-indigo-800 font-semibold text-white"
          inactiveClassName="hover:bg-indigo-900"
        >
          {item.label}
        </NavLink>
      ))}

      <a href="/admin/logout" className="mt-auto rounded-lg px-3 py-2 text-indigo-300 hover:bg-indigo-900">Logout</a>
    </aside>
  );
}
