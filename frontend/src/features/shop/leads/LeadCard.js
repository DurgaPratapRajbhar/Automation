import LeadBadge from '@/features/shop/leads/LeadBadge';

// Above the chat: who this person is and what they answered to your questions
export default function LeadCard({ conversation }) {
  if (!conversation.lead_status) return null;

  const answers = conversation.lead_answers ?? [];

  return (
    <section className="space-y-2 rounded-xl bg-white p-4 ring-1 ring-slate-200">
      <div className="flex items-center gap-2">
        <h2 className="font-semibold">New customer</h2>
        <LeadBadge status={conversation.lead_status} />
      </div>

      {conversation.lead_summary && <p className="text-sm text-slate-700">{conversation.lead_summary}</p>}

      <dl className="space-y-1 text-sm">
        {answers.map((item) => (
          <div key={item.question}>
            <dt className="text-slate-500">{item.question}</dt>
            <dd className="font-medium">{item.answer ?? <span className="font-normal text-slate-400">Not answered yet</span>}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}
