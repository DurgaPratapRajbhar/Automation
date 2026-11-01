import { redirectTo } from '@/lib/redirect';

// Revokes the refresh token on the server, clears one area's cookies and goes to its login page
export async function logout(request, names, loginPath) {
  const refreshToken = request.cookies.get(names.refresh)?.value;

  if (refreshToken) {
    await fetch(`${process.env.BACKEND_URL}/api/auth/logout`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refreshToken }),
    }).catch(() => {});
  }

  const response = redirectTo(loginPath);
  Object.values(names).forEach((name) => response.cookies.delete(name));

  return response;
}
