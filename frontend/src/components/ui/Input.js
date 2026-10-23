import { useId } from 'react';

// Text input with label and Laravel validation error (error = ['message']).
// label is linked by id, so the field's accessible name is exactly the label text.
export default function Input({ label, name, error, defaultValue, ...props }) {
  const id = useId();

  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">{label}</label>
      <input
        id={id}
        name={name}
        defaultValue={defaultValue ?? ''}
        aria-invalid={error ? true : undefined}
        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
        {...props}
      />
      {error && <p className="text-sm text-red-600">{error[0]}</p>}
    </div>
  );
}
