import Link from 'next/link';
import GoogleButton from '@/features/auth/GoogleButton';
import LoginForm from '@/features/auth/LoginForm';
import { login } from '@/features/auth/actions';

export default function LoginPage() {
  const googleClientId = process.env.GOOGLE_CLIENT_ID;

  return (
    <div className="space-y-4">
      <LoginForm action={login} title="Shop login" />

      <GoogleButton clientId={googleClientId} />

      <p className="text-center text-sm text-slate-500">
        New here? <Link href="/signup" className="font-medium text-slate-900 underline">Start your free trial</Link>
      </p>
    </div>
  );
}
