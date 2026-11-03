// How a shopkeeper reaches ScriptDP (to switch on a feature, or to continue after the trial)
export const CONTACT_PHONE = process.env.CONTACT_PHONE || '83569 74324';

export function contactWhatsAppLink(message) {
  const digits = CONTACT_PHONE.replace(/\D/g, '');
  const number = digits.length === 10 ? `91${digits}` : digits;

  return `https://wa.me/${number}?text=${encodeURIComponent(message)}`;
}
