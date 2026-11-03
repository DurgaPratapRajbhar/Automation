'use client';

import { useEffect, useState } from 'react';
import Icon from '@/components/ui/Icon';

// "Install app" button. Android/Chrome gives a real install prompt; on an iPhone the only way is
// Safari's Share menu, so there we show a short how-to. Hidden once the app is installed.
export default function InstallApp({ className = '' }) {
  const [promptEvent, setPromptEvent] = useState(null);
  const [installed, setInstalled] = useState(false);
  const [iphone, setIphone] = useState(false);
  const [showHelp, setShowHelp] = useState(false);

  useEffect(() => {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    setInstalled(standalone);
    setIphone(/iphone|ipad|ipod/i.test(navigator.userAgent));

    const onPrompt = (event) => {
      event.preventDefault();
      setPromptEvent(event);
    };
    const onInstalled = () => setInstalled(true);

    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);

    return () => {
      window.removeEventListener('beforeinstallprompt', onPrompt);
      window.removeEventListener('appinstalled', onInstalled);
    };
  }, []);

  if (installed || (!promptEvent && !iphone)) return null;

  async function install() {
    if (iphone) {
      setShowHelp((value) => !value);

      return;
    }

    await promptEvent.prompt();
    setPromptEvent(null);
  }

  return (
    <div className="relative">
      <button
        type="button"
        onClick={install}
        className={`flex items-center gap-2 rounded-full bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-500 ${className}`}
      >
        <Icon name="phone" size={17} />
        Install app
      </button>

      {showHelp && (
        <div className="absolute right-0 z-20 mt-2 w-64 rounded-xl bg-white p-3 text-sm text-slate-700 shadow-lg ring-1 ring-slate-200">
          On iPhone: tap the <strong>Share</strong> button in Safari, then <strong>Add to Home Screen</strong>.
        </div>
      )}
    </div>
  );
}
