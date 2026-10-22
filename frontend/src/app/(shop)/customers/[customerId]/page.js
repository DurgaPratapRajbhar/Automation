import Alert from '@/components/ui/Alert';
import ActionButton from '@/components/ui/ActionButton';
import PageHeader from '@/components/ui/PageHeader';
import CustomerForm from '@/features/shop/customers/CustomerForm';
import { deleteCustomer, saveCustomer } from '@/features/shop/customers/actions';
import { getCustomer } from '@/features/shop/customers/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function CustomerPage({ params }) {
  const { customerId } = await params;
  const { ok, data } = await getCustomer(customerId);

  if (!ok) return <Alert message={data.message} />;

  const customer = data.data;

  return (
    <>
      <PageHeader title={customer.name} />

      {(await can('customers.edit')) ? (
        <CustomerForm action={saveCustomer.bind(null, customer.id)} customer={customer} />
      ) : (
        <p className="text-slate-600">{customer.phone ?? 'No phone'} · {customer.address ?? 'No address'}</p>
      )}

      {(await can('customers.delete')) && (
        <div className="mt-8">
          <ActionButton action={deleteCustomer.bind(null, customer.id)} label="Delete customer" confirm={`Delete ${customer.name}?`} />
        </div>
      )}
    </>
  );
}
