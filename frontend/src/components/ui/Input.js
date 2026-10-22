// Text input with label and Laravel validation error (error = ['message'])
export default function Input({ label, name, error, defaultValue, ...props }) {
  return (
    <label className="block space-y-1">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      <input
        name={name}
        defaultValue={defaultValue ?? ''}
        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
        {...props}
      />
      {error && <span className="text-sm text-red-600">{error[0]}</span>}
    </label>
  );
}
