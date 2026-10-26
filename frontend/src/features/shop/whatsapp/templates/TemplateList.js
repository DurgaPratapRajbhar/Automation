import ActionButton from '@/components/ui/ActionButton';
import { deleteTemplate } from '@/features/shop/whatsapp/templates/actions';

const STATUS_STYLE = {
  APPROVED: 'bg-green-100 text-green-700',
  PENDING: 'bg-amber-100 text-amber-700',
  REJECTED: 'bg-red-100 text-red-700',
};

export default function TemplateList({ channelId, templates, canDelete }) {
  if (templates.length === 0) {
    return <p className="text-slate-500">No templates yet. Create one below; Meta usually approves it within minutes to a day.</p>;
  }

  return (
    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
      {templates.map((template) => (
        <li key={`${template.name}-${template.language}`} className="flex items-start gap-3 px-4 py-3">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <p className="font-medium">{template.name}</p>
              <span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLE[template.status] ?? 'bg-slate-100 text-slate-600'}`}>{template.status}</span>
              <span className="text-xs text-slate-500">{template.category} · {template.language}</span>
            </div>
            <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{template.body}</p>
          </div>
          {canDelete && (
            <ActionButton
              action={deleteTemplate.bind(null, channelId, template.name)}
              label="Delete"
              confirm={`Delete template ${template.name}?`}
            />
          )}
        </li>
      ))}
    </ul>
  );
}
