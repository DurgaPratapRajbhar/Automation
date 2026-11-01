# Server: teeno sites ek VPS par

```
Internet ─▶ Caddy (80/443, SSL) ─┬─▶ app-frontend:3000   app.scriptdp.com  (yeh repo)
           shared network "web"  ├─▶ blog-frontend:3000  scriptdp.com      (business-blog)
                                 └─▶ solar-frontend:3000 solarlc.com       (solar)
```

Har site ka apna Docker project aur apna Postgres hai; ek girne se baaki nahi girte.
Website chatbot (blog + solar) yahi app ka AI use karte hain: `https://app.scriptdp.com/api/v1/ask`.

## 1. DNS (A record → server IP)

`app.scriptdp.com`, `scriptdp.com`, `www.scriptdp.com`, `solarlc.com`, `www.solarlc.com`

## 2. Docker + firewall (fresh Ubuntu)

```bash
curl -fsSL https://get.docker.com | sh
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw --force enable
docker network create web
```

## 3. Teeno projects

```bash
git clone <automation repo> /opt/scriptdp      # app: docs/deploy.md ke steps
git clone <business-blog repo> /opt/blog
git clone <solar repo> /opt/solar
```

- **App** (`/opt/scriptdp`): `docs/deploy.md` follow karo (`.env`, `backend/.env`, `ai-service/.env`, keys, migrate, super admin).
- **Blog** (`/opt/blog`): `cp .env.example .env`, values bharo, `docker compose up -d --build`.
- **Solar** (`/opt/solar`): `cp .env.example .env`, values bharo, `docker compose up -d --build`.

## 4. Caddy (sabse aakhir me)

```bash
cd /opt/scriptdp/infrastructure/caddy
docker compose up -d
docker compose logs -f        # "certificate obtained" dikhe to SSL taiyar
```

## 5. Website chatbots ko app ke AI se jodo

1. `https://app.scriptdp.com/admin/login` se do workspace banao: **ScriptDP** aur **Indian Solar Light Centre**.
2. Har workspace me: **Knowledge** me apni files/text daalo, phir **Shop profile → Website chatbot → Create key**.
3. Key ko us site ke `.env` me `SCRIPTDP_AI_KEY=` me daalo (`SCRIPTDP_AI_URL=https://app.scriptdp.com/api/v1/ask`).
4. Us site me `docker compose up -d` (blog: ai-service, solar: frontend naye env ke saath chal jaate hain).

## Naya domain / site

Caddyfile me ek block jodo (`naya.com { reverse_proxy <alias>:3000 }`), site ki compose me `web` network
par wahi alias do, phir `docker compose restart` (caddy folder me).
