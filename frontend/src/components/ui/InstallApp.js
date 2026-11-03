'use client';

import { useEffect, useState } from 'react';
import Icon from '@/components/ui/Icon';

const HOW_TO = {
  iphone: 'On iPhone: tap the Share button in Safari, then "Add to Home Screen".',
  android: 'In Chrome: tap the ⋮ menu (top right), then "Install app" or "Add to Home screen".',
};

// "Install app" button. Chrome gives a real install prompt. Where it does not (iPhone, or a phone whose browser
// did not offer it), the button shows a short how-to instead. Hidden once the app is installed.
export default function InstallApp({ className = '' }) {
  const [promptEvent, setPromptEvent] = useState(null);
  const [installed, setInstalled] = useState(false);
  const [platform, setPlatform] = useState(null); // 'iphone' | 'android' | null (computer)
  const [waited, setWaited] = useState(false);
  const [showHelp, setShowHelp] = useState(false);

  useEffect(() => {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    setInstalled(standalone);

    const agent = navigator.userAgent;
    setPlatform(/iphone|ipad|ipod/i.test(agent) ? 'iphone' : /android/i.test(agent) ? 'android' : null);

    const onPrompt = (event) => {
      event.preventDefault();
      setPromptEvent(event);
    };
    const onInstalled = () => setInstalled(true);

    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);
    // Chrome sends the prompt event a moment after the page loads; if it never comes, show the how-to
    const timer = setTimeout(() => setWaited(true), 3000);

    return () => {
      clearTimeout(timer);
      window.removeEventListener('beforeinstallprompt', onPrompt);
      window.removeEventListener('appinstalled', onInstalled);
    };
  }, []);

  const needsHowTo = platform === 'iphone' || (platform === 'android' && waited && !promptEvent);

  if (installed || (!promptEvent && !needsHowTo)) return null;

  async function install() {
    if (promptEvent) {
      await promptEvent.prompt();
      setPromptEvent(null);

      return;
    }

    setShowHelp((value) => !value);
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
          {HOW_TO[platform]}
        </div>
      )}
    </div>
  );
}
