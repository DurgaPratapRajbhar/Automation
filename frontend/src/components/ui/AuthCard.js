// Centered card for login/register pages
export default function AuthCard({ children }) {
  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">{children}</div>
    </main>
  );
}
