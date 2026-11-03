// Plain words for what the API sends
export const KINDS = [
  { value: 'payment', label: 'Payment' },
  { value: 'documents', label: 'Documents / things' },
  { value: 'callback', label: 'Call back / visit' },
  { value: 'other', label: 'Other' },
];

export const kindLabel = (kind) => KINDS.find((item) => item.value === kind)?.label ?? kind;

export const STATUS_TABS = [
  { value: 'open', label: 'Open' },
  { value: 'done', label: 'Done' },
  { value: 'missed', label: 'Not coming' },
];

// overdue / today / later: how the Open list is grouped
export const TIMING = {
  overdue: { label: 'Late', tone: 'bg-red-100 text-red-700' },
  today: { label: 'Today', tone: 'bg-amber-100 text-amber-800' },
  later: { label: 'Later', tone: 'bg-slate-100 text-slate-600' },
};

export const formatDate = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
