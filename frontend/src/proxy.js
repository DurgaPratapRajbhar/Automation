import { NextResponse } from 'next/server';
import { COOKIES, COOKIE_OPTIONS, THIRTY_DAYS } from '@/lib/cookies';

const GUEST_PAGES = ['/login', '/register'];

// Runs before every page: guards pages and refreshes an expired access token
export async function proxy(request) {
  const { pathname } = request.nextUrl;
  const refreshToken = request.cookies.get(COOKIES.refresh)?.value;
  const isGuestPage = GUEST_PAGES.includes(pathname);

  if (!refreshToken) {
    return isGuestPage ? NextResponse.next() : NextResponse.redirect(new URL('/login', request.url));
  }

  if (isGuestPage) {
    return NextResponse.redirect(new URL('/dashboard', request.url));
  }

  if (request.cookies.has(COOKIES.access)) {
    return NextResponse.next();
  }

  // Access token expired: get a new pair with the refresh token
  const res = await fetch(`${process.env.BACKEND_URL}/api/auth/refresh`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh_token: refreshToken }),
  });

  if (!res.ok) {
    return NextResponse.redirect(new URL('/logout', request.url));
  }

  const { data } = await res.json();

  // New token for this request (server components) and for the browser
  request.cookies.set(COOKIES.access, data.access_token);
  request.cookies.set(COOKIES.refresh, data.refresh_token);

  const response = NextResponse.next({ request: { headers: request.headers } });
  response.cookies.set(COOKIES.access, data.access_token, { ...COOKIE_OPTIONS, maxAge: data.expires_in });
  response.cookies.set(COOKIES.refresh, data.refresh_token, { ...COOKIE_OPTIONS, maxAge: THIRTY_DAYS });

  return response;
}

export const config = {
  // Pages only, not static files or /logout
  matcher: ['/((?!_next|favicon.ico|logout).*)'],
};
