import SignupForm from '@/features/auth/SignupForm';

export const metadata = { title: 'Start your free trial' };

// Read the Google client id and trial days at request time, not once at build time
export const dynamic = 'force-dynamic';

export default function SignupPage() {
  return <SignupForm googleClientId={process.env.GOOGLE_CLIENT_ID} trialDays={process.env.TRIAL_DAYS || 7} />;
}
