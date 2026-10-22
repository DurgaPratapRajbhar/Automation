import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import ProfileForm from '@/features/shop/profile/ProfileForm';
import { getProfile } from '@/features/shop/profile/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function ProfilePage() {
  const { ok, data } = await getProfile();

  if (!ok) return <Alert message={data.message} />;

  return (
    <>
      <PageHeader title="Shop profile" />
      <ProfileForm profile={data.data} canEdit={await can('workspace.edit')} />
    </>
  );
}
