#!/usr/bin/env bash
# e2e blog w izolacji:
#  1. stawia OSOBNA instancje (APP_ENV=test, testowy admin z config/packages/test) - projekt blog-e2e,
#  2. odpala OSOBNY Playwright, ktory loguje sie raz (storageState) i testuje panel,
#  3. sprzata.
# /login i /admin nie dotykaja bazy, wiec baza nie jest potrzebna. Nie rusza dzialajacego blogu.
set -euo pipefail

cd "$(dirname "$0")/.."   # katalog blog-mafio-pl
PROJECT=blog-e2e

echo "== Stawiam izolowana instancje e2e =="
docker compose -p "$PROJECT" -f docker-compose.e2e.yml up -d --build

echo "== Czekam, az caddy bedzie healthy =="
status=starting
for _ in $(seq 1 40); do
    cid=$(docker compose -p "$PROJECT" -f docker-compose.e2e.yml ps -q caddy 2>/dev/null || true)
    [ -n "$cid" ] && status=$(docker inspect -f '{{.State.Health.Status}}' "$cid" 2>/dev/null || echo starting)
    [ "$status" = "healthy" ] && break
    sleep 2
done

if [ "$status" != "healthy" ]; then
    echo "Instancja e2e nie wstala (status: $status). Logi:"
    docker compose -p "$PROJECT" -f docker-compose.e2e.yml logs --tail=40
    docker compose -p "$PROJECT" -f docker-compose.e2e.yml down
    exit 1
fi

echo "== Playwright (osobny obraz) puka po sieci ${PROJECT}_default =="
set +e
docker run --rm \
    --network "${PROJECT}_default" \
    -e BASE_URL="http://caddy" \
    -v "$PWD/e2e":/e2e \
    -v /e2e/node_modules \
    -w /e2e \
    mcr.microsoft.com/playwright:v1.48.0-jammy \
    sh -c "npm ci && npx playwright install chromium && npx playwright test"
code=$?
set -e

echo "== Sprzatam instancje e2e =="
docker compose -p "$PROJECT" -f docker-compose.e2e.yml down

exit "$code"
