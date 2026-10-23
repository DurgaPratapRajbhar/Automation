import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import KnowledgeTable from '@/features/shop/knowledge/KnowledgeTable';
import { getKnowledgeSources } from '@/features/shop/knowledge/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function KnowledgePage({ searchParams }) {
  const { search = '', cursor } = await searchParams;
  const { ok, data } = await getKnowledgeSources({ search, cursor });

  if (!ok) return <Alert message={data.message} />;

  const nextPage = data.next_cursor && `/knowledge?${new URLSearchParams({ search, cursor: data.next_cursor })}`;

  return (
    <>
      <PageHeader
        title="Shop knowledge"
        actionLabel="Add knowledge"
        actionHref={(await can('knowledge-sources.create')) && '/knowledge/new'}
      />

      <p className="mb-4 max-w-2xl text-sm text-slate-600">
        Tell the bot about your shop — what you sell, timings, prices, offers. The more you add, the better it answers your customers.
      </p>

      <form className="mb-4 flex gap-2">
        <input
          name="search"
          defaultValue={search}
          placeholder="Search by title"
          className="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2"
        />
        <Button variant="secondary">Search</Button>
      </form>

      <KnowledgeTable sources={data.data} />

      {nextPage && <Link href={nextPage} className="mt-4 inline-block text-sm underline">Next page</Link>}
    </>
  );
}
