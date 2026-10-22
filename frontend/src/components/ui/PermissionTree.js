'use client';

import { useState } from 'react';

// Module -> permission checkboxes. Ticking a module ticks all its permissions.
// Sends the ticked names as form field "permissions" (many values).
export default function PermissionTree({ tree, selected = [] }) {
  const [checked, setChecked] = useState(new Set(selected));

  const toggle = (names, on) => {
    const next = new Set(checked);
    names.forEach((name) => (on ? next.add(name) : next.delete(name)));
    setChecked(next);
  };

  return (
    <div className="space-y-3">
      {tree.map((group) => {
        const names = group.permissions.map((permission) => permission.name);
        const allOn = names.every((name) => checked.has(name));

        return (
          <fieldset key={group.module} className="rounded-lg bg-white p-3 ring-1 ring-slate-200">
            <label className="flex items-center gap-2 font-medium">
              <input type="checkbox" checked={allOn} onChange={(event) => toggle(names, event.target.checked)} />
              {group.label}
            </label>

            <div className="mt-2 flex flex-wrap gap-x-5 gap-y-1 pl-6 text-sm">
              {group.permissions.map((permission) => (
                <label key={permission.name} className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="permissions"
                    value={permission.name}
                    checked={checked.has(permission.name)}
                    onChange={(event) => toggle([permission.name], event.target.checked)}
                  />
                  {permission.label}
                </label>
              ))}
            </div>
          </fieldset>
        );
      })}
    </div>
  );
}
