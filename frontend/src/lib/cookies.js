// Cookie names and options, shared by server code and proxy.js.
// Shop and super admin keep separate sessions, so one login never mixes with the other.
export const COOKIES = {
  access: 'access_token',
  refresh: 'refresh_token',
  workspace: 'workspace_id',
};

export const ADMIN_COOKIES = {
  access: 'admin_access_token',
  refresh: 'admin_refresh_token',
};

export const cookiesFor = (admin) => (admin ? ADMIN_COOKIES : COOKIES);

// httpOnly: browser JavaScript can never read the tokens
export const COOKIE_OPTIONS = {
  httpOnly: true,
  sameSite: 'lax',
  secure: process.env.NODE_ENV === 'production',
  path: '/',
};

export const THIRTY_DAYS = 60 * 60 * 24 * 30;
