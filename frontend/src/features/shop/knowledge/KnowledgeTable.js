import Link from 'next/link';

// AI ne padha ya nahi, ek chhota badge
function StatusBadge({ status }) {
  const ready = status === 'ready';
  return (
    <span className={`rounded-full px-2 py-0.5 text-xs ${ready ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
      {ready ? 'Learned' : 'Not learned yet'}
    </span>
  );
}

export default function KnowledgeTable({ sources }) {
  if (sources.length === 0) {
    return <p className="text-slate-500">No knowledge added yet. Tell the bot about your shop.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            <th className="px-4 py-3">Title</th>
            <th className="px-4 py-3">Type</th>
            <th className="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {sources.map((source) => (
            <tr key={source.id} className="hover:bg-slate-50">
              <td className="px-4 py-3">
                <Link href={`/knowledge/${source.id}`} className="font-medium hover:underline">{source.title}</Link>
                {source.file_name && <p className="text-slate-500">{source.file_name}</p>}
              </td>
              <td className="px-4 py-3">{source.type === 'file' ? 'File' : 'Text'}</td>
              <td className="px-4 py-3"><StatusBadge status={source.status} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
