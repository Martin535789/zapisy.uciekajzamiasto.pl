<?php
/**
 * Konfiguracja systemu zapisów
 * Zmień poniższe dane przed wdrożeniem!
 */

// Konfiguracja bazy danych
define('DB_HOST', 'localhost');
define('DB_NAME', 'zapisy_uciekajzamiasto');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');
define('DB_CHARSET', 'utf8mb4');

// Limit miejsc
define('MAX_PARTICIPANTS', 10);

// Konfiguracja email
define('MAIL_FROM', 'zapisy@uciekajzamiasto.pl');
define('MAIL_FROM_NAME', 'Uciekaj Za Miasto - Zapisy');
define('MAIL_REPLY_TO', 'kontakt@uciekajzamiasto.pl');

// Konfiguracja sesji admina
define('SESSION_NAME', 'zapisy_admin_session');
define('SESSION_LIFETIME', 3600); // 1 godzina

// Dane do logowania admina (używane tylko przy pierwszym uruchomieniu db.sql)
// Po wdrożeniu zmień hasło przez db.sql lub panel admina
define('ADMIN_USERNAME', 'admin');

// Strefa czasowa
date_default_timezone_set('Europe/Warsaw');

/**
 * Nawiązuje połączenie z bazą danych (PDO)
 */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Błąd połączenia z bazą danych: ' . $e->getMessage());
            die('<p style="color:red;text-align:center;padding:2rem;">Błąd połączenia z bazą danych. Skontaktuj się z administratorem.</p>');
        }
    }
    return $pdo;
}

/**
 * Uruchamia i konfiguruje sesję
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Generuje lub pobiera token CSRF
 */
function getCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Weryfikuje token CSRF
 */
function verifyCsrfToken(string $token): bool
{
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
