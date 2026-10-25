import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import AutoRefresh from '@/components/ui/AutoRefresh';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import ConversationList from '@/features/shop/inbox/ConversationList';
import { getConversations } from '@/features/shop/inbox/queries';

export default async function InboxPage({ searchParams }) {
  const { search = '', cursor } = await searchParams;
  const { ok, data } = await getConversations({ search, cursor });

  if (!ok) return <Alert message={data.message} />;

  const nextPage = data.next_cursor && `/inbox?${new URLSearchParams({ search, cursor: data.next_cursor })}`;

  return (
    <>
      <AutoRefresh />
      <PageHeader title="Inbox" />

      <p className="mb-4 max-w-2xl text-sm text-slate-600">
        Every WhatsApp chat with your customers: what they asked and what the bot replied. New messages show up here on their own.
      </p>

      <form className="mb-4 flex gap-2">
        <input
          name="search"
          defaultValue={search}
          placeholder="Search name or number"
          className="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2"
        />
        <Button variant="secondary">Search</Button>
      </form>

      <ConversationList conversations={data.data} />

      {nextPage && <Link href={nextPage} className="mt-4 inline-block text-sm underline">Older chats</Link>}
    </>
  );
}
