import Link from 'next/link';
import ActionButton from '@/components/ui/ActionButton';
import { deleteChannel } from '@/features/shop/whatsapp/actions';

export default function ChannelList({ channels, canDelete, canTemplates }) {
  if (channels.length === 0) {
    return <p className="text-slate-500">No WhatsApp number connected yet.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            <th className="px-4 py-3">Number</th>
            <th className="px-4 py-3">Phone number ID</th>
            <th className="px-4 py-3">Status</th>
            <th className="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {channels.map((channel) => (
            <tr key={channel.id}>
              <td className="px-4 py-3 font-medium">{channel.name}</td>
              <td className="px-4 py-3 text-slate-500">{channel.phone_number_id}</td>
              <td className="px-4 py-3">
                <span className={channel.status === 'connected' ? 'text-green-700' : 'text-red-700'}>{channel.status}</span>
              </td>
              <td className="space-x-3 px-4 py-3 text-right">
                {canTemplates && (
                  <Link href={`/settings/whatsapp/${channel.id}/templates`} className="text-sm font-medium underline">Templates</Link>
                )}
                {canDelete && (
                  <ActionButton
                    action={deleteChannel.bind(null, channel.id)}
                    label="Remove"
                    confirm={`Remove ${channel.name}? The bot stops answering on this number.`}
                  />
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
