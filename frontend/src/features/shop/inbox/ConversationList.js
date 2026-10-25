import Link from 'next/link';
import { customerLabel, formatTime } from '@/features/shop/inbox/format';

function lastLine(message) {
  if (!message) return '';
  if (message.direction === 'inbound') return message.body ?? '';

  return `${message.is_ai ? 'Bot' : 'You'}: ${message.body ?? ''}`;
}

export default function ConversationList({ conversations }) {
  if (conversations.length === 0) {
    return <p className="text-slate-500">No chats yet. When a customer messages your WhatsApp number, the chat shows here.</p>;
  }

  return (
    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
      {conversations.map((conversation) => (
        <li key={conversation.id}>
          <Link href={`/inbox/${conversation.id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
            <div className="min-w-0 flex-1">
              <div className="flex items-baseline justify-between gap-3">
                <p className={`truncate ${conversation.unread_count ? 'font-semibold' : 'font-medium'}`}>{customerLabel(conversation)}</p>
                <span className="shrink-0 text-xs text-slate-500">{formatTime(conversation.last_message_at)}</span>
              </div>
              <p className="truncate text-sm text-slate-500">{lastLine(conversation.last_message)}</p>
            </div>
            {conversation.unread_count > 0 && (
              <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white">{conversation.unread_count}</span>
            )}
          </Link>
        </li>
      ))}
    </ul>
  );
}
