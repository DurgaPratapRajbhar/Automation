import { COOKIES } from '@/lib/cookies';
import { logout } from '@/lib/logout';

// Shop logout
export function GET(request) {
  return logout(request, COOKIES, '/login');
}
