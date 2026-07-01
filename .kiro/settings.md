# Kiro Settings

## Projekt

blog.mafio.pl — auto-agregujący blog techniczny (Symfony 8, Supabase, VPS OVH).

## Deploy

```bash
ssh <VPS_USER>@<VPS_HOST> -p <VPS_PORT>
# App path: see GitHub Secrets (VPS_DEPLOY_PATH)
# Cache clear: php8.4 bin/console cache:clear --env=prod
```

## Supabase

- Dashboard: https://supabase.com/dashboard/project/<SUPABASE_REF>
- REST API: https://<SUPABASE_REF>.supabase.co/rest/v1/
- Management API: https://api.supabase.com/v1/projects/<SUPABASE_REF>/

## Konwencje

- Kod, komentarze, commity — po angielsku
- Dokumentacja w docs/ — po polsku
- Cienkie kontrolery, logika w serwisach
- PSR-12 coding style
- Sekrety TYLKO w .env.local (gitignored)
