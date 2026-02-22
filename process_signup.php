<?php
/**
 * process_signup.php – przetwarzanie formularza zapisu
 * Obsługuje żądania POST z index.php
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

startSecureSession();

// Akceptujemy tylko POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// --- CSRF ---
$csrfToken = trim($_POST['csrf_token'] ?? '');
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.';
    header('Location: index.php');
    exit;
}

// --- Zbieranie i oczyszczanie danych ---
$fields = ['imie', 'nazwisko', 'adres', 'miasto', 'email', 'telefon', 'wiek', 'wzrost', 'waga'];
$data   = [];
foreach ($fields as $field) {
    $data[$field] = trim(strip_tags($_POST[$field] ?? ''));
}

// --- Walidacja ---
$errors = [];

if (mb_strlen($data['imie']) < 2 || mb_strlen($data['imie']) > 100) {
    $errors[] = 'Imię musi mieć od 2 do 100 znaków.';
}
if (mb_strlen($data['nazwisko']) < 2 || mb_strlen($data['nazwisko']) > 100) {
    $errors[] = 'Nazwisko musi mieć od 2 do 100 znaków.';
}
if (mb_strlen($data['adres']) < 5 || mb_strlen($data['adres']) > 255) {
    $errors[] = 'Adres musi mieć od 5 do 255 znaków.';
}
if (mb_strlen($data['miasto']) < 2 || mb_strlen($data['miasto']) > 100) {
    $errors[] = 'Miasto musi mieć od 2 do 100 znaków.';
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Podaj prawidłowy adres e-mail.';
}
if (!preg_match('/^\+?[\d\s\-().]{7,20}$/', $data['telefon'])) {
    $errors[] = 'Podaj prawidłowy numer telefonu (7–20 znaków, cyfry, spacje, +, -, ().';
}

$wiek   = filter_var($data['wiek'],   FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 120]]);
$wzrost = filter_var($data['wzrost'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 50, 'max_range' => 250]]);
$wagaRaw = str_replace(',', '.', $data['waga']);
$waga   = filter_var($wagaRaw, FILTER_VALIDATE_FLOAT);

if ($wiek === false)  { $errors[] = 'Wiek musi być liczbą między 5 a 120.'; }
if ($wzrost === false) { $errors[] = 'Wzrost musi być liczbą między 50 a 250 cm.'; }
if ($waga === false || $waga < 20 || $waga > 500) { $errors[] = 'Waga musi być liczbą między 20 a 500 kg.'; }

if (!empty($errors)) {
    $_SESSION['flash_error']   = implode('<br>', $errors);
    $_SESSION['form_data']     = $data;
    header('Location: index.php');
    exit;
}

// --- Połączenie z DB ---
$pdo = getDB();

// --- Limit miejsc ---
$stmt  = $pdo->query('SELECT COUNT(*) FROM participants');
$count = (int) $stmt->fetchColumn();
if ($count >= MAX_PARTICIPANTS) {
    $_SESSION['flash_error'] = 'Przepraszamy, wszystkie miejsca są już zajęte.';
    header('Location: index.php');
    exit;
}

// --- Sprawdzenie duplikatu email ---
$stmt = $pdo->prepare('SELECT id FROM participants WHERE email = ?');
$stmt->execute([$data['email']]);
if ($stmt->fetchColumn()) {
    $_SESSION['flash_error'] = 'Ten adres e-mail jest już zarejestrowany.';
    $_SESSION['form_data']   = $data;
    header('Location: index.php');
    exit;
}

// --- Zapis do bazy ---
$sql = 'INSERT INTO participants (imie, nazwisko, adres, miasto, email, telefon, wiek, wzrost, waga)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $data['imie'],
    $data['nazwisko'],
    $data['adres'],
    $data['miasto'],
    $data['email'],
    $data['telefon'],
    (int) $wiek,
    (int) $wzrost,
    round((float) $waga, 1),
]);

// --- Email z potwierdzeniem ---
sendConfirmationEmail($data);

// --- Sukces ---
$_SESSION['flash_success'] = 'Gratulacje! Twój zapis został przyjęty. Potwierdzenie wysłaliśmy na adres ' . htmlspecialchars($data['email']) . '.';
unset($_SESSION['form_data']);

header('Location: index.php');
exit;

/* ---------------------------------------------------------------
   Funkcja wysyłki maila potwierdzającego
   --------------------------------------------------------------- */
function sendConfirmationEmail(array $data): void
{
    $to      = $data['email'];
    $subject = '=?UTF-8?B?' . base64_encode('Potwierdzenie zapisu – Testy nart') . '?=';

    $fromName    = mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8', 'B');
    $headers     = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_REPLY_TO,
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    $body = <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head><meta charset="UTF-8"><title>Potwierdzenie zapisu</title></head>
<body style="font-family:Arial,sans-serif;background:#f8f9fa;margin:0;padding:20px;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.1);overflow:hidden;">
    <div style="background:#1a73e8;color:#fff;padding:24px 28px;">
      <h1 style="margin:0;font-size:1.4rem;">✓ Zapis potwierdzony!</h1>
    </div>
    <div style="padding:28px;">
      <p>Cześć <strong>{$data['imie']}</strong>,</p>
      <p>Twój zapis na <strong>bezpłatne testy nart</strong> został przyjęty. Poniżej znajdziesz swoje dane:</p>
      <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:.9rem;">
        <tr><td style="padding:6px 10px;background:#f1f5f9;font-weight:600;width:140px;">Imię i nazwisko</td><td style="padding:6px 10px;">{$data['imie']} {$data['nazwisko']}</td></tr>
        <tr><td style="padding:6px 10px;font-weight:600;">Adres</td><td style="padding:6px 10px;">{$data['adres']}, {$data['miasto']}</td></tr>
        <tr><td style="padding:6px 10px;background:#f1f5f9;font-weight:600;">E-mail</td><td style="padding:6px 10px;">{$data['email']}</td></tr>
        <tr><td style="padding:6px 10px;font-weight:600;">Telefon</td><td style="padding:6px 10px;">{$data['telefon']}</td></tr>
        <tr><td style="padding:6px 10px;background:#f1f5f9;font-weight:600;">Wiek</td><td style="padding:6px 10px;">{$data['wiek']} lat</td></tr>
        <tr><td style="padding:6px 10px;font-weight:600;">Wzrost / Waga</td><td style="padding:6px 10px;">{$data['wzrost']} cm / {$data['waga']} kg</td></tr>
      </table>
      <p style="color:#555;font-size:.88rem;">Jeśli masz pytania, odpowiedz na tego maila lub skontaktuj się z nami.</p>
      <p style="margin-top:24px;">Do zobaczenia na stoku! 🎿</p>
    </div>
    <div style="background:#f1f5f9;padding:16px 28px;font-size:.8rem;color:#6c757d;">
      uciekajzamiasto.pl &nbsp;|&nbsp; zapisy@uciekajzamiasto.pl
    </div>
  </div>
</body>
</html>
HTML;

    if (!@mail($to, $subject, $body, $headers)) {
        error_log('Błąd wysyłki maila do: ' . $to);
        $_SESSION['flash_warning'] = 'Zapis przyjęty, ale nie udało się wysłać e-maila z potwierdzeniem. Skontaktuj się z organizatorem.';
    }
}
