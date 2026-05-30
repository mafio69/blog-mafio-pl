# Jak naprawić błąd PHPUnit "You must set the KERNEL_CLASS" w PHPStorm

Ten błąd pojawia się, ponieważ PHPStorm uruchamia test z flagą `--no-configuration`, co oznacza, że ignoruje plik konfiguracyjny projektu (`phpunit.dist.xml`). W rezultacie PHPUnit nie wie, gdzie jest główna klasa jądra aplikacji (Kernel) ani jak załadować plik startowy (bootstrap).

Oto jak to naprawić w ustawieniach PHPStorm w 2 prostych krokach:

---

## Krok 1: Wskazanie pliku konfiguracyjnego w ustawieniach ogólnych

1. Otwórz ustawienia PHPStorm: **Settings** (skrót `Ctrl + Alt + S` lub `Cmd + ,` na Macu).
2. Przejdź do zakładki: **Languages & Frameworks** -> **PHP** -> **Test Frameworks**.
3. Na liście po lewej stronie wybierz **PHPUnit** (jeśli nie ma, dodaj klikając `+` i wybierając *Local*).
4. Po prawej stronie, w sekcji **Test Runner**:
   * Zaznacz pole **Default configuration file** (Domyślny plik konfiguracyjny).
   * Kliknij ikonę folderu i wskaż plik w głównym katalogu projektu:
     ```text
     phpunit.dist.xml
     ```
   * Zaznacz pole **Default bootstrap file** (Domyślny plik startowy).
   * Wskaż plik:
     ```text
     tests/bootstrap.php
     ```
5. Kliknij **Apply** i **OK**.

---

## Krok 2: Usunięcie starych (błędnych) konfiguracji uruchamiania

PHPStorm mógł zapamiętać poprzednie błędne polecenie uruchamiania testu. Aby je odświeżyć:

1. W prawym górnym rogu ekranu PHPStorm (tam gdzie masz przycisk zielonego trójkąta "Run") kliknij rozwijaną listę konfiguracji i wybierz **Edit Configurations...**.
2. Rozwiń pozycję **PHPUnit** po lewej stronie.
3. Zaznacz i usuń (klikając ikonę `-` na górze) wszystkie zapamiętane konfiguracje testów dla `AdminControllerTest`.
4. Kliknij **OK**.

Teraz uruchom test ponownie klikając zieloną strzałkę przy nazwie metody testowej w kodzie. PHPStorm uruchomi go poprawnie, używając pliku konfiguracyjnego.
