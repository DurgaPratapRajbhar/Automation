export const STATUS_TABS = [
  { value: 'all', label: 'All' },
  { value: 'draft', label: 'Waiting for your OK' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'cancelled', label: 'Cancelled' },
];

export const STATUS_LOOK = {
  draft: ['Waiting for your OK', 'bg-amber-100 text-amber-800'],
  confirmed: ['Confirmed', 'bg-green-100 text-green-700'],
  cancelled: ['Cancelled', 'bg-slate-100 text-slate-600'],
};
