import Alert from '@/components/ui/Alert';
import ActionButton from '@/components/ui/ActionButton';
import PageHeader from '@/components/ui/PageHeader';
import KnowledgeForm from '@/features/shop/knowledge/KnowledgeForm';
import { deleteKnowledge, updateKnowledge } from '@/features/shop/knowledge/actions';
import { getKnowledgeSource } from '@/features/shop/knowledge/queries';
import { can } from '@/features/shop/workspace/queries';

export default async function KnowledgeSourcePage({ params }) {
  const { knowledgeSourceId } = await params;
  const { ok, data } = await getKnowledgeSource(knowledgeSourceId);

  if (!ok) return <Alert message={data.message} />;

  const source = data.data;

  return (
    <>
      <PageHeader title={source.title} />

      {(await can('knowledge-sources.edit')) ? (
        <KnowledgeForm action={updateKnowledge.bind(null, source.id)} source={source} />
      ) : (
        <p className="whitespace-pre-wrap text-slate-600">{source.content ?? source.file_name}</p>
      )}

      {(await can('knowledge-sources.delete')) && (
        <div className="mt-8">
          <ActionButton action={deleteKnowledge.bind(null, source.id)} label="Delete knowledge" confirm={`Delete "${source.title}"?`} />
        </div>
      )}
    </>
  );
}
