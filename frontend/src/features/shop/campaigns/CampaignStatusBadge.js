const LOOK = {
  draft: ['Ready to start', 'bg-slate-100 text-slate-700'],
  sending: ['Sending', 'bg-amber-100 text-amber-800'],
  done: ['Finished', 'bg-green-100 text-green-700'],
  cancelled: ['Stopped', 'bg-red-100 text-red-700'],
};

export default function CampaignStatusBadge({ status }) {
  const [label, classes] = LOOK[status] ?? [status, 'bg-slate-100 text-slate-700'];

  return <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${classes}`}>{label}</span>;
}
