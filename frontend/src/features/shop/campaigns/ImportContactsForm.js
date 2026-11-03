'use client';

import Link from 'next/link';
import { useState, useTransition } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { fieldClass } from '@/components/ui/Input';
import { importContacts, previewContacts } from '@/features/shop/campaigns/actions';

// Import contacts: 1) choose the file, 2) check what is in it and pick the phone and name columns, 3) import
export default function ImportContactsForm() {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [phoneColumn, setPhoneColumn] = useState('');
  const [nameColumn, setNameColumn] = useState('');
  const [consent, setConsent] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function choose(event) {
    const chosen = event.target.files[0];

    setFile(chosen ?? null);
    setPreview(null);
    setResult(null);
    setError(null);

    if (!chosen) return;

    const formData = new FormData();
    formData.set('file', chosen);

    // The file is looked at as soon as it is chosen; nothing is saved yet
    startTransition(async () => {
      const response = await previewContacts(formData);

      if (response.message) return setError(response.message);

      setPreview(response.preview);
      setPhoneColumn(response.preview.suggested.phone_column ?? '');
      setNameColumn(response.preview.suggested.name_column ?? '');
    });
  }

  function save() {
    const formData = new FormData();
    formData.set('file', file);
    formData.set('phone_column', phoneColumn);
    if (nameColumn) formData.set('name_column', nameColumn);
    formData.set('consent', consent ? '1' : '0');

    setError(null);

    startTransition(async () => {
      const response = await importContacts(formData);

      if (response.message && !response.result) return setError(response.message);

      setResult(response.result);
      setPreview(null);
    });
  }

  if (result) return <ImportResult result={result} />;

  return (
    <div className="max-w-3xl space-y-5">
      <div className="space-y-2">
        <label htmlFor="contacts-file" className="block text-sm font-medium text-slate-700">Excel or CSV file</label>
        <input id="contacts-file" type="file" accept=".xlsx,.csv" onChange={choose} className="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white" />
        <p className="text-sm text-slate-500">The first row must have the column titles (like Name, Phone, Amount). Up to 5,000 people at a time.</p>
      </div>

      <Alert message={error} />
      {pending && !preview && <p className="text-sm text-slate-500">Reading the file…</p>}

      {preview && (
        <>
          <section className="space-y-3">
            <h2 className="font-semibold">{preview.total_rows} people found. Check the columns.</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="space-y-1 text-sm font-medium text-slate-700">
                Which column is the phone number?
                <select value={phoneColumn} onChange={(event) => setPhoneColumn(event.target.value)} className={fieldClass}>
                  <option value="">Choose…</option>
                  {preview.headers.map((title) => <option key={title} value={title}>{title}</option>)}
                </select>
              </label>
              <label className="space-y-1 text-sm font-medium text-slate-700">
                Which column is the name?
                <select value={nameColumn} onChange={(event) => setNameColumn(event.target.value)} className={fieldClass}>
                  <option value="">No name column</option>
                  {preview.headers.map((title) => <option key={title} value={title}>{title}</option>)}
                </select>
              </label>
            </div>

            <SampleTable headers={preview.headers} rows={preview.sample} phoneColumn={phoneColumn} nameColumn={nameColumn} />
          </section>

          <label className="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" checked={consent} onChange={(event) => setConsent(event.target.checked)} className="mt-1" />
            <span>I have these people&apos;s permission to message them on WhatsApp. (Without this tick they are saved, but cannot get a campaign.)</span>
          </label>

          <Button type="button" onClick={save} pending={pending} disabled={!phoneColumn}>
            Import {preview.total_rows} people
          </Button>
        </>
      )}
    </div>
  );
}

function SampleTable({ headers, rows, phoneColumn, nameColumn }) {
  return (
    <div className="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-slate-500">
          <tr>
            {headers.map((title) => (
              <th key={title} className="whitespace-nowrap px-4 py-2">
                {title}
                {title === phoneColumn && <span className="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">phone</span>}
                {title === nameColumn && <span className="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">name</span>}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((row, index) => (
            <tr key={index}>
              {headers.map((title) => <td key={title} className="whitespace-nowrap px-4 py-2">{row[title]}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ImportResult({ result }) {
  return (
    <div className="max-w-3xl space-y-4">
      <p className="rounded-lg bg-green-50 px-3 py-2 text-green-800">
        Done. <strong>{result.created}</strong> new and <strong>{result.updated}</strong> updated
        {result.skipped > 0 && <>, <strong>{result.skipped}</strong> left out</>}.
      </p>

      {result.problems.length > 0 && (
        <section className="space-y-2">
          <h2 className="font-semibold">Rows that could not be used</h2>
          <ul className="space-y-1 text-sm text-slate-700">
            {result.problems.map((problem) => <li key={problem.row}>Row {problem.row}: {problem.reason}</li>)}
          </ul>
        </section>
      )}

      <div className="flex gap-3">
        <Link href="/campaigns/new" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Create a campaign</Link>
        <Link href="/campaigns/import" className="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100">Import another file</Link>
      </div>
    </div>
  );
}
