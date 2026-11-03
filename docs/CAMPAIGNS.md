# Campaigns (growth messages) — sirf WhatsApp

Kisi bhi business ke liye: Excel do → message likho → WhatsApp se bhejo (sabko ek saath ya ek-ek ko) → report dekho.
Email abhi nahi (baad me, agar zaroorat padi).

## Jo pehle se hai (dobara nahi banana)
- WhatsApp number connect (Embedded Signup), templates banana/list karna (Meta ke paas), template bhejna (`ConversationService::sendTemplate`).
- Message status (sent / delivered / read / failed) webhook se (`OutboundMessageService::applyStatuses`).
- Queue worker (`queue` container), Inbox (jawab yahin aate hain, bot jawab de sakta hai).
- `customers` table: name, phone, `whatsapp_opt_in`.
- Modules (super admin switch): naya module `campaigns` jodna hai, naye business par band rahega.

## Naya kya banega
**Data**
- `customers` me 2 column: `data` (JSON: Excel ke baaki columns, jaise rakam, tareekh, city) aur `opted_out_at`.
- `campaigns`: workspace, naam, channel, template (naam + bhasha), blanks ka mapping ({{1}} = Excel ka kaunsa column ya fixed text), status (draft / sending / done / paused), gino ka hisaab.
- `campaign_recipients`: campaign, customer, phone, bhare hue parameters, message_id, status (queued / sent / delivered / read / replied / failed / skipped), error.

**Flow**
1. **Excel/CSV upload** → columns dikhte hain → phone aur naam ka column chuno → customers bante/update hote hain. Upload par ek tick: "In logon ki sahmati mere paas hai".
2. **Template chuno** (sirf approved) ya naya likho (AI se draft: baad ke step me).
3. **Blanks map karo**: {{1}} ← naam, {{2}} ← rakam.
4. **Preview** (3 logon ko kaisa jayega) + **test message apne number par**.
5. **Bhejo**: abhi ya time chun kar. Queue me ek-ek karke, speed Meta ki seema ke andar.
6. **Report**: kitne gaye / pahunche / padhe / jawab aaya / fail (fail ka kaaran saath me).
7. **Opt-out**: koi "STOP / BAND" likhe to `opted_out_at` lagta hai, aage kisi campaign me nahi jaata.

**Surakshit rakhne wale niyam (zaroori)**
- Sirf opt-in (sahmati wale) aur opt-out na kiye hue logon ko.
- Meta ki daily seema (naye number par kam) se zyada recipients ho to bhejne se pehle chetavni.
- Ek hi campaign me ek hi number ko do baar nahi.
- Fail ho to dobara koshish sirf kuch error par (network), "number WhatsApp par nahi hai" par nahi.
- Super admin ke switch ke bina band (API par bhi).

## Kharcha (Meta ka, business ko dena padta hai)
Marketing template kareeb ₹0.8, utility (jaise payment/order update) kareeb ₹0.12 per message (Meta rate badalta rehta hai, bhejne se pehle dobara dekhna).

## Kaam ke step (ek ek karke, har step ke baad test)
1. Backend: module `campaigns`, customers me naye column, Excel/CSV import API + tests. (xlsx padhne ke liye ek PHP library `composer` se judegi — Docker ke andar.)
2. Backend: campaign banana, preview, bhejna (queue job), status/reply track, opt-out + tests.
3. Frontend: Contacts import page, Campaign wizard, Report page.
4. Seema ki chetavni, test message, retry.
5. AI se message draft (ai-service) + 3 variants.
6. Baad me: drip (3 din baad yaad dilana), QR/link "WhatsApp par baat karein", tyohaar/janam-din, lead status.

## Abhi tay karna hai
- Customers table ko hi "contacts" ki tarah use karein (naya table nahi). Mera suggestion: haan.
- xlsx library ka judna theek hai? (CSV bina library ke chalta hai.)
