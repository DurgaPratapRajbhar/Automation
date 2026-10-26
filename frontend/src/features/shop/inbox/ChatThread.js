import { formatTime } from '@/features/shop/inbox/format';

// WhatsApp-style ticks for our messages, as Meta reports them
const TICKS = {
  sent: { mark: '✓', label: 'Sent', tone: '' },
  delivered: { mark: '✓✓', label: 'Delivered', tone: '' },
  read: { mark: '✓✓', label: 'Read', tone: 'text-sky-500' },
};

function Status({ message }) {
  if (message.status === 'failed') {
    return <span className="ml-1 font-semibold text-red-500">· not sent</span>;
  }

  const tick = TICKS[message.status];

  return tick ? <span title={tick.label} aria-label={tick.label} className={`ml-1 ${tick.tone}`}>{tick.mark}</span> : null;
}

// Customer on the left, shop (bot or staff) on the right
function Bubble({ message }) {
  const mine = message.direction === 'outbound';
  const who = mine ? (message.is_ai ? 'Bot' : message.sent_by || 'Staff') : null;

  return (
    <div className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
      <div className={`max-w-[80%] rounded-2xl px-3 py-2 text-sm ${mine ? (message.is_ai ? 'bg-emerald-50 ring-1 ring-emerald-200' : 'bg-slate-900 text-white') : 'bg-white ring-1 ring-slate-200'}`}>
        {who && (
          <p className={`mb-0.5 text-xs font-semibold ${message.is_ai ? 'text-emerald-700' : 'text-slate-300'}`}>{who}</p>
        )}
        <p className="whitespace-pre-wrap break-words">{message.body ?? `[${message.type}]`}</p>
        <p className={`mt-1 text-right text-[11px] ${mine && !message.is_ai ? 'text-slate-400' : 'text-slate-500'}`}>
          {formatTime(message.created_at)}
          {mine && <Status message={message} />}
        </p>
        {message.status === 'failed' && message.error && (
          <p className="mt-1 break-all rounded bg-red-50 px-2 py-1 text-xs text-red-700">{message.error}</p>
        )}
      </div>
    </div>
  );
}

export default function ChatThread({ messages }) {
  if (messages.length === 0) return <p className="text-slate-500">No messages.</p>;

  return (
    <div className="space-y-2 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
      {messages.map((message) => (
        <Bubble key={message.id} message={message} />
      ))}
    </div>
  );
}
