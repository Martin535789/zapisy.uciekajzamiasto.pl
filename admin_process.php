<?php
/**
 * admin_process.php – logika panelu administracyjnego
 * Obsługuje: logowanie, wylogowanie, usuwanie zapisu, resetowanie listy
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$action = trim($_POST['action'] ?? '');

// ---------------------------------------------------------------
// LOGOWANIE – nie wymaga istniejącej sesji admina
// ---------------------------------------------------------------
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, password FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = (int) $user['id'];
        $_SESSION['admin_username']  = $username;
        $_SESSION['admin_login_time'] = time();
        header('Location: admin.php');
    } else {
        $_SESSION['flash_error'] = 'Nieprawidłowa nazwa użytkownika lub hasło.';
        header('Location: admin.php');
    }
    exit;
}

// ---------------------------------------------------------------
// Pozostałe akcje wymagają zalogowanego admina
// ---------------------------------------------------------------
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Sprawdzenie czasu sesji
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > SESSION_LIFETIME) {
    session_destroy();
    header('Location: admin.php?timeout=1');
    exit;
}

// Weryfikacja CSRF dla akcji modyfikujących dane
$csrfToken = trim($_POST['csrf_token'] ?? '');
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Nieprawidłowy token bezpieczeństwa.';
    header('Location: admin.php');
    exit;
}

$pdo = getDB();

switch ($action) {
    // ---------------------------------------------------------------
    // Wylogowanie
    // ---------------------------------------------------------------
    case 'logout':
        session_destroy();
        header('Location: admin.php');
        break;

    // ---------------------------------------------------------------
    // Usunięcie zapisu
    // ---------------------------------------------------------------
    case 'delete':
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $stmt = $pdo->prepare('DELETE FROM participants WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['flash_success'] = 'Zapis został usunięty.';
        } else {
            $_SESSION['flash_error'] = 'Nieprawidłowy identyfikator zapisu.';
        }
        header('Location: admin.php');
        break;

    // ---------------------------------------------------------------
    // Reset całej listy
    // ---------------------------------------------------------------
    case 'reset':
        $confirm = trim($_POST['confirm_reset'] ?? '');
        if ($confirm === 'RESETUJ') {
            $pdo->exec('DELETE FROM participants');
            $_SESSION['flash_success'] = 'Lista uczestników została zresetowana.';
        } else {
            $_SESSION['flash_error'] = 'Podaj słowo potwierdzające, aby zresetować listę.';
        }
        header('Location: admin.php');
        break;

    default:
        header('Location: admin.php');
}

exit;
