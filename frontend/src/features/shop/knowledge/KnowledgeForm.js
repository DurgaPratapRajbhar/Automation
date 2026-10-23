'use client';

import { useActionState, useState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

// Create: pick text or file. Edit: type is fixed; only text sources show the content box.
export default function KnowledgeForm({ action, source }) {
  const editing = Boolean(source);
  const [state, formAction, pending] = useActionState(action, {});
  const values = { ...source, ...state.values };
  const errors = state.errors ?? {};
  const [type, setType] = useState(values.type ?? 'text');

  return (
    <form action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} />

      <Input label="Title" name="title" defaultValue={values.title} error={errors.title} required />

      {!editing && (
        <fieldset className="space-y-2">
          <legend className="text-sm font-medium text-slate-700">Type</legend>
          <div className="flex gap-4 text-sm">
            <label className="flex items-center gap-2">
              <input type="radio" name="type" value="text" checked={type === 'text'} onChange={() => setType('text')} />
              Write text
            </label>
            <label className="flex items-center gap-2">
              <input type="radio" name="type" value="file" checked={type === 'file'} onChange={() => setType('file')} />
              Upload a file
            </label>
          </div>
        </fieldset>
      )}

      {type === 'text' ? (
        <div className="space-y-1">
          <label htmlFor="content" className="block text-sm font-medium text-slate-700">About your shop</label>
          <textarea
            id="content"
            name="content"
            rows={8}
            defaultValue={values.content ?? ''}
            placeholder="What you sell, timings, offers, delivery, return policy..."
            aria-invalid={errors.content ? true : undefined}
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
          />
          {errors.content && <p className="text-sm text-red-600">{errors.content[0]}</p>}
        </div>
      ) : (
        !editing && (
          <div className="space-y-1">
            <label htmlFor="file" className="block text-sm font-medium text-slate-700">File (PDF, Word, text, CSV — max 10 MB)</label>
            <input
              id="file"
              name="file"
              type="file"
              accept=".pdf,.txt,.doc,.docx,.csv,.md"
              aria-invalid={errors.file ? true : undefined}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
            />
            {errors.file && <p className="text-sm text-red-600">{errors.file[0]}</p>}
          </div>
        )
      )}

      <Button pending={pending}>Save</Button>
    </form>
  );
}
