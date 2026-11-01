import SignupForm from '@/features/auth/SignupForm';

export const metadata = { title: 'Start your free trial' };

export default function SignupPage() {
  return <SignupForm googleClientId={process.env.GOOGLE_CLIENT_ID} trialDays={process.env.TRIAL_DAYS || 7} />;
}
