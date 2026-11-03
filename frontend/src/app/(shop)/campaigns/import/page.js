import Link from 'next/link';
import PageHeader from '@/components/ui/PageHeader';
import ImportContactsForm from '@/features/shop/campaigns/ImportContactsForm';

export const metadata = { title: 'Import contacts' };

export default function ImportContactsPage() {
  return (
    <div className="space-y-4">
      <Link href="/campaigns" className="text-sm text-slate-500 underline">Back to campaigns</Link>
      <PageHeader title="Import contacts" />
      <p className="max-w-2xl text-sm text-slate-600">
        Upload an Excel file of the people you want to reach: customers, students, clients, leads. Every column you add (amount, date, class...)
        can be used in your message.
      </p>
      <ImportContactsForm />
    </div>
  );
}
