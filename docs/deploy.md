# Deploy: app.scriptdp.com

Server par Docker hona chahiye, aur ek shared network `web` jisme server ka Caddy chalta hai
(Caddy `app.scriptdp.com` ko `app-frontend:3000` par bhejta hai).

## Pehli baar

```bash
docker network create web
git clone <repo> /opt/scriptdp && cd /opt/scriptdp

cp .env.example .env                       # POSTGRES_PASSWORD = strong password
cp backend/.env.example backend/.env       # neeche wali values
```

`backend/.env` (production):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.scriptdp.com
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=automation
DB_USERNAME=postgres
DB_PASSWORD=<same as POSTGRES_PASSWORD>
AI_SERVICE_URL=http://ai-service:8001
META_* = local .env wali values
```

`ai-service/.env`:

```
AI_PROVIDER=gemini
GEMINI_API_KEY=<Google AI Studio key>
DATABASE_URL=postgresql://postgres:<POSTGRES_PASSWORD>@postgres:5432/automation
```

Chalana:

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm --no-deps backend php artisan key:generate --show
# jo key aaye use backend/.env me APP_KEY= me daalo
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec backend sh -c "mkdir -p storage/keys && openssl genpkey -algorithm RSA -out storage/keys/jwt-private.pem -pkeyopt rsa_keygen_bits:4096 && openssl rsa -pubout -in storage/keys/jwt-private.pem -out storage/keys/jwt-public.pem && chown -R www-data storage/keys"
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
```

Pehla super admin (phir `/admin/login` se dukaanein banao):

```bash
docker compose -f docker-compose.prod.yml exec backend php artisan tinker --execute="App\Models\User::create(['name' => 'Admin', 'email' => 'ADMIN_EMAIL', 'password' => 'STRONG_PASSWORD'])"
docker compose -f docker-compose.prod.yml exec backend php artisan app:make-super-admin ADMIN_EMAIL
```

`queue` aur `ai-service` migrate se pehle tables na milne par restart hote rehte hain; migrate ke baad
apne aap chal padte hain.

Container ke andar `.env` file nahi hoti (values `env_file` se aati hain), isliye `backend/.env` badalne ke baad
`docker compose -f docker-compose.prod.yml up -d` dobara chalao.

Meta webhook URL: `https://app.scriptdp.com/api/webhooks/whatsapp`

## Update

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
```
