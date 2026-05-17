# MCP Server — blog.mafio.pl

## Przegląd

Blog eksponuje MCP (Model Context Protocol) endpoint, dzięki czemu AI agenci (Claude, Cursor, ChatGPT) mogą zarządzać treścią bloga programowo.

## Stack

- **Pakiet:** `php-mcp/server` v3.3+ (https://github.com/php-mcp/server)
- **Transport:** Streamable HTTP (ReactPHP event loop)
- **Proces:** Long-running, zarządzany przez Supervisor
- **Proxy:** Nginx z SSL (Let's Encrypt)

## Dlaczego nie ma problemu z timeoutami

PHP w trybie tradycyjnym (Apache/FPM) ma `max_execution_time`. Ale `php-mcp/server` działa jako **persistent CLI process** z ReactPHP event loop — analogicznie do Node.js. Nie podlega limitom request-response.

## Architektura

```
AI Agent (Claude/Cursor)
    │
    ▼ HTTPS
┌─────────────────────┐
│  nginx (SSL proxy)  │  mcp.mafio.pl:443
└─────────┬───────────┘
          │ HTTP
          ▼
┌─────────────────────┐
│  php-mcp/server     │  127.0.0.1:8081
│  (ReactPHP process) │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Supabase (DB)      │
│  Gemini API (AI)    │
└─────────────────────┘
```

## Toole

### `create_post`
Tworzy nowy artykuł.
- Parametry: `title`, `content` (markdown), `tags[]`, `status` (draft|published)
- Zwraca: ID i slug utworzonego posta

### `list_posts`
Lista artykułów z filtrami.
- Parametry: `status?`, `tag?`, `limit?`, `offset?`
- Zwraca: tablica postów (id, title, slug, status, created_at)

### `fetch_rss`
Triggeruje pobranie nowych artykułów z feedów RSS.
- Parametry: `feed_id?` (opcjonalnie konkretny feed)
- Zwraca: liczba nowych artykułów

### `summarize_article`
Pobiera URL i generuje streszczenie przez Gemini API.
- Parametry: `url`
- Zwraca: `title`, `summary`, `tags[]`

## Deploy

### Supervisor config

```ini
[program:mcp-server]
command=php /var/www/blog-mafio-pl/bin/mcp-server.php
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/mcp-server.log
stderr_logfile=/var/log/mcp-server-error.log
```

### Nginx config

```nginx
server {
    listen 443 ssl;
    server_name mcp.mafio.pl;

    ssl_certificate /etc/letsencrypt/live/mcp.mafio.pl/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mcp.mafio.pl/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_buffering off;
        proxy_cache off;
    }
}
```

## Konfiguracja klienta MCP

```json
{
  "mcpServers": {
    "blog-mafio": {
      "url": "https://mcp.mafio.pl/mcp"
    }
  }
}
```

## Faza implementacji

MCP Server jest planowany jako **faza 4** (po MVP, auto-fetch, AI streszczeń).
