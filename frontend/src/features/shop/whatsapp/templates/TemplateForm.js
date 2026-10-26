'use client';

import { useActionState, useEffect, useRef, useState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';

const CATEGORIES = [
  { value: 'UTILITY', label: 'Utility: order update, bill, reminder (cheaper)' },
  { value: 'MARKETING', label: 'Marketing: offer, new stock, festival sale' },
];

const LANGUAGES = [
  { value: 'en', label: 'English' },
  { value: 'hi', label: 'Hindi' },
];

// How many {{n}} blanks the text has (Meta wants one example for each)
function blanksIn(text) {
  const numbers = new Set([...(text ?? '').matchAll(/\{\{(\d+)\}\}/g)].map((match) => match[1]));
  return numbers.size;
}

export default function TemplateForm({ action }) {
  const [state, formAction, pending] = useActionState(action, {});
  const values = state.values ?? {};
  const errors = state.errors ?? {};
  const [body, setBody] = useState(values.body ?? '');
  const formRef = useRef(null);

  // Clear the form after Meta accepted the template for review
  useEffect(() => {
    if (state.done) {
      formRef.current?.reset();
      setBody('');
    }
  }, [state.done]);

  const blanks = blanksIn(body);

  return (
    <form ref={formRef} action={formAction} className="space-y-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <h2 className="font-semibold">New template</h2>
      <Alert message={state.message} type={state.done ? 'success' : 'error'} />

      <Input
        label="Name (small letters, numbers and _ only)"
        name="name"
        placeholder="order_ready"
        defaultValue={values.name}
        error={errors.name}
        required
      />

      <div className="grid gap-4 sm:grid-cols-2">
        <Select label="Category" name="category" options={CATEGORIES} defaultValue={values.category ?? 'UTILITY'} error={errors.category} />
        <Select label="Language" name="language" options={LANGUAGES} defaultValue={values.language ?? 'en'} error={errors.language} />
      </div>

      <div className="space-y-1">
        <label htmlFor="template-body" className="block text-sm font-medium text-slate-700">Message text</label>
        <textarea
          id="template-body"
          name="body"
          rows={4}
          required
          maxLength={1024}
          value={body}
          onChange={(event) => setBody(event.target.value)}
          placeholder="Namaste {{1}}, your order {{2}} is ready. Please collect it today."
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
        />
        <p className="text-xs text-slate-500">Use {'{{1}}'}, {'{{2}}'}… for parts that change per customer (name, order number).</p>
        {errors.body && <p className="text-sm text-red-600">{errors.body[0]}</p>}
      </div>

      {blanks > 0 && (
        <fieldset className="space-y-2">
          <legend className="text-sm font-medium text-slate-700">Example values (Meta checks these)</legend>
          {Array.from({ length: blanks }, (_, index) => (
            <Input
              key={index}
              label={`Example for {{${index + 1}}}`}
              name="examples"
              defaultValue={values.examples?.[index]}
              error={errors[`examples.${index}`]}
              required
            />
          ))}
        </fieldset>
      )}

      <Button pending={pending}>Send to Meta for approval</Button>
    </form>
  );
}
