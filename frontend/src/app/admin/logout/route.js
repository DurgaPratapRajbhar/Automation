import { ADMIN_COOKIES } from '@/lib/cookies';
import { logout } from '@/lib/logout';

// Super admin logout (the shop session, if any, is not touched)
export function GET(request) {
  return logout(request, ADMIN_COOKIES, '/admin/login');
}
