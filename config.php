<?php
// Konfiguracja bazy danych
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'zapisy_nart');

// Konfiguracja emaila
define('EMAIL_FROM', 'noreply@zapisy.uciekajzamiasto.pl');
define('EMAIL_NAME', 'Zapisy na Testy Nart');

// Bezpieczeństwo
define('SESSION_TIMEOUT', 3600);
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', '$2y$10$FvXqp2PwwfKsBXc3.9uB9eXqV8L/uZwY1XlPlXhVfp5y1TwBvyM7K'); // admin123

// Połączenie z bazą
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Błąd połączenia: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>