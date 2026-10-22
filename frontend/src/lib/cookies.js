// Cookie names and options, shared by server code and proxy.js
export const COOKIES = {
  access: 'access_token',
  refresh: 'refresh_token',
  workspace: 'workspace_id',
};

// httpOnly: browser JavaScript can never read the tokens
export const COOKIE_OPTIONS = {
  httpOnly: true,
  sameSite: 'lax',
  secure: process.env.NODE_ENV === 'production',
  path: '/',
};

export const THIRTY_DAYS = 60 * 60 * 24 * 30;
