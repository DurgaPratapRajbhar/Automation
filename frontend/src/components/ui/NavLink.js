'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

// Sidebar link that looks selected on its own page and the pages under it (/inbox and /inbox/5).
// Only one of activeClassName / inactiveClassName is applied, so their colours never clash.
export default function NavLink({ href, className = '', activeClassName, inactiveClassName, children }) {
  const pathname = usePathname();
  const active = pathname === href || pathname.startsWith(`${href}/`);

  return (
    <Link href={href} aria-current={active ? 'page' : undefined} className={`${className} ${active ? activeClassName : inactiveClassName}`}>
      {children}
    </Link>
  );
}
