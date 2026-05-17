# Supabase — blog.mafio.pl

## Projekt

| Parametr | Wartość |
|----------|---------|
| Nazwa | blog-mafio-pl |
| Ref | `pqunceuggtrqrerybult` |
| Region | eu-central-1 (Frankfurt) |
| URL | `https://pqunceuggtrqrerybult.supabase.co` |
| REST API | `https://pqunceuggtrqrerybult.supabase.co/rest/v1/` |
| Organizacja | mafio69's Org (`bibujexsktmmoelpinfa`) |
| Postgres | 17.6 |
| Status | ACTIVE_HEALTHY |

## Klucze (w `.env.local`)

| Zmienna | Rola |
|---------|------|
| `SUPABASE_ANON_KEY` | Publiczny dostęp (RLS) — tylko SELECT na published posts |
| `SUPABASE_SERVICE_ROLE_KEY` | Pełny dostęp — admin CRUD, pomija RLS |

## Schemat bazy

### posts

| Kolumna | Typ | Opis |
|---------|-----|------|
| id | UUID (PK) | auto gen_random_uuid() |
| title | VARCHAR(255) | Tytuł artykułu |
| slug | VARCHAR(255) UNIQUE | URL-friendly identyfikator |
| content | TEXT | Treść markdown |
| summary | VARCHAR(500) | Krótkie streszczenie |
| source_urls | TEXT[] | Linki do oryginalnych źródeł |
| tags | TEXT[] | Tagi/kategorie |
| status | VARCHAR(20) | `draft` \| `published` |
| auto_generated | BOOLEAN | Czy z auto-agregatora |
| created_at | TIMESTAMPTZ | Data utworzenia |
| published_at | TIMESTAMPTZ | Data publikacji |

### feeds

| Kolumna | Typ | Opis |
|---------|-----|------|
| id | UUID (PK) | auto |
| name | VARCHAR(100) | Nazwa źródła |
| url | VARCHAR(500) | URL feedu RSS |
| category | VARCHAR(50) | Kategoria |
| active | BOOLEAN | Czy aktywny |
| last_fetched_at | TIMESTAMPTZ | Ostatnie pobranie |

### admins

| Kolumna | Typ | Opis |
|---------|-----|------|
| id | UUID (PK) | auto |
| username | VARCHAR(50) UNIQUE | Login |
| password_hash | VARCHAR(255) | bcrypt hash |

## Row Level Security (RLS)

| Tabela | Polityka | Reguła |
|--------|----------|--------|
| posts | Public read published posts | `SELECT` gdzie `status = 'published'` |
| posts | Service role full access | `ALL` dla `service_role` |
| feeds | Service role full access | `ALL` dla `service_role` |
| admins | Service role full access | `ALL` dla `service_role` |

## REST API — przykłady użycia z PHP

### Pobierz opublikowane posty (anon key)

```php
$response = $httpClient->request('GET', $supabaseUrl . '/rest/v1/posts', [
    'headers' => [
        'apikey' => $anonKey,
        'Authorization' => 'Bearer ' . $anonKey,
    ],
    'query' => [
        'status' => 'eq.published',
        'order' => 'published_at.desc',
        'limit' => '10',
    ],
]);
```

### Utwórz post (service_role key)

```php
$response = $httpClient->request('POST', $supabaseUrl . '/rest/v1/posts', [
    'headers' => [
        'apikey' => $serviceRoleKey,
        'Authorization' => 'Bearer ' . $serviceRoleKey,
        'Content-Type' => 'application/json',
        'Prefer' => 'return=representation',
    ],
    'json' => [
        'title' => 'Nowy artykuł',
        'slug' => 'nowy-artykul',
        'content' => '# Treść markdown',
        'tags' => ['PHP', 'Docker'],
        'status' => 'draft',
    ],
]);
```

### Aktualizuj post

```php
$response = $httpClient->request('PATCH', $supabaseUrl . '/rest/v1/posts?id=eq.' . $id, [
    'headers' => [
        'apikey' => $serviceRoleKey,
        'Authorization' => 'Bearer ' . $serviceRoleKey,
        'Content-Type' => 'application/json',
        'Prefer' => 'return=representation',
    ],
    'json' => [
        'status' => 'published',
        'published_at' => date('c'),
    ],
]);
```

### Usuń post

```php
$response = $httpClient->request('DELETE', $supabaseUrl . '/rest/v1/posts?id=eq.' . $id, [
    'headers' => [
        'apikey' => $serviceRoleKey,
        'Authorization' => 'Bearer ' . $serviceRoleKey,
    ],
]);
```

## PostgREST — filtry

| Operator | Znaczenie | Przykład |
|----------|-----------|----------|
| `eq` | = | `?status=eq.published` |
| `neq` | != | `?status=neq.draft` |
| `gt` | > | `?created_at=gt.2026-01-01` |
| `gte` | >= | |
| `lt` | < | |
| `lte` | <= | |
| `like` | LIKE | `?title=like.*PHP*` |
| `ilike` | ILIKE | `?title=ilike.*php*` |
| `in` | IN | `?status=in.(draft,published)` |
| `cs` | @> (contains) | `?tags=cs.{PHP}` |
| `is` | IS | `?published_at=is.null` |

## Sortowanie i paginacja

```
?order=created_at.desc
?limit=10&offset=20
?select=id,title,slug,summary,tags,published_at
```

## Management API

Base URL: `https://api.supabase.com`  
Auth: `Authorization: Bearer <SUPABASE_ACCESS_TOKEN>`

| Endpoint | Opis |
|----------|------|
| `GET /v1/projects` | Lista projektów |
| `GET /v1/projects/{ref}/api-keys` | Klucze API |
| `POST /v1/projects/{ref}/database/query` | Wykonaj SQL |

## Dashboard

https://supabase.com/dashboard/project/pqunceuggtrqrerybult
