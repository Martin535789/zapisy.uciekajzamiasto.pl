<?php
/**
 * admin.php – Panel administratora
 * Logowanie i zarządzanie uczestnikami
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

startSecureSession();

// Obsługa timeout sesji
$timeoutMsg = null;
if (isset($_GET['timeout'])) {
    $timeoutMsg = 'Sesja wygasła. Zaloguj się ponownie.';
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf = getCsrfToken();
$e    = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/* ---------------------------------------------------------------
   Dane dla panelu (tylko gdy zalogowany)
   --------------------------------------------------------------- */
$participants = [];
$count        = 0;
if ($isLoggedIn) {
    $pdo          = getDB();
    $stmt         = $pdo->query(
        'SELECT id, imie, nazwisko, adres, miasto, email, telefon, wiek, wzrost, waga, data_zapisu
         FROM participants
         ORDER BY data_zapisu ASC'
    );
    $participants = $stmt->fetchAll();
    $count        = count($participants);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel admina – Zapisy na testy nart</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- ===============================================================
     FORMULARZ LOGOWANIA
     =============================================================== -->
<div class="login-wrapper">
    <div class="login-card">
        <div class="logo">
            <i class="bi bi-shield-lock"></i>
            <h4 class="mt-2 fw-bold">Panel administratora</h4>
            <p class="text-muted small">Zapisy na testy nart</p>
        </div>

        <?php if ($timeoutMsg): ?>
            <div class="alert alert-warning small"><?= $e($timeoutMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger small"><?= $e($flashError) ?></div>
        <?php endif; ?>

        <form method="post" action="admin_process.php">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Nazwa użytkownika</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username"
                           required autocomplete="username" placeholder="admin">
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label fw-semibold">Hasło</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           required autocomplete="current-password" placeholder="••••••••">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Zaloguj się
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="index.php" class="text-muted small">
                <i class="bi bi-arrow-left me-1"></i>Wróć na stronę główną
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===============================================================
     PANEL ADMINA
     =============================================================== -->

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin.php">
            <i class="bi bi-shield-check me-2"></i>Panel administratora
        </a>
        <span class="ms-auto d-flex align-items-center gap-3">
            <span class="text-light small d-none d-md-inline">
                <i class="bi bi-person-circle me-1"></i><?= $e($_SESSION['admin_username'] ?? '') ?>
            </span>
            <form method="post" action="admin_process.php" class="mb-0">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Wyloguj
                </button>
            </form>
        </span>
    </div>
</nav>

<div class="container-fluid py-4 px-4">

    <!-- Flash messages -->
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success d-flex gap-2 align-items-start">
            <i class="bi bi-check-circle-fill mt-1"></i><div><?= $e($flashSuccess) ?></div>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i><div><?= $e($flashError) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-primary"><?= $count ?></div>
                    <div class="small text-muted">Uczestników</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-<?= $count < MAX_PARTICIPANTS ? 'success' : 'danger' ?>">
                        <?= MAX_PARTICIPANTS - $count ?>
                    </div>
                    <div class="small text-muted">Wolnych miejsc</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-<?= $count < MAX_PARTICIPANTS ? 'success' : 'danger' ?>">
                        <?= $count < MAX_PARTICIPANTS ? 'Otwarte' : 'Pełne' ?>
                    </div>
                    <div class="small text-muted">Status zapisów</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Eksport + Reset -->
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <strong class="me-2">Akcje:</strong>
            <a href="export.php?format=csv" class="btn btn-success btn-sm">
                <i class="bi bi-filetype-csv me-1"></i>Eksport CSV
            </a>
            <a href="export.php?format=xlsx" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Eksport Excel
            </a>
            <button type="button" class="btn btn-danger btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#resetModal">
                <i class="bi bi-trash3 me-1"></i>Resetuj listę
            </button>
        </div>
    </div>

    <!-- Tabela uczestników -->
    <div class="card">
        <div class="card-header-custom">
            <i class="bi bi-table me-2"></i>Lista uczestników
            <span class="badge bg-light text-dark ms-2"><?= $count ?> / <?= MAX_PARTICIPANTS ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($participants)): ?>
                <p class="text-muted text-center py-4">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Brak zapisanych uczestników.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-admin table-bordered table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Imię</th>
                                <th>Nazwisko</th>
                                <th>Adres</th>
                                <th>Miasto</th>
                                <th>E-mail</th>
                                <th>Telefon</th>
                                <th>Wiek</th>
                                <th>Wzrost</th>
                                <th>Waga</th>
                                <th>Data zapisu</th>
                                <th>Akcja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $i => $p): ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td><?= $e($p['imie']) ?></td>
                                    <td><?= $e($p['nazwisko']) ?></td>
                                    <td><?= $e($p['adres']) ?></td>
                                    <td><?= $e($p['miasto']) ?></td>
                                    <td><a href="mailto:<?= $e($p['email']) ?>"><?= $e($p['email']) ?></a></td>
                                    <td><?= $e($p['telefon']) ?></td>
                                    <td class="text-center"><?= (int) $p['wiek'] ?></td>
                                    <td class="text-center"><?= (int) $p['wzrost'] ?> cm</td>
                                    <td class="text-center"><?= $e($p['waga']) ?> kg</td>
                                    <td class="text-nowrap"><?= $e($p['data_zapisu']) ?></td>
                                    <td>
                                        <form method="post" action="admin_process.php"
                                              onsubmit="return confirm('Czy na pewno chcesz usunąć ten zapis?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /container -->

<!-- Modal: Resetowanie listy -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="resetModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Resetowanie listy uczestników
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="admin_process.php">
                <div class="modal-body">
                    <p class="text-danger fw-bold">Uwaga! Ta operacja jest nieodwracalna.</p>
                    <p>Zostaną usunięci <strong>wszyscy (<?= $count ?>)</strong> uczestnicy.</p>
                    <p>Wpisz <code>RESETUJ</code>, aby potwierdzić:</p>
                    <input type="text" class="form-control" name="confirm_reset"
                           placeholder="Wpisz RESETUJ" required pattern="RESETUJ">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Resetuj listę
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
