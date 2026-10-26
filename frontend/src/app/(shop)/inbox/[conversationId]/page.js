import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import AutoRefresh from '@/components/ui/AutoRefresh';
import ChatThread from '@/features/shop/inbox/ChatThread';
import ReplyForm from '@/features/shop/inbox/ReplyForm';
import TemplateSender from '@/features/shop/inbox/TemplateSender';
import { sendReply, sendTemplateMessage } from '@/features/shop/inbox/actions';
import { customerLabel } from '@/features/shop/inbox/format';
import { getConversation } from '@/features/shop/inbox/queries';
import { getTemplates } from '@/features/shop/whatsapp/templates/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function ConversationPage({ params }) {
  const { conversationId } = await params;
  const { ok, data } = await getConversation(conversationId);

  if (!ok) return <Alert message={data.message} />;

  const conversation = data.data;
  const canTemplate = await can('messages.template');

  // Only approved templates can be sent
  const templates = canTemplate ? await getTemplates(conversation.channel_id) : null;
  const approved = templates?.ok ? templates.data.data.filter((t) => t.status === 'APPROVED') : [];

  return (
    <div className="max-w-3xl space-y-4">
      <AutoRefresh seconds={10} />

      <div>
        <Link href="/inbox" className="text-sm text-slate-500 hover:underline">← Inbox</Link>
        <h1 className="text-2xl font-semibold">{customerLabel(conversation)}</h1>
        <p className="text-sm text-slate-500">+{conversation.customer_phone}</p>
      </div>

      <ChatThread messages={conversation.messages} />

      {!conversation.window_open && (
        <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
          The customer has not written in the last 24 hours, so WhatsApp will only deliver an approved template now.
        </p>
      )}

      {canTemplate && (
        templates?.ok
          ? <TemplateSender action={sendTemplateMessage.bind(null, conversation.id)} templates={approved} open={!conversation.window_open} />
          : <Alert message={templates?.data?.message} />
      )}

      {(await can('messages.create')) && <ReplyForm action={sendReply.bind(null, conversation.id)} />}
    </div>
  );
}
