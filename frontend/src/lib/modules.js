// The parts of the app a shop can have switched on (the keys match the API's WorkspaceModule enum).
// label + hint are what the shopkeeper reads; href = the page the module opens.
export const MODULES = [
  { key: 'inbox', label: 'Inbox', hint: 'See customer chats and reply.', href: '/inbox', icon: 'inbox' },
  { key: 'whatsapp', label: 'WhatsApp', hint: 'Connect your business’s WhatsApp number.', href: '/settings/whatsapp', icon: 'whatsapp' },
  { key: 'knowledge', label: 'Knowledge', hint: 'Teach the AI what you offer, your prices and timings.', href: '/knowledge', icon: 'knowledge' },
  { key: 'customers', label: 'Customers', hint: 'Your customer list and their details.', href: '/customers', icon: 'customers' },
  { key: 'team', label: 'Staff & roles', hint: 'Add staff and choose what each can do.', href: '/members', icon: 'members' },
];

// Starting set of a shop that signs up by itself (the API decides this; used only for the wording)
export const STARTER_MODULES = ['inbox', 'whatsapp', 'knowledge'];
