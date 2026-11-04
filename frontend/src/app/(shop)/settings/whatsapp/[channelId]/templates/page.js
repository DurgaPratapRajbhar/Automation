import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import TemplateCreator from '@/features/shop/whatsapp/templates/TemplateCreator';
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
        Meta charges your business per template message (Utility costs less than Marketing).
      </p>

      <div className="rounded-lg bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
        <p className="font-medium">Add a payment method in Meta, or templates will not be sent</p>
        <p className="mt-1">
          Open Meta Business Suite, then Billing &amp; payments, then Payment methods, choose this WhatsApp account, and add a card or UPI.
          Meta charges it automatically for each template message. Replies within 24 hours of a customer&apos;s message are free.
        </p>
      </div>

      {templates.ok
        ? <TemplateList channelId={channelId} templates={templates.data.data} canDelete={await can('templates.delete')} />
        : <Alert message={templates.data.message} />}

      {(await can('templates.create')) && (
        <TemplateCreator action={createTemplate.bind(null, channelId)} canDraft={await can('campaign-drafts.create')} />
      )}
    </div>
  );
}
