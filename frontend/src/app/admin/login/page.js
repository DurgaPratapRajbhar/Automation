import AuthCard from '@/components/ui/AuthCard';
import LoginForm from '@/features/auth/LoginForm';
import { adminLogin } from '@/features/auth/actions';

export const metadata = { title: 'Super Admin login' };

export default function AdminLoginPage() {
  return (
    <AuthCard>
      <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-indigo-700">Super Admin</p>
      <LoginForm action={adminLogin} title="Admin login" />
    </AuthCard>
  );
}
