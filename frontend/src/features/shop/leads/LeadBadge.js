// "Hot lead" / "Warm" / "Cold" / "Asking questions": what the Sales agent found about a person
const STATUSES = {
  hot: { label: 'Hot lead', tone: 'bg-red-100 text-red-700' },
  warm: { label: 'Warm', tone: 'bg-amber-100 text-amber-800' },
  cold: { label: 'Cold', tone: 'bg-slate-100 text-slate-600' },
  qualifying: { label: 'Asking questions', tone: 'bg-sky-100 text-sky-700' },
};

export default function LeadBadge({ status }) {
  const lead = STATUSES[status];

  return lead ? <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${lead.tone}`}>{lead.label}</span> : null;
}
