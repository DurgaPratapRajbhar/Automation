// Sidebar links. A link shows only if the user has its permission (null = everyone).
// icon = a name from components/ui/Icon.js, hint = one easy line shown on the dashboard cards.
// Add a line here when a new page is built.
export const MENU = [
  { label: 'Dashboard', href: '/dashboard', permission: null, icon: 'dashboard', hint: null },
  { label: 'Inbox', href: '/inbox', permission: 'conversations.view', icon: 'inbox', hint: 'See customer chats and reply.' },
  { label: 'WhatsApp', href: '/settings/whatsapp', permission: 'channels.view', icon: 'whatsapp', hint: 'Connect your shop’s WhatsApp number.' },
  { label: 'Customers', href: '/customers', permission: 'customers.view', icon: 'customers', hint: 'Your customer list and their details.' },
  { label: 'Knowledge', href: '/knowledge', permission: 'knowledge-sources.view', icon: 'knowledge', hint: 'Teach the AI your products, prices and timings.' },
  { label: 'Members', href: '/members', permission: 'members.view', icon: 'members', hint: 'Add staff who can use the app.' },
  { label: 'Roles', href: '/roles', permission: 'roles.view', icon: 'roles', hint: 'Choose what each staff member can do.' },
];

// Links in the profile menu at the top right (same permission rule as MENU)
export const ACCOUNT_MENU = [
  { label: 'Shop profile', href: '/settings/profile', permission: 'workspace.view', icon: 'shop' },
  { label: 'Password', href: '/settings/password', permission: null, icon: 'password' },
];
