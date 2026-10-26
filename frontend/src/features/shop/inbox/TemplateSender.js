'use client';

import { useActionState, useEffect, useState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';

function blanksIn(text) {
  return new Set([...(text ?? '').matchAll(/\{\{(\d+)\}\}/g)].map((match) => match[1])).size;
}

// Pick an approved template, fill its blanks, see the final text, send
export default function TemplateSender({ action, templates, open }) {
  const [state, formAction, pending] = useActionState(action, {});
  const [selected, setSelected] = useState(templates[0] ?? null);
  const [values, setValues] = useState([]);

  // Start fresh after a successful send
  useEffect(() => {
    if (state.sent) setValues([]);
  }, [state.sent]);

  if (templates.length === 0) {
    return <p className="text-sm text-slate-500">No approved template yet. Create one in WhatsApp → Templates and wait for Meta to approve it.</p>;
  }

  const blanks = blanksIn(selected?.body);
  const preview = (selected?.body ?? '').replace(/\{\{(\d+)\}\}/g, (all, n) => values[n - 1] || all);

  function choose(event) {
    setSelected(templates.find((t) => `${t.name}|${t.language}` === event.target.value) ?? null);
    setValues([]);
  }

  return (
    <details open={open} className="rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <summary className="cursor-pointer font-medium">Send a template</summary>

      <form action={formAction} className="mt-3 space-y-3">
        <Alert message={state.message} />

        <div className="space-y-1">
          <label htmlFor="template-pick" className="block text-sm font-medium text-slate-700">Template</label>
          <select
            id="template-pick"
            name="template"
            value={selected ? `${selected.name}|${selected.language}` : ''}
            onChange={choose}
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
          >
            {templates.map((t) => (
              <option key={`${t.name}|${t.language}`} value={`${t.name}|${t.language}`}>{t.name} ({t.language}, {t.category})</option>
            ))}
          </select>
        </div>

        {/* Plain controlled inputs: the preview below updates as you type */}
        {Array.from({ length: blanks }, (_, index) => (
          <div key={`${selected?.name}-${index}`} className="space-y-1">
            <label htmlFor={`template-value-${index}`} className="block text-sm font-medium text-slate-700">{`Value for {{${index + 1}}}`}</label>
            <input
              id={`template-value-${index}`}
              name="parameters"
              required
              value={values[index] ?? ''}
              onChange={(event) => setValues((current) => Object.assign([...current], { [index]: event.target.value }))}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
            />
            {state.errors?.[`parameters.${index}`] && <p className="text-sm text-red-600">{state.errors[`parameters.${index}`][0]}</p>}
          </div>
        ))}

        <div className="rounded-lg bg-emerald-50 p-3 text-sm ring-1 ring-emerald-200">
          <p className="mb-1 text-xs font-semibold text-emerald-700">Customer will get</p>
          <p className="whitespace-pre-wrap">{preview}</p>
        </div>

        <Button pending={pending}>Send template</Button>
      </form>
    </details>
  );
}
