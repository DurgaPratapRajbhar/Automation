import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import CustomerTable from '@/features/shop/customers/CustomerTable';
import { getCustomers } from '@/features/shop/customers/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function CustomersPage({ searchParams }) {
  const { search = '', cursor } = await searchParams;
  const { ok, data } = await getCustomers({ search, cursor });

  if (!ok) return <Alert message={data.message} />;

  const nextPage = data.next_cursor && `/customers?${new URLSearchParams({ search, cursor: data.next_cursor })}`;

  return (
    <>
      <PageHeader
        title="Customers"
        actionLabel="New customer"
        actionHref={(await can('customers.create')) && '/customers/new'}
      />

      <form className="mb-4 flex gap-2">
        <input
          name="search"
          defaultValue={search}
          placeholder="Search name or phone"
          className="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2"
        />
        <Button variant="secondary">Search</Button>
      </form>

      <CustomerTable customers={data.data} />

      {nextPage && <Link href={nextPage} className="mt-4 inline-block text-sm underline">Next page</Link>}
    </>
  );
}
