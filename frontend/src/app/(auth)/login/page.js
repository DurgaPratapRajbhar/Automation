import LoginForm from '@/features/auth/LoginForm';
import { login } from '@/features/auth/actions';

export default function LoginPage() {
  return <LoginForm action={login} title="Shop login" />;
}
