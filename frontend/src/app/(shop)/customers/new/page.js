import PageHeader from '@/components/ui/PageHeader';
import CustomerForm from '@/features/shop/customers/CustomerForm';
import { saveCustomer } from '@/features/shop/customers/actions';

export default function NewCustomerPage() {
  return (
    <>
      <PageHeader title="New customer" />
      <CustomerForm action={saveCustomer.bind(null, null)} />
    </>
  );
}
