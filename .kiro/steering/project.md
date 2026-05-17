---
inclusion: always
---

# Kontekst projektu — blog.mafio.pl

## Co to jest

Auto-agregujący blog techniczny. Zbiera artykuły z PHP/Docker/dev świata, generuje streszczenia AI, serwuje publicznie.

## Status: Faza 1 MVP ✅

### Co zrobione (2026-05-17)

- [x] Symfony 8.0 skeleton (PHP 8.4)
- [x] VPS OVH deploy (nginx + PHP 8.4-FPM + SSL Let's Encrypt)
- [x] Domena blog.mafio.pl → DNS + certyfikat
- [x] Admin panel z loginem (form_login, in-memory user)
- [x] SupabaseClient service (REST API wrapper)
- [x] ProjectStateService (stan projektu w Supabase)
- [x] Strona /admin/project-state (read-only widok stanu)
- [x] Tabela `project_state` w Supabase (security, infra, plany, prompty)
- [x] PHPUnit testy (9 testów, 28 asercji)
- [x] Git security (brak sekretów w repo)
- [x] README.md po angielsku
- [x] UI przetłumaczone na angielski

### Co dalej (kolejność priorytetów)

1. **CRUD artykułów** — admin: dodawanie/edycja/usuwanie postów (tabela `posts` w Supabase)
2. **Publiczna lista postów** — strona główna z artykułami
3. **Gemini Pro integration** — streszczenia artykułów z URL
4. **RSS agregator** — auto-fetch z feedów (cron 2x/dzień)
5. **Ładna aranżacja** — CSS/design, responsywność, typografia
6. **MCP Server** — endpoint dla AI agentów (faza 4)

## Stack

| Warstwa | Technologia |
|---------|-------------|
| Backend | PHP 8.4, Symfony 8.0 |
| Baza | Supabase (PostgreSQL 17, eu-central-1) |
| Frontend | Twig SSR, minimalistyczny CSS |
| Auth | Session-based, 1 admin user (in-memory) |
| Deploy | VPS OVH, nginx + PHP 8.4-FPM, Let's Encrypt |
| AI | Gemini Pro (planowane) |

## Struktura katalogów

```
src/
├── Controller/           # Cienkie kontrolery (routing only)
│   ├── AdminController.php
│   ├── HomeController.php
│   └── SecurityController.php
├── Service/              # Logika biznesowa
│   ├── SupabaseClient.php
│   └── ProjectStateService.php
└── Kernel.php

templates/                # Twig templates
config/                   # Symfony config
tests/                    # PHPUnit testy
docs/                     # Dokumentacja (po polsku)
public/                   # Web root
```

## Konwencje

- Kod, komentarze, commity, README — po **angielsku**
- Dokumentacja w `docs/` — po **polsku**
- Cienkie kontrolery, logika w serwisach
- PSR-12 coding style
- `vendor/` w .gitignore
- Sekrety TYLKO w `.env.local` (gitignored)
- Deploy: scp + `php8.4 bin/console cache:clear --env=prod`

## Infrastruktura

| Zasób | Wartość |
|-------|---------|
| VPS | OVH vps-6f882619, Ubuntu 24.04 |
| IP | 57.128.241.168 |
| Domeny | blog.mafio.pl, lab.mafio.pl, mafio.pl |
| PHP | 8.4.21 (cli + fpm) |
| Nginx | reverse proxy, SSL termination |
| Supabase | ref: pqunceuggtrqrerybult |
| Git | github.com/mafio69/blog-mafio-pl |

## Zmienne środowiskowe (.env.local)

- `SUPABASE_URL` — URL projektu Supabase
- `SUPABASE_ANON_KEY` — klucz publiczny (RLS)
- `SUPABASE_SERVICE_ROLE_KEY` — klucz admin (pomija RLS)
- `APP_ENV` — prod na serwerze
- `APP_SECRET` — secret Symfony

## Baza danych (Supabase)

### Istniejące tabele

- `project_state` — stan projektu (sekcje: security, infrastructure, progress, plans, prompts)

### Planowane tabele

- `posts` — artykuły (title, slug, content, summary, tags, status, source_urls)
- `feeds` — źródła RSS
- `admins` — użytkownicy admin (docelowo zamiast in-memory)
