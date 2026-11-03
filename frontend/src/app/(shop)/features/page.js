import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import Icon from '@/components/ui/Icon';
import PageHeader from '@/components/ui/PageHeader';
import { getWorkspaceAccess } from '@/features/shop/workspace/queries';
import { CONTACT_PHONE, contactWhatsAppLink } from '@/lib/contact';
import { MODULES } from '@/lib/modules';

export const metadata = { title: 'Features' };

// Every part of the app: what this shop already has, and how to ask ScriptDP for the rest
export default async function FeaturesPage() {
  const { ok, data } = await getWorkspaceAccess();

  if (!ok) return <Alert message={data.message} />;

  const { workspace, modules } = data.data;

  return (
    <div className="space-y-6">
      <PageHeader title="Features" />
      <p className="-mt-3 max-w-2xl text-slate-600">
        Here is everything the app can do. What is on for your business is ready to use. For anything else, message ScriptDP on
        WhatsApp {CONTACT_PHONE} and we will switch it on for you.
      </p>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {MODULES.map((module) => {
          const isOn = modules.includes(module.key);

          return (
            <div key={module.key} className="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
              <div className="flex items-start gap-4">
                <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg ${isOn ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'}`}>
                  <Icon name={module.icon} size={22} />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">{module.label}</p>
                  <p className="mt-0.5 text-sm text-slate-600">{module.hint}</p>
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ${isOn ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                  {isOn ? 'On' : 'Not on'}
                </span>
              </div>

              {isOn ? (
                <Link href={module.href} className="text-sm font-medium text-emerald-700 hover:underline">Open {module.label} →</Link>
              ) : (
                <a
                  href={contactWhatsAppLink(`Hi ScriptDP! Please switch on "${module.label}" for my shop "${workspace.name}".`)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-sm font-medium text-emerald-700 hover:underline"
                >
                  Ask ScriptDP on WhatsApp →
                </a>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
