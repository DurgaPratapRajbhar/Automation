import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import AutoRefresh from '@/components/ui/AutoRefresh';
import ChatThread from '@/features/shop/inbox/ChatThread';
import ReplyForm from '@/features/shop/inbox/ReplyForm';
import { sendReply } from '@/features/shop/inbox/actions';
import { customerLabel } from '@/features/shop/inbox/format';
import { getConversation } from '@/features/shop/inbox/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function ConversationPage({ params }) {
  const { conversationId } = await params;
  const { ok, data } = await getConversation(conversationId);

  if (!ok) return <Alert message={data.message} />;

  const conversation = data.data;

  return (
    <div className="max-w-3xl space-y-4">
      <AutoRefresh seconds={10} />

      <div>
        <Link href="/inbox" className="text-sm text-slate-500 hover:underline">← Inbox</Link>
        <h1 className="text-2xl font-semibold">{customerLabel(conversation)}</h1>
        <p className="text-sm text-slate-500">+{conversation.customer_phone}</p>
      </div>

      <ChatThread messages={conversation.messages} />

      {(await can('messages.create')) && <ReplyForm action={sendReply.bind(null, conversation.id)} />}
    </div>
  );
}
