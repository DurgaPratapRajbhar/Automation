// Sidebar links. A link shows only if the user has its permission (null = everyone).
// Add a line here when a new page is built.
export const MENU = [
  { label: 'Dashboard', href: '/dashboard', permission: null },
  { label: 'Customers', href: '/customers', permission: 'customers.view' },
  { label: 'Members', href: '/members', permission: 'members.view' },
  { label: 'Roles', href: '/roles', permission: 'roles.view' },
  { label: 'Shop profile', href: '/settings/profile', permission: 'workspace.view' },
];
