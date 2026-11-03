'use client';

import { useEffect, useRef, useState } from 'react';

// A small red "!" button. Clicking it opens a short explanation next to it; click outside or Escape closes it.
// Use it where a shopkeeper may not understand why something is needed or why it fails.
export default function InfoTip({ label = 'More information', children }) {
  const [open, setOpen] = useState(false);
  const box = useRef(null);

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

  return (
    <div ref={box} className="relative inline-block">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-label={label}
        aria-expanded={open}
        className="flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-sm font-bold text-white shadow-sm ring-2 ring-red-100 hover:bg-red-500"
      >
        !
      </button>

      {open && (
        <div
          role="dialog"
          aria-label={label}
          className="absolute left-0 top-9 z-20 w-72 max-w-[calc(100vw-2rem)] space-y-2 rounded-xl bg-white p-4 text-sm text-slate-700 shadow-lg ring-1 ring-red-200 sm:w-80"
        >
          {children}
        </div>
      )}
    </div>
  );
}
