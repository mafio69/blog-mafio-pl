# blog.mafio.pl

Auto-aggregating tech blog built with Symfony 7 and Supabase.

## Overview

- **Public blog** — read-only, zero user interaction, server-rendered.
- **Auto-aggregator** — fetches articles from PHP/Docker/dev RSS feeds.
- **Admin panel** — manual article management (add from URL or markdown).
- **AI summaries** — Gemini Pro generates short summaries for aggregated articles.
- **MCP Server** — Model Context Protocol endpoint for AI agents (planned).

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4 (prod), Symfony 7.4 |
| Database | Supabase (PostgreSQL 17) |
| Frontend | Server-side rendered (Twig), minimal CSS |
| Auth | Session-based, single admin user |
| Deploy | GitHub Actions → rsync over SSH to VPS |

## Requirements

- PHP 8.4 or higher
- Composer 2.x
- Symfony CLI (optional but recommended)
- Supabase account (or local equivalent)

## Local development

### Installation

```bash
# Install dependencies
composer install
```

### Environment Configuration

Copy the example environment file and configure your settings:

```bash
cp .env.example .env.local
```

Required variables (update in `.env.local`):
- `SUPABASE_URL`
- `SUPABASE_ANON_KEY`
- `SUPABASE_SERVICE_ROLE_KEY`
- `APP_SECRET`
- `GOOGLE_API_KEY` and `GEMINI_MODEL` (Gemini)
- `PERPLEXITY_API_KEY` (second AI provider, used alongside Gemini)

### Run Server

```bash
# Using Symfony CLI
symfony serve

# Or using PHP built-in server
php -S localhost:8000 -t public
```

Alternatively, use Docker (Caddy + PHP-FPM, see `docker-compose.yml` / `docker/`):

```bash
docker compose up -d
```

## Scripts & Commands

### Symfony Console
The main entry point for CLI commands is `bin/console`. Common commands:

```bash
# Clear cache
php bin/console cache:clear

# List routes
php bin/console debug:router

# Fetch RSS feeds into the aggregator
php bin/console app:fetch-feeds

# Generate an AI summary for a given article URL
php bin/console app:summarize-url <url>
```

### CI / code quality

GitHub Actions runs [Qodana](https://www.jetbrains.com/qodana/) static analysis on every push
(`.github/workflows/qodana_code_quality.yml`, config in `qodana.yaml`).

### Testing

Run the test suite with PHPUnit:

```bash
php vendor/bin/phpunit
```

### End-to-End Tests (Playwright)

```bash
cd e2e
npm install
npx playwright install
npm run e2e
```

### Security / Pre-commit Hook

The project includes a pre-commit hook that prevents accidental commits of secrets (API keys, passwords, tokens).

**Installation:**

```bash
composer install-hooks
```

This copies `scripts/secret-check.sh` to `.git/hooks/pre-commit`.

**What it detects:**

- Supabase keys (`sb_secret_`)
- JWT tokens (`eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9`)
- Perplexity API keys (`pplx-`)
- OpenAI keys (`sk-proj-`)
- GitHub/GitLab tokens (`ghp_`, `gho_`, `glpat-`)
- Slack tokens (`xox[baprs]-`)
- Passwords with real values (ignores placeholders like `test-`, `your_`, `changeme`)

**Bypass (if false positive):**

```bash
git commit --no-verify
```

## Project Structure

```
src/
├── Controller/         # Controllers (routing)
├── Service/            # Business logic
├── Command/            # Console commands (app:fetch-feeds, app:summarize-url)
└── Kernel.php
templates/              # Twig templates
config/                 # Symfony configuration
tests/                  # PHPUnit tests
docs/                   # Project documentation
public/                 # Web root (index.php)
docker/                 # Local dev Docker setup (PHP-FPM + Caddy)
```

## Deployment

Deployment is automated via GitHub Actions (`.github/workflows/deploy.yml`): every push to
`main` syncs the repo to the VPS over SSH (`rsync`) and runs `composer install --no-dev` +
`cache:clear` remotely. There is no manual `scp` step and no nginx config in this repo — the
VPS runs PHP 8.4-FPM behind whatever web server is configured there directly (not tracked here).

## License

Proprietary
