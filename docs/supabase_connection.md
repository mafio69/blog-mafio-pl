# Jak połączyć PHPStorm (Data Source) z bazą Supabase

Ten plik zawiera szczegółowe dane i wyjaśnienia dotyczące konfiguracji połączenia bazy danych w PHPStorm.

---

## 1. Dane do wpisania w PHPStorm (PostgreSQL)

Skopiuj poniższe wartości bezpośrednio za pomocą przycisku kopiowania w PHPStorm:

* **Host:**
```text
aws-0-eu-central-1.pooler.supabase.com
```

* **Port:**
```text
6543
```

* **Database (Baza danych):**
```text
postgres
```

* **User (Użytkownik):**
```text
postgres.pqunceuggtrqrerybult
```

* **Password (Hasło):**
*(Twoje własne hasło do bazy danych Supabase)*

---

## 2. Rozwiązanie błędu SSL: "Invalid sslmode value: require"

Błąd ten oznacza, że sterownik JDBC w PHPStorm nie radzi sobie z parametrem `sslmode=require` przekazanym w adresie URL. Aby to naprawić, zastosuj jedno z poniższych rozwiązań:

### Sposób A (Najprostszy - przez okno SSH/SSL)
1. W oknie konfiguracji połączenia w PHPStorm przejdź do zakładki **SSH/SSL** (obok zakładki *General*).
2. Zaznacz pole **"Use SSL"** (Użyj SSL).
3. Pozostałe pola w tej zakładce zostaw puste / domyślne.
4. W zakładce *General* upewnij się, że w adresie URL **nie ma** dopisku `?sslmode=require`.

### Sposób B (Przez zmianę parametru w URL)
Jeśli konfigurujesz połączenie bezpośrednio przez URL, zamiast `?sslmode=require` dopisz na końcu `?ssl=true`.
Cały URL do skopiowania:
```text
jdbc:postgresql://aws-0-eu-central-1.pooler.supabase.com:6543/postgres?ssl=true
```

---

## 3. Komunikat "Specify Version" (Określ wersję) w PHPStorm

Jeśli PHPStorm wyświetla komunikat / link o treści **"Specify Version"**:
1. Wersja bazy PostgreSQL na Twoim Supabase to **`17`** (dokładnie `17.6`).
2. W oknie konfiguracji połączenia możesz kliknąć link **"Specify Version"** i wybrać z listy wersję **`PostgreSQL 16`** lub **`PostgreSQL 17`** (w zależności od tego, co jest dostępne w Twojej wersji PHPStorm).

---

## 4. Dlaczego używamy akurat takich wartości? (Wyjaśnienie techniczne)

### Problem z IPv6 (Dlaczego zwykłe połączenie nie działało?)
* Darmowe projekty w Supabase domyślnie oferują bezpośrednie połączenie (Direct Connection) pod adresem zaczynającym się od `db.`. 
* Adres ten obsługuje **wyłącznie protokół IPv6**. 
* Jeśli Twój dostawca internetu (ISP) lub router obsługuje tylko starszy protokół **IPv4**, próba połączenia z adresem `db....` na porcie `5432` zakończy się niepowodzeniem i błędem limitu czasu (timeout).

### Rozwiązanie (Connection Pooler)
* Aby umożliwić połączenie przez standardowy protokół IPv4, Supabase udostępnia tzw. **Connection Pooler** (pośrednik połączeń).
* Adres poolera (`aws-0-eu-central-1.pooler.supabase.com`) działa na protokole IPv4, dzięki czemu połączysz się z nim z każdej sieci.
* Pooler działa na specjalnym porcie **`6543`** (zamiast standardowego `5432`).
* Pooler wymaga również, aby nazwa użytkownika jednoznacznie identyfikowała Twój projekt, dlatego Twój login to `postgres.pqunceuggtrqrerybult` (nazwa użytkownika + kropka + unikalne ID Twojego projektu).
