import Link from 'next/link';
import { buttonClass } from '@/components/ui/Button';

// Page title with an optional link button on the right, e.g. "New customer"
export default function PageHeader({ title, actionLabel, actionHref }) {
  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-2xl font-semibold">{title}</h1>
      {actionHref && <Link href={actionHref} className={buttonClass()}>{actionLabel}</Link>}
    </div>
  );
}
