'use client';

import { useState } from 'react';
import { MENU } from '@/components/shop/menu';
import UserMenu from '@/components/shop/UserMenu';
import Icon from '@/components/ui/Icon';
import NavLink from '@/components/ui/NavLink';

// Left menu on a computer or tablet. On a phone it is a top bar with the profile button and a "Menu" button that opens the links.
export default function Sidebar({ access }) {
  const [open, setOpen] = useState(false);
  const links = MENU.filter((item) => !item.permission || access.permissions.includes(item.permission));

  return (
    <aside className="border-b border-slate-200 bg-white md:flex md:min-h-screen md:w-60 md:shrink-0 md:flex-col md:border-b-0 md:border-r">
      <div className="flex items-center justify-between gap-3 p-4">
        <div className="flex min-w-0 items-center gap-3">
          <img src="/brand/scriptdp-logo.png" alt="ScriptDP" width="36" height="36" className="h-9 w-9 shrink-0 rounded-lg" />
          <div className="min-w-0">
            <p className="truncate font-semibold">{access.workspace.name}</p>          </div>
        </div>

        <div className="flex shrink-0 items-center gap-2 md:hidden">
          <UserMenu access={access} />
          <button
            type="button"
            onClick={() => setOpen((value) => !value)}
            aria-expanded={open}
            aria-controls="shop-menu"
            className="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100"
          >
            {open ? 'Close' : 'Menu'}
          </button>
        </div>
      </div>

      <nav id="shop-menu" className={`${open ? 'flex' : 'hidden'} flex-1 flex-col gap-1 px-4 pb-4 md:flex`}>
        {links.map((item) => (
          <NavLink
            key={item.href}
            href={item.href}
            onClick={() => setOpen(false)}
            className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
            activeClassName="bg-emerald-50 font-semibold text-emerald-800 ring-1 ring-emerald-100"
            inactiveClassName="font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
          >
            <Icon name={item.icon} size={19} />
            {item.label}
          </NavLink>
        ))}

        {/* On a computer this link is in the top bar; on a phone it lives here */}
        <NavLink
          href="/features"
          onClick={() => setOpen(false)}
          className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm md:hidden"
          activeClassName="bg-emerald-50 font-semibold text-emerald-800 ring-1 ring-emerald-100"
          inactiveClassName="font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
        >
          <Icon name="sparkle" size={19} />
          Features
        </NavLink>
      </nav>
    </aside>
  );
}
