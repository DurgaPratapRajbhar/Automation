# Dukaan Platform: Saari Functionality (module-wise)

> Ye file batati hai **kya banana hai**. Kaun sa kaam ho gaya, wo `TASKS.md` me hai.
> Pehle sirf **API (Laravel backend)** banegi. Next.js frontend aur Python AI service baad me.

---

## 0. Banane ka tareeka (isi `backend` me)

Kaam `D:\Sass\Automation\backend` me hi hota hai. **Workspace = dukaan** (header `X-Workspace-Id`). Har naya module wahi layers follow karta hai jo pehle se hain:

| Layer | Kaam | Example |
|---|---|---|
| `routes/api.php` | URL + `jwt.auth` + `workspace` + permission | `Permission::CustomersView->middleware()` |
| `app/Http/Requests/<Module>/` | Validation (FormRequest), shared rules `app/Rules` | `StoreCustomerRequest` |
| `app/Http/Controllers/Api/` | Sirf request lo, service bulao, JSON do | `{ success, message, data }` |
| `app/Services/` | Business rules, `BusinessRuleException` throw | `CustomerService` |
| `app/Repositories/Contracts/` + `Eloquent/` | Interface + DB ka kaam, `AppServiceProvider` me bind | `CustomerRepository` |
| `app/Events` / `app/Listeners` | Module aapas me events se baat karein | `MemberRoleChanged` |
| `app/Enums/Permission.php` | Nayi permissions yahan, phir `php artisan permissions:sync` | `CustomersView` |
| `lang/en/<module>.php`, `config/` | Messages aur numbers, code me hardcode nahi | `customers.php` |
| `tests/Feature/` | Pest: CRUD, permission, dusre workspace ka data na dikhe | `CustomerCrudTest.php` |

**Har API ke niyam**
- Response hamesha `{ "success": true|false, "message": "...", "data": ... }`
- Har dukaan ka data `workspace_id` se bandha
- Secret (token, password) kabhi response me nahi, sirf `has_xxx: true`
- Paisa aur stock: sirf INSERT (ledger). Galti ho to ulta entry, delete nahi
- Har badlaav audit log me
- Code simple, kam code, comments English me

---

## 1. Core: Auth (login)
- Register (owner) aur login: phone + OTP (WhatsApp/SMS), email/password backup
- JWT access token + refresh token, logout, `/auth/me`
- Rate limit (throttle) login/OTP pe

| API | Permission |
|---|---|
| `POST /auth/otp/send`, `POST /auth/otp/verify` | public (throttle) |
| `POST /auth/register`, `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout` | public (throttle) |
| `GET /auth/me` | logged in |

## 2. Core: Shop (dukaan) + Setup
- Dukaan banana: naam, phone, pata, state code, dukaan type (template), language, level
- GST status: UNREGISTERED / COMPOSITION / REGULAR + GSTIN (format check)
- Drug license (medical), UPI ID, logo
- Template load: units, tracking modes, default items
- Ek user kai dukaan ka owner/staff ho sakta hai

| API | Permission |
|---|---|
| `POST /shops` | logged in |
| `GET /shop/me` (meri dukaan + role + permissions + ON modules) | shop member |
| `PUT /shop` | `shop.edit` |
| `GET /shop-types` | logged in |

## 3. Core: Modules ON/OFF (Lego)
- Kaun sa module ON hai (`shop_modules`), module ki apni settings (JSON)
- Middleware `module:khata`: module OFF to 403
- Plan/price calculate

| API | Permission |
|---|---|
| `GET /modules` (sab modules + ON/OFF + price) | shop member |
| `PUT /shop/modules` | `shop.modules` |

## 4. Core: Roles, Permissions, Staff
- Default roles: Owner, Manager, Staff, Delivery, CA (har dukaan me apne aap)
- Custom role + permissions (checkbox list)
- Staff jodna/hatana/role badalna, hatate hi session khatam

| API | Permission |
|---|---|
| `GET /permissions` | `roles.view` |
| `GET/POST/PUT/DELETE /roles` | `roles.*` |
| `GET/POST/PUT/DELETE /members` | `members.*` |

## 5. Core: Customers
- Naam, phone, pata, GSTIN (B2B), state, udhaar seema, WhatsApp opt-in
- Search (naam/phone), same phone dobara nahi (per dukaan)

| API | Permission |
|---|---|
| `GET/POST/PUT/DELETE /customers`, `GET /customers/{id}` | `customers.*` |

## 6. Core: Suppliers
- Naam, phone, GSTIN, state

| API | Permission |
|---|---|
| `GET/POST/PUT/DELETE /suppliers` | `suppliers.*` |

## 7. Core: Items (saamaan)
- Naam, local naam, aliases (voice match), photo, tracking mode (LOOSE/PACK/BATCH/SERIAL/VARIANT/NONE)
- Base unit, container unit + size (bori = 50kg), HSN, GST rate, khareed/becho rate, min rate, barcode
- Variants (size/rang)
- Search (naam, alias, barcode)

| API | Permission |
|---|---|
| `GET/POST/PUT/DELETE /items`, `GET /items/search?q=` | `items.*` |
| `POST /items/{id}/photo` | `items.edit` |

## 8. Core: Audit Log
- Kisne, kab, kya badla (pehle/baad)

| API | Permission |
|---|---|
| `GET /audit-logs` | `audit.view` |

## 9. Core: Messaging (WhatsApp channel)
- Dukaan ka WhatsApp Cloud API number jodna (token encrypted)
- Text / template / PDF / button message bhejna
- Webhook: verify + receive, duplicate message ek hi baar
- Conversations + messages save, 24 ghante window track

| API | Permission |
|---|---|
| `GET/POST/PUT/DELETE /channels` | `channels.*` |
| `POST /messages/send` | `conversations.reply` |
| `GET/POST /webhooks/whatsapp` | public (Meta signature check) |

## 10. Core: Input (voice / photo / text)
- Input save (`inbound_inputs`): kind, raw text, media, status
- AI service ko bhejna (baad me Python), parsed JSON wapas, confirm/reject
- Shuru me: AI ke bina manual JSON se test

| API | Permission |
|---|---|
| `POST /inputs`, `GET /inputs/{id}`, `POST /inputs/{id}/confirm`, `POST /inputs/{id}/reject` | `inputs.create` |

---

## 11. Module: Khata (udhaar) `khata`
- Udhaar diya (DEBIT), wapas aaya (CREDIT): customer, amount, note, invoice link
- Customer ka baaki, sabka baaki, 30 din se purane
- Udhaar seema check (paar ho to owner ko alert / block)
- Har entry pe customer ko WhatsApp message
- Weekly reminder (scheduler) + UPI link
- Customer ke liye khata dekhne ka link (signed URL)

| API | Permission |
|---|---|
| `GET /khata` (list + total) | `khata.view` |
| `GET /khata/{customerId}` (entries + baaki) | `khata.view` |
| `POST /khata/debit`, `POST /khata/credit` | `khata.create` |
| `POST /khata/{customerId}/remind`, `POST /khata/remind-all` | `khata.remind` |
| `PUT /khata/settings` (reminder din, seema) | `khata.settings` |

## 12. Module: Galla (cash + UPI) `galla`
- Subah opening, shaam closing, galle se kharcha (head ke saath), supplier ko payment
- UPI entry (manual abhi, webhook baad me), duplicate UPI ref nahi
- Din ka hisaab: cash bikri = closing - opening + kharcha
- Roz ki report (text) WhatsApp pe

| API | Permission |
|---|---|
| `POST /galla/open`, `POST /galla/close` | `galla.manage` |
| `POST /expenses`, `POST /upi-payments` | `galla.manage` |
| `GET /galla/today`, `GET /galla/{date}` | `galla.view` |
| `POST /webhooks/upi/{provider}` | public (signature) |

## 13. Module: Invoice `invoice`
- Type: Cash Memo, Bill of Supply, Tax Invoice (B2C/B2B), Credit Note, Delivery Challan, Estimate
- Lagataar number har FY (lock ke saath, duplicate nahi)
- GST: CGST+SGST (same state) ya IGST (dusra state), round off, discount
- Payment: cash / UPI / udhaar / adha-adha
- Baad me invoice (purani sale / khata entry / naya, supply_date alag)
- Cancel (delete kabhi nahi)
- PDF (Hindi font) + WhatsApp pe bhejna

| API | Permission |
|---|---|
| `POST /invoices`, `POST /invoices/later` | `invoice.create` |
| `GET /invoices?q=&from=&to=`, `GET /invoices/{id}` | `invoice.view` |
| `POST /invoices/{id}/cancel` | `invoice.cancel` |
| `GET /invoices/{id}/pdf`, `POST /invoices/{id}/send` | `invoice.view` |

## 14. Module: WhatsApp Order `wa_order`
- Order banana (WhatsApp / app / counter), items match, rate
- Status: NEW, CONFIRMED, PACKED, OUT_FOR_DELIVERY, DELIVERED, CANCELLED
- Customer ko har status pe message
- Staff inbox: chat assign (ek chat ek staff), BOT/HUMAN mode
- Delivery boy ko order, delivered + payment

| API | Permission |
|---|---|
| `GET /orders?status=`, `GET /orders/{id}` | `orders.view` |
| `POST /orders` | `orders.create` |
| `POST /orders/{id}/accept|pack|dispatch|deliver|cancel` | `orders.manage` |
| `GET /inbox`, `POST /inbox/{conv}/assign`, `POST /inbox/{conv}/reply`, `POST /inbox/{conv}/mode` | `conversations.reply` |

## 15. Module: Stock `stock`
- Ledger (`stock_events`): PURCHASE_IN, CONTAINER_OPEN/EMPTY, SALE, SALE_ESTIMATE (bina record wala baad ka bill), WEIGH_CHECK, RETURN_IN/OUT, DAMAGE, EXPIRED, ADJUST
- LOOSE/PACK: bori/carton SEALED, OPEN, EMPTY
- BATCH: batch + expiry, FEFO
- SERIAL: serial/IMEI, warranty date
- Live stock, low stock, reorder prediction (bori kitne din chalti hai)
- Expiry alert

| API | Permission |
|---|---|
| `GET /stock`, `GET /stock/{itemId}` | `stock.view` |
| `POST /stock/{itemId}/open`, `POST /stock/{itemId}/empty`, `POST /stock/{itemId}/weigh` | `stock.update` |
| `POST /stock/adjust` | `stock.adjust` |
| `GET /stock/low`, `GET /stock/expiring` | `stock.view` |

## 16. Module: Purchase `purchase`
- Supplier order (PO) draft, WhatsApp pe bhejna
- Supplier bill entry (photo + AI baad me), order vs aaya milaan
- Supplier udhaar + due date reminder
- Debit note (supplier ko wapas)

| API | Permission |
|---|---|
| `GET/POST /purchase-orders`, `POST /purchase-orders/{id}/send` | `purchase.*` |
| `GET/POST /purchases`, `POST /purchases/{id}/receive` | `purchase.*` |
| `POST /supplier-payments` | `purchase.pay` |

## 17. Module: GST `gst`
- GSTR-1 summary (B2B, B2C, HSN, credit notes)
- GSTR-3B summary (output tax - ITC)
- GSTR-2B upload + ITC milaan (MATCHED / MISSING)
- Excel/JSON export, due date reminder

| API | Permission |
|---|---|
| `GET /gst/gstr1?period=`, `GET /gst/gstr3b?period=` | `gst.view` |
| `POST /gst/2b-upload` | `gst.manage` |
| `GET /gst/export?period=&format=` | `gst.view` |

## 18. Module: After Sale `after_sale`
- Return (credit note, stock wapas / damage, refund ya khata)
- Exchange
- Warranty check (serial se)
- Repair job card: RECEIVED, IN_REPAIR, READY, DELIVERED + customer message

| API | Permission |
|---|---|
| `POST /returns` | `returns.create` |
| `GET /warranty/{serial}` | `warranty.view` |
| `GET/POST/PUT /repairs`, `POST /repairs/{id}/status` | `repairs.*` |

## 19. Module: Broadcast `broadcast`
- Offer/tyohaar message (approved template), sirf opt-in customers
- Kharcha estimate, phir bhejo, delivered/read report

| API | Permission |
|---|---|
| `POST /broadcasts/preview`, `POST /broadcasts` , `GET /broadcasts/{id}` | `broadcast.*` |

## 20. Module: Insights `insights`
- Milaan: maal ke hisaab se kitna aana chahiye vs kitna aaya
- Item-wise munafa, supplier-wise ghatai, pada sadta maal

| API | Permission |
|---|---|
| `GET /reports/daily`, `GET /reports/profit`, `GET /reports/reconciliation` | `reports.view` |

---

## 21. Baad me (API ke baad)
- **Next.js** PWA + website (design: Dukaan App Design canvas)
- **Python AI service:** FastAPI + LangGraph agents, RAG (pgvector + pg_trgm), MCP tools, Hindi STT/TTS, Jev router, Dukaan Jarvis
- **DB:** PostgreSQL (RLS, JSONB, pgvector), Redis, S3-compatible storage on VPS (loose coupling)
