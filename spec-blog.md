# Spec: blog.mafio.pl — Auto-blog PHP

## Koncept

Blog techniczny który:
1. **Automatycznie** agreguje artykuły z PHP/Docker/dev świata (2/dzień)
2. **Ręcznie** pozwala adminowi dodawać własne artykuły (URL lub tekst)
3. **Publiczna strona** — read-only, zero interakcji użytkownika

---

## Funkcjonalności

### Publiczna strona (blog.mafio.pl)

- Lista artykułów (tytuł, skrót, data, źródła)
- Pojedynczy artykuł (treść markdown, linki do źródeł)
- Tagi/kategorie (PHP, Docker, DuckDB, Laravel, etc.)
- RSS feed
- Brak komentarzy, brak logowania użytkowników

### Admin panel (blog.mafio.pl/admin)

- Login (jeden user, credentials w DB/env)
- Dashboard: lista artykułów (draft/published)
- Dodaj artykuł:
  - **Z URL** — podajesz link, system pobiera treść, generuje skrót
  - **Z tekstu** — wklejasz gotowy markdown
- Edycja/usuwanie artykułów
- Konfiguracja: źródła RSS, częstotliwość, tagi

### Auto-agregator (cron)

- Źródła: RSS feedy (php.net, Laravel News, dev.to/php, Reddit r/php, Hacker News)
- 2x dziennie: pobiera nowe artykuły, generuje krótkie streszczenie
- Streszczenie: AI (OpenAI API) lub prosty ekstraktor (pierwsze 2-3 akapity)
- Zawsze linkuje oryginalne źródło
- Status: draft (do review) lub auto-publish

---

## Stack

| Warstwa | Technologia |
|---|---|
| Backend | PHP 8.3 |
| Baza | Supabase (PostgreSQL) — free tier |
| Frontend | Server-side rendered, minimalistyczny CSS |
| Auth | Session-based, 1 admin user |
| Cron | Systemowy cron lub Docker healthcheck |
| AI | Gemini Pro API (darmowe 3 mies.) do streszczeń |
| Deploy | Docker na VPS OVH |

---

## Schemat bazy (Supabase)

```sql
-- Artykuły
CREATE TABLE posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    summary VARCHAR(500),
    source_urls TEXT[], -- linki do oryginalnych artykułów
    tags TEXT[],
    status VARCHAR(20) DEFAULT 'draft', -- draft | published
    auto_generated BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT now(),
    published_at TIMESTAMPTZ
);

-- Źródła RSS
CREATE TABLE feeds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    category VARCHAR(50),
    active BOOLEAN DEFAULT true,
    last_fetched_at TIMESTAMPTZ
);

-- Admin
CREATE TABLE admins (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);
```

---

## Bezpieczeństwo

- Admin panel za loginem (bcrypt hash, session)
- Publiczna strona: zero inputów, zero formularzy
- Supabase: Row Level Security (RLS) — public SELECT only na posts
- API key Supabase w env (nie w kodzie)
- Rate limiting na /admin/login (fail2ban lub aplikacyjny)

---

## Cron flow

```
[co 12h] → fetch RSS feeds
         → filtruj nowe (po dacie/URL)
         → dla każdego: pobierz treść → generuj streszczenie
         → zapisz jako draft (lub auto-publish)
```

---

## Fazy implementacji

1. **MVP** — publiczna strona + admin (ręczne dodawanie)
2. **Auto-fetch** — RSS agregator + cron
3. **AI streszczenia** — OpenAI integration
4. **Polish** — RSS feed, SEO meta, cache
