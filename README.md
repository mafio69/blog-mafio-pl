# blog.mafio.pl

Auto-aggregating tech blog built with Symfony 8 and Supabase.

## Overview

- **Public blog** — read-only, zero user interaction, server-rendered.
- **Auto-aggregator** — fetches articles from PHP/Docker/dev RSS feeds.
- **Admin panel** — manual article management (add from URL or markdown).
- **AI summaries** — Gemini Pro generates short summaries for aggregated articles.
- **MCP Server** — Model Context Protocol endpoint for AI agents (planned).

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4, Symfony 8.0 |
| Database | Supabase (PostgreSQL 17) |
| Frontend | Server-side rendered (Twig), minimal CSS |
| Auth | Session-based, single admin user |
| Deploy | VPS (nginx + PHP-FPM), Let's Encrypt SSL |

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
cp .env .env.local
```

Required variables (update in `.env.local`):
- `SUPABASE_URL`
- `SUPABASE_ANON_KEY`
- `SUPABASE_SERVICE_ROLE_KEY`
- `APP_SECRET`
- `GEMINI_API_KEY` (TODO: Verify if this is the correct key name)

### Run Server

```bash
# Using Symfony CLI
symfony serve

# Or using PHP built-in server
php -S localhost:8000 -t public
```

## Scripts & Commands

### Symfony Console
The main entry point for CLI commands is `bin/console`. Common commands:

```bash
# Clear cache
php bin/console cache:clear

# List routes
php bin/console debug:router
```

### Testing

Run the test suite with PHPUnit:

```bash
php vendor/bin/phpunit
```

## Project Structure

```
src/
├── Controller/         # Controllers (routing)
├── Service/            # Business logic
└── Kernel.php
templates/              # Twig templates
config/                 # Symfony configuration
tests/                  # PHPUnit tests
docs/                   # Project documentation
public/                 # Web root (index.php)
```

## Deployment

The app runs on a VPS with nginx + PHP 8.4-FPM.

```bash
# Deploy by syncing files and clearing cache
scp -r src/ config/ templates/ public/ composer.* user@server:~/app/
ssh user@server "cd ~/app && php8.4 bin/console cache:clear --env=prod"
```

## License

Proprietary
