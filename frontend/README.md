# Frontend (Next.js, JavaScript)

Run: `docker compose up -d --build frontend` → http://localhost:3000

## Where things live

| Folder | What |
|---|---|
| `src/app/(auth)/` | Login, register (shared) |
| `src/app/(shop)/` | **Shop app** pages: dashboard, customers, ... |
| `src/app/admin/` | **Super admin** pages (later) |
| `src/features/auth/` | Login/register forms and actions |
| `src/features/shop/<module>/` | Shop app logic, one folder per backend module |
| `src/features/admin/<module>/` | Super admin logic |
| `src/components/ui/` | Shared Input, Button, Alert |
| `src/components/shop/` | Shop sidebar + menu |
| `src/components/admin/` | Admin sidebar (later) |
| `src/lib/` | `backend.js` (only place that calls Laravel), cookies, session |
| `src/proxy.js` | Page guard + token refresh |

## Rules

- Pages only show components; logic lives in `features/`.
- Every Laravel call goes through `lib/backend.js` on the server. Tokens stay in httpOnly cookies.
- New shop page: add a folder in `features/shop/`, a page in `app/(shop)/`, and a line in `components/shop/menu.js`.
