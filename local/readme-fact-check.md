# Fact-check: README.md vs rzeczywisty stan repo

Data: 2026-07-07

## Podsumowanie

README.md w większości trafnie opisuje **funkcje** projektu (publiczna strona, admin,
auto-agregator, AI streszczenia, MCP jako "planned"), ale jest **nieaktualne/mylące co
do stacku technicznego i sposobu deployu**. Kilka realnie istniejących rzeczy (komendy
konsolowe, Perplexity, CI/Qodana, `src/Command`) w ogóle nie jest wspomnianych.

## Punkt po punkcie

| Twierdzenie README                                                                          | Werdykt                           | Dowód                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
|---------------------------------------------------------------------------------------------|-----------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| PHP 8.4, Symfony 8.0                                                                        | ⚠️ Częściowo błędne               | `composer.json`: `"php": ">=8.3"`, `symfony/framework-bundle": "7.4.*"` (zainstalowane `v7.4.13`). Symfony 8.0 nie jest używane — to prawdopodobnie pomyłka albo plan na przyszłość. PHP 8.4 zgadza się z prod (`.github/workflows/deploy.yml` wywołuje `php8.4 bin/console ...`), ale nie z deklaracją w `composer.json` (>=8.3) ani z lokalnym CLI (8.5.7).                                                                                                                                          |
| Deploy: ręczny `scp -r src/ config/ ...` + „VPS z nginx + PHP-FPM"                          | ❌ Nieaktualne                     | Realny deploy jest **w pełni automatyczny przez GitHub Actions** (`.github/workflows/deploy.yml`): push na `main` → `rsync -azP --delete` po SSH → `composer install --no-dev` → `cache:clear` na serwerze. Nikt nie robi tego ręcznie przez `scp`. Dodatkowo w repo nie ma **żadnego pliku nginx** — lokalne środowisko dev to Docker (`docker-compose.yml`) z **Caddy**, nie nginx.                                                                                                                  |
| Struktura projektu (`src/Controller`, `src/Service`, `Kernel.php`)                          | ⚠️ Niepełna                       | Brakuje `src/Command/`, które zawiera `FetchFeedsCommand` (`app:fetch-feeds`) i `SummarizeUrlCommand` (`app:summarize-url`) — czyli realne wejścia do agregatora RSS i AI-streszczeń.                                                                                                                                                                                                                                                                                                                  |
| Zmienna `GEMINI_API_KEY` (README ma dopisek „TODO: Verify if this is the correct key name") | ❌ Zła nazwa                       | `config/services.yaml` wiąże `%env(GOOGLE_API_KEY)%` + `%env(GEMINI_MODEL)%`, nie `GEMINI_API_KEY`. README słusznie miało wątpliwość — i miała rację: nazwa jest inna. Dodatkowo README nie wspomina wcale `PERPLEXITY_API_KEY`, mimo że `PerplexityClient` istnieje i jest częścią `ContentGeneratorService` (wzorowany na `GeminiClient`, ma retry + logging).                                                                                                                                       |
| Overview: „Auto-aggregator" i „AI summaries"                                                | ✅ Prawdziwe, ale niedopowiedziane | Realnie zaimplementowane: `AggregatorService`, `FeedService`, `SummarizerService`, `GeminiClient`, `PerplexityClient`, `ContentGeneratorService`. Ale sekcja „Scripts & Commands" w README wymienia tylko `cache:clear` i `debug:router` — nie ma ani słowa o `bin/console app:fetch-feeds` czy `app:summarize-url`, więc ktoś czytający README nie dowie się, jak to ręcznie odpalić.                                                                                                                 |
| „MCP Server — Model Context Protocol endpoint for AI agents (planned)"                      | ✅ Zgodne z rzeczywistością        | Zweryfikowane wcześniej: `bin/mcp-server.php` istnieje jako gotowy szkic (4 toole: `list_posts`, `create_post`, `fetch_rss`, `get_post`), ale pakiet `php-mcp/server` **nie jest zainstalowany** (0 wystąpień w `composer.json`/`composer.lock`, brak w `vendor/`) — kod nie da się uruchomić bez `composer require php-mcp/server` i bez przepisania pod aktualne API biblioteki. Słowo „planned" jest więc trafne, ale ukrywa, że kod-szkic już istnieje i wymaga dopracowania, nie pisania od zera. |
| Admin panel: login, 1 admin user, session-based                                             | ✅ Zgodne                          | `config/packages/security.yaml`: `users_in_memory` z jednym userem `admin` (bcrypt hash), `form_login` na `/login`, `access_control` wymusza `ROLE_ADMIN` na `/admin`. Zgadza się z README. Rozjazd ze `spec-blog.md`, który mówił o „credentials w DB/env" — w praktyce hash hasła jest zahardkodowany bezpośrednio w `security.yaml`.                                                                                                                                                                |
| CI / jakość kodu                                                                            | ⚠️ Pominięte w README             | `qodana.yaml` + `.github/workflows/qodana_code_quality.yml` istnieją i realnie działają (analiza Qodana, profil `qodana.starter`, PHP 8.3 w CI) — README nic o tym nie mówi.                                                                                                                                                                                                                                                                                                                           |

## Czego README w ogóle nie wspomina

- **`PERPLEXITY_API_KEY`** i `PerplexityClient` — drugi dostawca AI obok Gemini.
- **`src/Command/`** — dwie komendy konsolowe napędzające agregator i streszczenia.
- **CI/CD** — GitHub Actions (deploy automatyczny + Qodana code quality).
- **Docker** (`docker-compose.yml`, `docker/php/Dockerfile`, `docker/caddy/Caddyfile`) jako sposób na lokalny dev —
  README każe robić `symfony serve` albo wbudowany serwer PHP, o Dockerze ani słowa.
- Nowe, jeszcze niescommitowane pliki robocze: `src/Controller/ArticlesController.php`,
  `src/Controller/NewsletterController.php`, `templates/newsletter/` — praca w toku, nigdzie nieudokumentowana (ani w
  README, ani w docs/, ani w spec-blog.md).

## Rekomendacje (jeśli chcesz zaktualizować README)

1. Zmienić „Symfony 8.0" → „Symfony 7.4" (albo faktycznie zaktualizować pakiet, jeśli 8.0 to był plan).
2. Zastąpić sekcję „Deployment" opisem realnego flow: push na `main` → GitHub Actions → rsync + cache:clear. Usunąć
   wzmiankę o nginx (albo dodać faktyczny setup na VPS, jeśli nginx jest tam mimo braku configu w repo).
3. Poprawić `GEMINI_API_KEY` → `GOOGLE_API_KEY` + `GEMINI_MODEL`, dopisać `PERPLEXITY_API_KEY`.
4. Dodać do „Scripts & Commands": `php bin/console app:fetch-feeds` i `php bin/console app:summarize-url <url>`.
5. Wspomnieć Docker jako alternatywną ścieżkę lokalnego setupu (skoro `docker-compose.yml` istnieje i jest utrzymywany —
   widoczny w `git status` jako zmodyfikowany).
6. Dodać `src/Command/` do drzewa „Project Structure".
