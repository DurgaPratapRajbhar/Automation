'use client';

import { useActionState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import { saveLeadQuestions } from '@/features/shop/leads/actions';

// The Sales agent: when a new person writes, it asks these questions one by one and tells you who is ready to buy.
export default function LeadQuestionsForm({ profile, canEdit }) {
  const [state, formAction, pending] = useActionState(saveLeadQuestions, {});
  const errors = state.errors ?? {};
  const slots = Array.from({ length: profile.max_lead_questions }, (_, index) => index);

  return (
    <form action={formAction} className="max-w-lg space-y-4">
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      <fieldset disabled={!canEdit} className="space-y-4">
        <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
          <input type="checkbox" name="lead_flow_enabled" defaultChecked={profile.lead_flow_enabled} className="h-4 w-4" />
          Ask new customers my questions and find who is ready to buy
        </label>

        {slots.map((index) => (
          <Input
            key={index}
            label={`Question ${index + 1}`}
            name={`question_${index + 1}`}
            defaultValue={profile.lead_questions[index]}
            error={errors[`lead_questions.${index}`]}
            placeholder={['What do you need?', 'What is your budget?', 'When do you need it?'][index]}
            maxLength={200}
          />
        ))}
      </fieldset>

      {canEdit && <Button pending={pending}>Save</Button>}
    </form>
  );
}
