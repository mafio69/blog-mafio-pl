---
inclusion: always
---
#To projekt który będzie elementem server-a
# Kontekst projektu — docker-fast-php-logger

## Co to jest

Środowisko deweloperskie Docker dla PHP 8.3 + Apache + MySQL 8.
Bundluje dwa pakiety Composer:
- `mafio69/fast-php-logger` — PSR-3 logger z rotacją plików i anonimizacją
- `mafio69/fast-php-log-viewer` — UI do przeglądania logów

## Struktura katalogów

```
./app/              ← kod PHP aplikacji (volume: ./app → /var/www/html/app)
./app/suite-nav.js  ← nawigacja dropdown (serwowana globalnie przez proxy)
./logs/             ← pliki logów na hoście (volume: ./logs → /var/www/html/logs)
./viewer/           ← entry point przeglądarki logów (kopiowany do obrazu)
./docker/           ← php.ini, xdebug.ini, entrypoint.sh, nginx-inject.conf
./packages/         ← lokalny pakiet fast-php-logger (źródło)
./vendor/           ← Composer deps (budowane wewnątrz obrazu, NIE montowane)
```

## Serwisy Docker i routing

| Domena | Serwis | Opis |
|---|---|---|
| `http://app.local` | php (Apache) | Dashboard aplikacji |
| `http://logs.local` | php (Apache VirtualHost) | Log viewer |
| `http://pma.local` | phpmyadmin | phpMyAdmin |
| `http://adminer.local` | adminer | Adminer |
| `http://mail.local` | mailpit | Przechwytywanie maili |
| `http://portainer.local` | portainer | Zarządzanie kontenerami |

Fallback (bez proxy): `http://localhost:8080`

## Nawigacja suite (dropdown ☰)

Plik `app/suite-nav.js` jest wstrzykiwany na **wszystkie** serwisy przez nginx-proxy:
- `docker/nginx-inject.conf` — `sub_filter` dodaje `<script src>` przed `</title>`
- Montowany jako `/etc/nginx/vhost.d/default_location` w serwisie proxy
- Używa `window.location.href` (nie `<a>`) żeby ominąć AJAX interception PMA/Portainer

## Namespace PHP

Pakiet logger: `Mariusz\Logger\` (src w `packages/fast-php-logger/src/`)

## Kluczowe klasy

| Klasa | Plik | Rola |
|---|---|---|
| `DualLogger` | `packages/fast-php-logger/src/DualLogger.php` | PSR-3 logger, pisze do pliku i STDERR |
| `LogFileManager` | `packages/fast-php-logger/src/LogFileManager.php` | Rotacja plików, struktura `YYYY/MM/YYYY-MM-DD.log` |
| `LogAnonymizer` | `packages/fast-php-logger/src/LogAnonymizer.php` | Maskuje email, token, password w kontekście |
| `LogContextSerializer` | `packages/fast-php-logger/src/LogContextSerializer.php` | Serializuje obiekty/wyjątki do tablicy |

## Format wpisu logu

```
[2026-05-04 14:32:01] [WARNING] [app/index.php:28] Login failed {"email":"j*n@***.com","attempts":3}
```

## Zmienne środowiskowe

- `APP_ENV` — środowisko (development/test/production)
- `LOG_DIR` — katalog logów w kontenerze (`/var/www/html/logs`)
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — MySQL
- `GIT_ACCES_TOKEN` — token GitHub (w `.env`, NIE commitować)
- `DUCK_TOKEN` — token MotherDuck (w `.env`, NIE commitować)

## Konwencje

- Kod, komentarze, commity i PR-y po **angielsku**
- `vendor/` wykluczony z gita (budowany w obrazie Docker)
- Zmiany w `composer.json` → wymagają `docker compose build`
- Zmiany w `viewer/` lub `Dockerfile` → wymagają `docker compose up -d --build php`
- PHP 8.1+ (strict_types, named arguments, enums, fibers OK)
- PSR-12 coding style
