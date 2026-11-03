'use client';

import { useState } from 'react';
import AiDraftBox from '@/features/shop/campaigns/AiDraftBox';
import TemplateForm from '@/features/shop/whatsapp/templates/TemplateForm';

// The new-template form, with the AI box above it that fills the form in
export default function TemplateCreator({ action, canDraft }) {
  const [draft, setDraft] = useState(null);

  return (
    <div className="space-y-4">
      {canDraft && <AiDraftBox onDraft={setDraft} />}
      {/* A new draft re-mounts the form, so every field shows the new text */}
      <TemplateForm key={draft ? draft.body : 'blank'} action={action} draft={draft} />
    </div>
  );
}
