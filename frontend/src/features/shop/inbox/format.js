// Customer name, or the WhatsApp number when the profile has no name
export function customerLabel(conversation) {
  return conversation.customer_name || `+${conversation.customer_phone}`;
}

// Shop times are shown in India time, whatever the server zone is
export function formatTime(value) {
  if (!value) return '';

  return new Date(value).toLocaleString('en-IN', {
    timeZone: 'Asia/Kolkata',
    day: 'numeric',
    month: 'short',
    hour: 'numeric',
    minute: '2-digit',
  });
}
