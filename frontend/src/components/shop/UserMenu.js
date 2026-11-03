'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { ACCOUNT_MENU } from '@/components/shop/menu';
import Icon from '@/components/ui/Icon';

// Profile button at the top right: shop name, role, and the links Shop profile / Password / Logout
export default function UserMenu({ access }) {
  const [open, setOpen] = useState(false);
  const box = useRef(null);
  const links = ACCOUNT_MENU.filter((item) => !item.permission || access.permissions.includes(item.permission));
  const initial = access.workspace.name.trim().charAt(0).toUpperCase() || 'S';
  const roleName = access.role?.name ?? 'Super admin';

  // Close on a click outside or the Escape key
  useEffect(() => {
    if (!open) return undefined;

    const onClick = (event) => {
      if (box.current && !box.current.contains(event.target)) setOpen(false);
    };
    const onKey = (event) => {
      if (event.key === 'Escape') setOpen(false);
    };

    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);

    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const itemClass = 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100';

  return (
    <div ref={box} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-label="Profile menu"
        className="group flex items-center gap-2 rounded-full bg-white py-1 pl-1 pr-3 shadow-sm ring-1 ring-slate-200 transition hover:ring-2 hover:ring-emerald-300"
      >
        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-sm font-semibold text-white shadow-sm">
          {initial}
        </span>
        <span className="hidden text-left leading-tight sm:block">
          <span className="block text-sm font-medium">{roleName}</span>
        </span>
        <Icon
          name="chevron"
          size={16}
          className={`text-emerald-500 transition-transform ${open ? 'rotate-180' : ''}`}
        />
      </button>

      {open && (
        <div role="menu" className="absolute right-0 z-20 mt-2 w-56 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200">
          <div className="border-b border-slate-100 px-3 pb-2 pt-1">
            <p className="truncate text-sm font-semibold">{access.workspace.name}</p>
            <p className="text-xs text-slate-500">{roleName}</p>
          </div>

          <div className="pt-2">
            {links.map((item) => (
              <Link key={item.href} href={item.href} role="menuitem" onClick={() => setOpen(false)} className={itemClass}>
                <Icon name={item.icon} size={18} className="text-emerald-500" />
                {item.label}
              </Link>
            ))}
            {/* Plain link: /logout is a route that clears the session */}
            <a href="/logout" role="menuitem" className={`${itemClass} mt-1 border-t border-slate-100 pt-3 text-red-600`}>
              <Icon name="logout" size={18} />
              Logout
            </a>
          </div>
        </div>
      )}
    </div>
  );
}
