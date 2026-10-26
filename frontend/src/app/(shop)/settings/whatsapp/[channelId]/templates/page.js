import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import TemplateForm from '@/features/shop/whatsapp/templates/TemplateForm';
import TemplateList from '@/features/shop/whatsapp/templates/TemplateList';
import { createTemplate } from '@/features/shop/whatsapp/templates/actions';
import { getChannel, getTemplates } from '@/features/shop/whatsapp/templates/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function TemplatesPage({ params }) {
  const { channelId } = await params;
  const [channel, templates] = await Promise.all([getChannel(channelId), getTemplates(channelId)]);

  if (!channel.ok) return <Alert message={channel.data.message} />;

  return (
    <div className="max-w-3xl space-y-6">
      <div>
        <Link href="/settings/whatsapp" className="text-sm text-slate-500 hover:underline">← WhatsApp</Link>
        <h1 className="text-2xl font-semibold">Templates</h1>
        <p className="text-sm text-slate-500">{channel.data.data.name}</p>
      </div>

      <p className="text-sm text-slate-600">
        WhatsApp lets you message a customer first, or after 24 hours of silence, only with a template that Meta has approved.
        Meta charges the shop per template message (Utility costs less than Marketing).
      </p>

      {templates.ok
        ? <TemplateList channelId={channelId} templates={templates.data.data} canDelete={await can('templates.delete')} />
        : <Alert message={templates.data.message} />}

      {(await can('templates.create')) && <TemplateForm action={createTemplate.bind(null, channelId)} />}
    </div>
  );
}
