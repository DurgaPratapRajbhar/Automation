import { NextResponse } from 'next/server';
import { COOKIES } from '@/lib/cookies';

// Logs out: revokes the refresh token on the server and clears the cookies
export async function GET(request) {
  const refreshToken = request.cookies.get(COOKIES.refresh)?.value;

  if (refreshToken) {
    await fetch(`${process.env.BACKEND_URL}/api/auth/logout`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refreshToken }),
    }).catch(() => {});
  }

  const response = NextResponse.redirect(new URL('/login', request.url));
  Object.values(COOKIES).forEach((name) => response.cookies.delete(name));

  return response;
}
