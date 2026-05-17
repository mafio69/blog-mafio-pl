# blog.mafio.pl

Auto-aggregating tech blog built with Symfony 8 and Supabase.

## What it does

- **Public blog** — read-only, zero user interaction, server-rendered
- **Auto-aggregator** — fetches articles from PHP/Docker/dev RSS feeds (2/day)
- **Admin panel** — manual article management (add from URL or markdown)
- **AI summaries** — Gemini Pro generates short summaries for aggregated articles
- **MCP Server** — Model Context Protocol endpoint for AI agents (planned)

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4, Symfony 8.0 |
| Database | Supabase (PostgreSQL 17) |
| Frontend | Server-side rendered (Twig), minimal CSS |
| Auth | Session-based, single admin user |
| Deploy | VPS (nginx + PHP-FPM), Let's Encrypt SSL |

## Project structure

```
src/
├── Controller/         # Thin controllers (routing only)
│   ├── AdminController.php
│   ├── HomeController.php
│   └── SecurityController.php
├── Service/            # Business logic
│   ├── SupabaseClient.php
│   └── ProjectStateService.php
└── Kernel.php

templates/              # Twig templates
config/                 # Symfony config (services, security, routes)
tests/                  # PHPUnit tests
docs/                   # Project documentation (Polish)
public/                 # Web root (index.php)
```

## Local development

```bash
# Install dependencies
composer install

# Configure environment
cp .env .env.local
# Fill in SUPABASE_URL, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY

# Run dev server
symfony serve

# Run tests
php vendor/bin/phpunit
```

## Deployment

The app runs on a VPS with nginx + PHP 8.4-FPM. Deploy by syncing files and clearing cache:

```bash
scp -r src/ config/ templates/ public/ composer.* user@server:~/app/
ssh user@server "cd ~/app && php8.4 bin/console cache:clear --env=prod"
```

## Implementation phases

1. **MVP** ✅ — Public homepage + admin panel with auth
2. **Auto-fetch** — RSS aggregator + cron
3. **AI summaries** — Gemini Pro integration
4. **MCP Server** — AI agent endpoint
5. **Polish** — RSS feed, SEO, caching

## License

Proprietary
