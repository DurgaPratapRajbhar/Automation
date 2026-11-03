import InstallApp from '@/components/ui/InstallApp';

// Centered card for login/register pages, with the ScriptDP logo above it
export default function AuthCard({ children }) {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-5 px-4 py-8">
      <div className="flex items-center gap-3">
        <img src="/brand/scriptdp-logo.png" alt="" width="44" height="44" className="h-11 w-11 rounded-xl" />
        <div>
          <p className="text-lg font-semibold leading-tight">ScriptDP</p>
          <p className="text-xs uppercase tracking-wide text-emerald-700">WhatsApp Automation</p>
        </div>
      </div>

      <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">{children}</div>

      {/* A new shopkeeper meets the app here first: show "Install app" on the phone */}
      <div className="md:hidden">
        <InstallApp />
      </div>

      <p className="text-xs text-slate-500">
        <a href="https://scriptdp.com/en/terms" className="underline">Terms</a>
        {' · '}
        <a href="https://scriptdp.com/en/privacy" className="underline">Privacy</a>
      </p>
    </main>
  );
}
