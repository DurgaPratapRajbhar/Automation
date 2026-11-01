import { NextResponse } from 'next/server';

// Redirect to a path on the same site. The Location stays relative, because behind a proxy
// request.url can carry an internal host (like localhost:3000), which the browser must not be sent to.
export function redirectTo(path, status = 307) {
  return new NextResponse(null, { status, headers: { Location: path } });
}
