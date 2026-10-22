import Link from 'next/link';

export default function CustomerTable({ customers }) {
  if (customers.length === 0) {
    return <p className="text-slate-500">No customers yet.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            <th className="px-4 py-3">Name</th>
            <th className="px-4 py-3">Phone</th>
            <th className="px-4 py-3">GSTIN</th>
            <th className="px-4 py-3">Credit limit</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {customers.map((customer) => (
            <tr key={customer.id} className="hover:bg-slate-50">
              <td className="px-4 py-3">
                <Link href={`/customers/${customer.id}`} className="font-medium hover:underline">{customer.name}</Link>
              </td>
              <td className="px-4 py-3">{customer.phone ?? '-'}</td>
              <td className="px-4 py-3">{customer.gstin ?? '-'}</td>
              <td className="px-4 py-3">{customer.credit_limit ? `₹${customer.credit_limit}` : '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
