import { NextResponse } from 'next/server';
import { ADMIN_COOKIES, COOKIES, COOKIE_OPTIONS, THIRTY_DAYS } from '@/lib/cookies';

// Two separate areas, each with its own login and session cookies
const AREAS = {
  shop: { cookies: COOKIES, guestPages: ['/login'], login: '/login', home: '/dashboard', logout: '/logout' },
  admin: { cookies: ADMIN_COOKIES, guestPages: ['/admin/login'], login: '/admin/login', home: '/admin', logout: '/admin/logout' },
};

// Runs before every page: guards pages and refreshes an expired access token
export async function proxy(request) {
  const { pathname } = request.nextUrl;
  const area = pathname.startsWith('/admin') ? AREAS.admin : AREAS.shop;
  const refreshToken = request.cookies.get(area.cookies.refresh)?.value;
  const isGuestPage = area.guestPages.includes(pathname);

  if (!refreshToken) {
    return isGuestPage ? NextResponse.next() : NextResponse.redirect(new URL(area.login, request.url));
  }

  if (isGuestPage) {
    return NextResponse.redirect(new URL(area.home, request.url));
  }

  if (request.cookies.has(area.cookies.access)) {
    return NextResponse.next();
  }

  // Access token expired: get a new pair with the refresh token
  const res = await fetch(`${process.env.BACKEND_URL}/api/auth/refresh`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh_token: refreshToken }),
  });

  if (!res.ok) {
    return NextResponse.redirect(new URL(area.logout, request.url));
  }

  const { data } = await res.json();

  // New token for this request (server components) and for the browser
  request.cookies.set(area.cookies.access, data.access_token);
  request.cookies.set(area.cookies.refresh, data.refresh_token);

  const response = NextResponse.next({ request: { headers: request.headers } });
  response.cookies.set(area.cookies.access, data.access_token, { ...COOKIE_OPTIONS, maxAge: data.expires_in });
  response.cookies.set(area.cookies.refresh, data.refresh_token, { ...COOKIE_OPTIONS, maxAge: THIRTY_DAYS });

  return response;
}

export const config = {
  // Pages only, not static files or the two logout routes
  matcher: ['/((?!_next|favicon.ico|logout|admin/logout).*)'],
};
