const STYLES = {
  error: 'bg-red-50 text-red-700',
  success: 'bg-green-50 text-green-700',
};

export default function Alert({ message, type = 'error' }) {
  if (!message) return null;

  return <p className={`rounded-lg px-3 py-2 text-sm ${STYLES[type]}`}>{message}</p>;
}
