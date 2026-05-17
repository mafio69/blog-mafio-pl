# Kiro Settings

## Projekt

blog.mafio.pl — auto-agregujący blog techniczny (Symfony 8, Supabase, VPS OVH).

## Deploy

```bash
sshpass -p '<PASSWORD>' ssh ubuntu@57.128.241.168
# App path: /home/ubuntu/app
# Cache clear: php8.4 bin/console cache:clear --env=prod
```

## Supabase

- Dashboard: https://supabase.com/dashboard/project/pqunceuggtrqrerybult
- REST API: https://pqunceuggtrqrerybult.supabase.co/rest/v1/
- Management API: https://api.supabase.com/v1/projects/pqunceuggtrqrerybult/

## Konwencje

- Kod, komentarze, commity — po angielsku
- Dokumentacja w docs/ — po polsku
- Cienkie kontrolery, logika w serwisach
- PSR-12 coding style
- Sekrety TYLKO w .env.local (gitignored)
