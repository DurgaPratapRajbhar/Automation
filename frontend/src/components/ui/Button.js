const VARIANTS = {
  primary: 'bg-slate-900 text-white hover:bg-slate-700',
  secondary: 'bg-white text-slate-900 ring-1 ring-slate-300 hover:bg-slate-100',
  danger: 'bg-red-600 text-white hover:bg-red-500',
};

export const buttonClass = (variant = 'primary') =>
  `inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-60 ${VARIANTS[variant]}`;

export default function Button({ children, pending, variant, className = '', ...props }) {
  return (
    <button disabled={pending} className={`${buttonClass(variant)} ${className}`} {...props}>
      {pending ? 'Please wait...' : children}
    </button>
  );
}
