# Dukaan Platform: Task Tracker

> Kya banana hai: `FEATURES.md` (isi `docs/` folder me). Yahan sirf **kaun sa task kahan tak pahuncha**.

**Niyam**
1. Ek time pe **ek hi task**. Claude apne se agla task shuru nahi karega. User **"next task"** bolega tab hi
2. Har task ka "Done" tabhi jab uske **Pest tests pass** hon
3. Pehle **API** (Laravel). Frontend aur AI baad me
4. Kaam `D:\Sass\Automation\backend` me hi hota hai (workspace = dukaan). Purana code tabhi badle jab zaroori ho, aur user ko bata ke
5. Task poora hone pe yahan status + tareekh + commit update karna
6. Server (php artisan serve, docker, npm) **user khud chalayega**. Claude sirf command batayega
7. Docker ek saath poora stack mat uthao (WSL memory). Pehle DB + Redis, phir baaki

**Status:** ⬜ Pending · 🔄 Chal raha · ✅ Poora (tests pass) · ⏸️ Ruka hua

**Har module task ke andar ye steps (same process):**
`migration` → `Contract` → `Repository` → `Service` → `FormRequest` → `Controller` → `routes + permission` → `Provider bind` → `Pest tests` → `TASKS.md update`

---

## Abhi tay karna baaki (Task 0 se pehle)
| # | Sawaal | Faisla |
|---|---|---|
| D1 | Project kahan hai? | ✅ `D:\Sass\Automation\backend` (alag naya project nahi) |
| D2 | Product ka naam | ⏸️ Baad me |
| D3 | Repository: Eloquent ya raw SQL? | ✅ Eloquent (Automation jaisa) |
| D4 | Login pehle kaunsa? | ✅ Email/password pehle (phone OTP baad me, T4) |
| D5 | Code kaun likhega? | ✅ Claude likhega, user review karega |
| D6 | Tests kis DB pe? | ✅ sqlite in-memory (Automation jaisa). Guard `TestCase::setUpTraits()` me, `phpunit.xml` me `<server force>` |

---

## Phase A: Setup
| # | Task | Status | Tests | Commit | Tareekh |
|---|---|---|---|---|---|
| T0 | Project setup (Laravel 13, PHP 8.5, Docker, Pest) | ✅ Automation me pehle se | | | |
| T1 | Module structure: Automation ki layers hi (`app/Services`, `Repositories`...), alag module loader nahi | ✅ Faisla | | | 2026-10-22 |
| T2 | Common: `BusinessRuleException`, JSON format, handler, Pest helpers | ✅ Pehle se + test DB guard fix | 37 pass | | 2026-10-22 |

## Phase B: Core
| # | Task | Status | Tests | Commit | Tareekh |
|---|---|---|---|---|---|
| T3 | Auth: register, login, JWT + refresh, logout, me | ✅ Automation me pehle se | | | |
| T4 | Auth: phone OTP (send/verify, throttle) | ⬜ | | | |
| T5 | Shop profile: workspace = dukaan; phone, pata, state, GST status, GSTIN, UPI ID + `GET/PUT /workspace/profile` | ✅ | 5 tests | ⬜ | 2026-10-23 |
| T6 | Roles + permissions: Spatie (teams), permission = route ka naam (fail closed), `permissions:sync`, permission tree, role templates (DB), Super Admin + admin API | ✅ | 47 pass | ⬜ | 2026-10-22 |
| T7 | Members (jodna/hatana/role, aakhri owner guard) | ✅ Spatie pe shift | 37 pass | ⬜ | 2026-10-22 |
| T8 | Modules ON/OFF + `module:` middleware | ⬜ | | | |
| T9 | Customers CRUD + search + GSTIN/state rule | ✅ | 8 tests | ⬜ | 2026-10-22 |
| T10 | Suppliers CRUD | ⬜ | | | |
| T11 | Items CRUD + variants + search + photo | ⬜ | | | |
| T12 | Audit log (events + listener + list API) | ✅ Automation me pehle se | | | |
| T13 | WhatsApp channel + send message + webhook | ✅ Automation me pehle se | | | |
| T14 | Input API (voice/photo/text save, confirm/reject; AI abhi manual) | ⬜ | | | |

## Phase C: Pehla bechne layak module
| # | Task | Status | Tests | Commit | Tareekh |
|---|---|---|---|---|---|
| T15 | Khata: debit/credit, baaki, list, seema check | ⬜ | | | |
| T16 | Khata: customer ko message + weekly reminder (scheduler) + UPI link | ⬜ | | | |
| T17 | Galla: open/close, kharcha, UPI entry, din ka hisaab | ⬜ | | | |
| T18 | Galla: roz ki report WhatsApp pe | ⬜ | | | |

## Phase D: Bill + Order
| # | Task | Status | Tests | Commit | Tareekh |
|---|---|---|---|---|---|
| T19 | Invoice: number series (FY, lock), cash memo + tax invoice, GST calc | ⬜ | | | |
| T20 | Invoice: payment (cash/UPI/udhaar/split) + events to khata/galla | ⬜ | | | |
| T21 | Invoice: baad me invoice, cancel | ⬜ | | | |
| T22 | Invoice: PDF (Hindi) + WhatsApp send | ⬜ | | | |
| T23 | WA Order: order CRUD + status flow + customer messages | ⬜ | | | |
| T24 | WA Order: staff inbox (assign, BOT/HUMAN, reply) | ⬜ | | | |

## Phase E: Maal
| # | Task | Status | Tests | Commit | Tareekh |
|---|---|---|---|---|---|
| T25 | Stock: ledger + LOOSE/PACK containers (open/empty/weigh) | ⬜ | | | |
| T26 | Stock: low stock + reorder prediction + alert | ⬜ | | | |
| T27 | Stock: BATCH (expiry, FEFO) + SERIAL (warranty) | ⬜ | | | |
| T28 | Purchase: PO + WhatsApp send | ⬜ | | | |
| T29 | Purchase: bill entry + receive + supplier udhaar + debit note | ⬜ | | | |

## Phase F: Hisaab + baaki
| # | Task | Status | Tests | Commit | Tareekh |
|---|---|---|---|---|---|
| T30 | Insights: milaan, item profit, ghatai | ⬜ | | | |
| T31 | GST: GSTR-1, GSTR-3B, 2B upload + ITC match, export | ⬜ | | | |
| T32 | After sale: return/credit note, exchange, warranty, repair | ⬜ | | | |
| T33 | Broadcast: preview, send, report | ⬜ | | | |

## Phase G: API ke baad
| # | Task | Status |
|---|---|---|
| T34 | Next.js frontend (JS, Server Components + Server Actions, sirf next/react/tailwind): login/register, shop app (dashboard, customers, roles + permission tree, members, shop profile), Super Admin (workspaces, role templates). Browser me test | ✅ 2026-10-22 |
| T34b | PWA (install, offline), Hindi UI, workspace switcher | ⬜ |
| T35 | Python AI service: STT + parse + MCP tools + item RAG | ⬜ |
| T36 | LangGraph: Order agent, Jev router, Dukaan Jarvis | ⬜ |
| T37 | Deploy: k3s on VPS, storage, backup | ⬜ |

## Phase H: WhatsApp AI chatbot (Basic + Premium plan)
Basic plan = bot + hamare dashboard ka Inbox (kam daam). Premium plan = dukaandar khud Meta se joDe, sab seedha uske WhatsApp Business app me (coexistence, zyada daam).

| # | Task | Status | Tests | Commit | Tareekh |
|---|---|---|---|---|---|
| T38 | Shop knowledge (text/file) CRUD + permissions | ✅ | 6 tests | 1d8fb51 | 2026-10-23 |
| T39 | ai-service: embed (pgvector) + Agentic RAG (LangGraph) + /ask | ✅ | manual | 8c489cb | 2026-10-23 |
| T40 | WhatsApp AI reply (queue) + file se seekhna (pdf/docx/txt) | ✅ live test pass | 3 tests | ee3a706 | 2026-10-23 |
| T41 | Shop type (kirana, medical, solar...) admin form + list + filter | ⬜ | | | |
| T42 | Inbox (Basic): chats list, customer + bot messages (AI tag), unread count, staff reply, auto-refresh; sidebar active highlight | ✅ browser me verify | 6 tests | ⬜ | 2026-10-25 |
| T43 | Owner alert: naya customer ya bot "pata nahi" bole to owner ke WhatsApp par khabar | ⬜ | | | |
| T44 | Premium: Meta Tech Provider approval + Embedded Signup ("Connect WhatsApp") | ⬜ Meta approval user ki taraf se | | | |
| T45 | Premium: coexistence webhooks (owner app se reply save, tab bot ruke) | ⬜ | | | |

---

## Log (har task ke baad ek line)
| Tareekh | Task | Kya hua |
|---|---|---|
| 2026-10-25 | T42 | Inbox API (unread, search, reply, permissions conversations.view + messages.create) + Inbox/chat pages + menu active highlight. 67 backend tests pass. Commit baaki |
| 2026-10-25 | T38-T45 | AI chatbot live (asli number par test pass). Plan tay: Basic (Inbox) pehle, Premium (Meta coexistence) saath me approval ke baad |
| 2026-10-22 | T6, T34 | Permissions route-name se (Enum/config hate), role templates; frontend banaya aur browser me poora test kiya (register, login, staff permissions, customers CRUD, roles tree, members, profile, admin suspend/templates). 47 backend tests pass, `next build` saaf |
| 2026-10-23 | T5 | Dukaan profile + shared GstinService (customers bhi isi se) + UpiId rule. Sab tests pass. Commit baaki |
| 2026-10-22 | T9, T6, T7 | Customers module; purana RBAC hata ke Spatie (teams) + Permission enum + Super Admin; test env fix (tests pehle dev Postgres pe chal rahe the). 37 tests pass. Commit baaki |
| 2026-10-22 | - | FEATURES.md + TASKS.md bane. Koi code nahi likha |
| 2026-10-22 | - | Faisle: D1, D3, D4, D5, D6 final. D2 (naam) baad me. T0 "next task" ka intezaar |
| 2026-10-22 | - | Review: schema.sql me roles/permissions/shop_user, refresh_tokens, otp_codes, channels (Automation pattern) jode; UPI ref unique per shop; D5, D6 jode; design.html Phase 4 naye stack pe |
