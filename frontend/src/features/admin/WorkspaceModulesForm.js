'use client';

import { useActionState } from 'react';
import { updateWorkspaceModules } from '@/features/admin/actions';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { MODULES } from '@/lib/modules';

// The super admin ticks which parts of the app this shop can use
export default function WorkspaceModulesForm({ workspace }) {
  const [state, formAction, pending] = useActionState(updateWorkspaceModules.bind(null, workspace.id, workspace.name), {});

  return (
    <form action={formAction} className="max-w-lg space-y-3">
      <Alert message={state.message} type={state.success ? 'success' : 'error'} />

      <div className="divide-y divide-slate-100 rounded-xl bg-white ring-1 ring-slate-200">
        {MODULES.map((module) => (
          <label key={module.key} className="flex cursor-pointer items-start gap-3 px-4 py-3">
            <input
              type="checkbox"
              name="modules"
              value={module.key}
              defaultChecked={workspace.modules.includes(module.key)}
              className="mt-1"
            />
            <span>
              <span className="block font-medium">{module.label}</span>
              <span className="block text-sm text-slate-500">{module.hint}</span>
            </span>
          </label>
        ))}
      </div>

      <Button pending={pending}>Save features</Button>
    </form>
  );
}
