import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import ChannelList from '@/features/shop/whatsapp/ChannelList';
import ConnectWhatsAppButton from '@/features/shop/whatsapp/ConnectWhatsAppButton';
import { getChannels } from '@/features/shop/whatsapp/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function WhatsAppPage() {
  const { ok, data } = await getChannels();

  if (!ok) return <Alert message={data.message} />;

  const canConnect = await can('channels.connect');

  return (
    <div className="max-w-3xl space-y-6">
      <PageHeader title="WhatsApp" />

      <p className="text-sm text-slate-600">
        Connect your shop&apos;s WhatsApp number. Customers who message it get answers from the bot, and every chat shows in your Inbox.
      </p>

      {canConnect && (
        data.embedded_signup
          ? <ConnectWhatsAppButton signup={data.embedded_signup} />
          : <Alert message="WhatsApp signup is not set up on the server yet." />
      )}

      <ChannelList channels={data.data} canDelete={await can('channels.delete')} />
    </div>
  );
}
