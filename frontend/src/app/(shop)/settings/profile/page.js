import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import ProfileForm from '@/features/shop/profile/ProfileForm';
import { getProfile } from '@/features/shop/profile/queries';
import ApiKeyCard from '@/features/shop/website/ApiKeyCard';
import { getApiKey } from '@/features/shop/website/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function ProfilePage() {
  const { ok, data } = await getProfile();

  if (!ok) return <Alert message={data.message} />;

  const apiKey = (await can('api-key.view')) ? await getApiKey() : null;

  return (
    <div className="space-y-8">
      <div>
        <PageHeader title="Business profile" />
        <ProfileForm profile={data.data} canEdit={await can('workspace.edit')} />
      </div>

      {apiKey?.ok && (
        <ApiKeyCard status={apiKey.data.data} canCreate={await can('api-key.create')} canDelete={await can('api-key.delete')} />
      )}
    </div>
  );
}
