import PageHeader from '@/components/ui/PageHeader';
import KnowledgeForm from '@/features/shop/knowledge/KnowledgeForm';
import { createKnowledge } from '@/features/shop/knowledge/actions';

export default function NewKnowledgePage() {
  return (
    <>
      <PageHeader title="Add knowledge" />
      <KnowledgeForm action={createKnowledge} />
    </>
  );
}
