import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import PasswordForm from '@/features/shop/profile/PasswordForm';
import { getWorkspaceAccess } from '@/features/shop/workspace/queries';

export const metadata = { title: 'Change password' };

export default async function PasswordPage() {
  const { ok, data } = await getWorkspaceAccess();

  if (!ok) return <Alert message={data.message} />;

  return (
    <>
      <PageHeader title="Change password" />
      <PasswordForm hasPassword={data.data.has_password} />
    </>
  );
}
