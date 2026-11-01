#!/usr/bin/env bash
# Creates the three .env files of the ScriptDP app on the server, with fresh random secrets.
# Run once, inside the cloned repo:   cd /opt/scriptdp && bash infrastructure/setup-env.sh
# It asks (hidden) only for the values that cannot be generated; nothing is printed or stored elsewhere.
set -euo pipefail

cd "$(dirname "$0")/.."

for file in .env backend/.env ai-service/.env; do
  if [ -e "$file" ]; then
    echo "$file already exists. Delete it first if you really want a new one." >&2
    exit 1
  fi
done

ask() { # ask VAR "label"   (hidden input, must not be empty)
  local value=""
  while [ -z "$value" ]; do
    read -rsp "$2: " value
    echo
  done
  printf -v "$1" '%s' "$value"
}

ask GEMINI_API_KEY "Google Gemini API key"
ask META_APP_SECRET "Meta app secret (App settings > Basic > App secret)"
ask META_WEBHOOK_VERIFY_TOKEN "Meta webhook verify token (the one set in the Meta webhook)"
read -rp "Google Client ID for \"Sign in with Google\" (press Enter to skip for now): " GOOGLE_CLIENT_ID

DB_PASSWORD="$(openssl rand -hex 24)"
APP_KEY="base64:$(openssl rand -base64 32)"

umask 077

printf 'POSTGRES_PASSWORD=%s\nGOOGLE_CLIENT_ID=%s\n' "$DB_PASSWORD" "$GOOGLE_CLIENT_ID" > .env

cat > backend/.env <<EOF
APP_NAME=ScriptDP
APP_ENV=production
APP_DEBUG=false
APP_KEY=${APP_KEY}
APP_URL=https://app.scriptdp.com
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=automation
DB_USERNAME=postgres
DB_PASSWORD=${DB_PASSWORD}
SESSION_DRIVER=database
SESSION_LIFETIME=120
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
JWT_ISSUER=automation-auth
JWT_AUDIENCE=automation-api
JWT_TTL=900
META_GRAPH_URL=https://graph.facebook.com
META_GRAPH_VERSION=v25.0
META_APP_ID=957422040447717
META_ES_CONFIG_ID=1437341781085155
META_APP_SECRET=${META_APP_SECRET}
META_WEBHOOK_VERIFY_TOKEN=${META_WEBHOOK_VERIFY_TOKEN}
AI_SERVICE_URL=http://ai-service:8001
EOF

cat > ai-service/.env <<EOF
AI_PROVIDER=gemini
GEMINI_API_KEY=${GEMINI_API_KEY}
DATABASE_URL=postgresql://postgres:${DB_PASSWORD}@postgres:5432/automation
EOF

echo "Done: .env, backend/.env and ai-service/.env created (readable only by root)."
echo "Next: docker compose -f docker-compose.prod.yml up -d --build"
