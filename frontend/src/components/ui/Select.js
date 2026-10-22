// options: [{ value, label }]
export default function Select({ label, name, options, error, defaultValue, ...props }) {
  return (
    <label className="block space-y-1">
      {label && <span className="text-sm font-medium text-slate-700">{label}</span>}
      <select
        // A new key re-mounts the select, so a form reset keeps the value sent back by the action
        key={String(defaultValue)}
        name={name}
        defaultValue={defaultValue ?? undefined}
        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900"
        {...props}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>{option.label}</option>
        ))}
      </select>
      {error && <span className="text-sm text-red-600">{error[0]}</span>}
    </label>
  );
}
