# System zapisów na testy nart – zapisy.uciekajzamiasto.pl

Kompletny system zapisów na bezpłatne testy nart, oparty na PHP 7.4+ i MySQL/MariaDB.

---

## Struktura plików

| Plik | Opis |
|---|---|
| `index.php` | Strona publiczna z formularzem i listą uczestników |
| `admin.php` | Panel administratora (logowanie + zarządzanie) |
| `config.php` | **Konfiguracja** – dane DB, email, ustawienia |
| `db.sql` | Schemat bazy danych (SQL) |
| `process_signup.php` | Przetwarzanie formularza zapisu |
| `admin_process.php` | Logika akcji admina (login, logout, delete, reset) |
| `export.php` | Eksport uczestników do CSV i XLSX |
| `styles.css` | Niestandardowe style CSS |

---

## Wdrożenie na Zenbox.pl Firma 10

### Wymagania

- PHP 7.4 lub wyższy (dostępne na Zenbox.pl Firma 10)
- MySQL 5.7+ / MariaDB 10.2+
- Rozszerzenia PHP: `pdo_mysql`, `mbstring`, `zip` (ZipArchive), `openssl`
- Serwer pocztowy (do wysyłki potwierdzeń – dostępny na Zenbox.pl)

---

### Krok 1 – Wgraj pliki na serwer

Przez FTP (np. FileZilla) wgraj wszystkie pliki do katalogu `public_html` (lub podkatalogu, jeśli chcesz).

---

### Krok 2 – Utwórz bazę danych

1. Zaloguj się do **cPanelu Zenbox.pl**.
2. Przejdź do **MySQL Databases** (Bazy danych MySQL).
3. Utwórz nową bazę danych, np. `zapisy_uciekajzamiasto`.
4. Utwórz użytkownika i przypisz mu **pełne uprawnienia** do tej bazy.
5. W sekcji **phpMyAdmin** wybierz bazę i wykonaj zapytania z pliku `db.sql`.

> **Ważne:** Plik `db.sql` tworzy tabelę uczestników i domyślne konto admina.

---

### Krok 3 – Skonfiguruj plik `config.php`

Otwórz `config.php` i ustaw prawidłowe dane:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'twoja_baza');      // nazwa bazy z kroku 2
define('DB_USER', 'twoj_uzytkownik'); // użytkownik bazy
define('DB_PASS', 'twoje_haslo');     // hasło użytkownika bazy

define('MAIL_FROM',      'zapisy@twojadomena.pl');
define('MAIL_FROM_NAME', 'Uciekaj Za Miasto - Zapisy');
define('MAIL_REPLY_TO',  'kontakt@twojadomena.pl');
```

---

### Krok 4 – Ustaw hasło administratora

Wygeneruj bezpieczny hash hasła. Możesz to zrobić jednorazowo, tworząc plik `gen_hash.php` z zawartością:

```php
<?php
echo password_hash('TwojeNoweHaslo123!', PASSWORD_BCRYPT, ['cost' => 12]);
```

Wgraj go, otwórz w przeglądarce, skopiuj wynik, a następnie zaktualizuj bazę danych:

```sql
UPDATE admin_users SET password = '<skopiowany_hash>' WHERE username = 'admin';
```

Usuń plik `gen_hash.php` z serwera po użyciu!

---

### Krok 5 – Sprawdź uprawnienia plików

| Pliki | Uprawnienia |
|---|---|
| `*.php`, `*.css`, `*.sql` | `644` |
| Katalogi | `755` |

---

### Krok 6 – Przetestuj działanie

1. Otwórz stronę główną i spróbuj się zapisać.
2. Sprawdź, czy przyszedł e-mail z potwierdzeniem.
3. Zaloguj się do panelu admina pod adresem `twojadomena.pl/admin.php`.
4. Przetestuj eksport CSV i XLSX.

---

## Bezpieczeństwo

- Wszystkie zapytania SQL używają **prepared statements** (ochrona przed SQL injection).
- Formularz chroniony tokenem **CSRF**.
- Hasło admina przechowywane jako hash **bcrypt** (`password_hash`).
- Sesje z flagami `httponly` i `samesite=Lax`.
- Dane wejściowe są walidowane zarówno po stronie klienta (HTML5), jak i serwera (PHP).

---

## Dostęp

| Ścieżka | Opis |
|---|---|
| `/` lub `/index.php` | Formularz zapisu i lista uczestników |
| `/admin.php` | Panel administratora |

Domyślne dane logowania (zmień po wdrożeniu!):

- **Login:** `admin`
- **Hasło:** `admin123`
