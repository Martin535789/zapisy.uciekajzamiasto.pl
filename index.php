<?php
/**
 * index.php – Publiczna strona zapisów na testy nart
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

startSecureSession();

$pdo = getDB();

// Liczba uczestników
$count     = (int) $pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();
$spotsLeft = max(0, MAX_PARTICIPANTS - $count);
$isOpen    = $spotsLeft > 0;

// Publiczna lista uczestników (imię, pierwsza litera nazwiska, miasto)
$listStmt  = $pdo->query('SELECT imie, nazwisko, miasto FROM participants ORDER BY data_zapisu ASC');
$participants = $listStmt->fetchAll();

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashWarning = $_SESSION['flash_warning'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
$formData     = $_SESSION['form_data']     ?? [];
unset($_SESSION['flash_success'], $_SESSION['flash_warning'], $_SESSION['flash_error'], $_SESSION['form_data']);

$csrf = getCsrfToken();

// Procent zajętości
$pct = $count > 0 ? (int) round($count / MAX_PARTICIPANTS * 100) : 0;
$barClass = $pct < 60 ? '' : ($pct < 90 ? 'warning' : 'danger');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zapisy na testy nart – Uciekaj Za Miasto</title>
    <meta name="description" content="Bezpłatne testy nart – zapisz się już teraz! Maksymalnie 10 miejsc.">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-snow me-2"></i>Uciekaj Za Miasto
        </a>
        <span class="ms-auto">
            <a href="admin.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-lock me-1"></i>Admin
            </a>
        </span>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <h1><i class="bi bi-snow2 me-2"></i>Testy nart – Zapisz się!</h1>
        <p>Dołącz do bezpłatnych testów nart. Wypróbuj najlepszy sprzęt na stoku razem z nami!</p>

        <div class="counter-badge<?= $isOpen ? '' : ' full' ?>">
            <i class="bi bi-people-fill"></i>
            <?= $count ?> / <?= MAX_PARTICIPANTS ?> miejsc zajętych
        </div>

        <div class="mt-2">
            <span class="status-pill <?= $isOpen ? 'open' : 'closed' ?>">
                <?= $isOpen ? '✓ Zapisy otwarte' : '✗ Brak wolnych miejsc' ?>
            </span>
        </div>

        <div style="max-width:280px;margin:.8rem auto 0;">
            <div class="spots-bar">
                <div class="spots-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
    </div>
</section>

<div class="container pb-5">
    <div class="row g-4">

        <!-- ---- Formularz ---- -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header-custom">
                    <i class="bi bi-pencil-square me-2"></i>Formularz zapisu
                </div>
                <div class="card-body p-4">

                    <?php if ($flashSuccess): ?>
                        <div class="alert alert-success d-flex gap-2">
                            <i class="bi bi-check-circle-fill mt-1"></i>
                            <div><?= $flashSuccess ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($flashWarning): ?>
                        <div class="alert alert-warning d-flex gap-2">
                            <i class="bi bi-exclamation-circle-fill mt-1"></i>
                            <div><?= $flashWarning ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($flashError): ?>
                        <div class="alert alert-danger d-flex gap-2">
                            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                            <div><?= $flashError ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isOpen): ?>
                        <div class="alert alert-warning text-center">
                            <i class="bi bi-x-circle-fill me-2"></i>
                            <strong>Przepraszamy – wszystkie miejsca są zajęte.</strong><br>
                            Formularz zapisów jest zamknięty.
                        </div>
                    <?php else: ?>

                    <form method="post" action="process_signup.php" id="signupForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">

                        <div class="row g-3">
                            <!-- Imię -->
                            <div class="col-sm-6">
                                <label for="imie" class="form-label">Imię <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="imie" name="imie"
                                       value="<?= $e($formData['imie'] ?? '') ?>"
                                       minlength="2" maxlength="100" required
                                       placeholder="np. Anna">
                                <div class="invalid-feedback">Imię musi mieć min. 2 znaki.</div>
                            </div>
                            <!-- Nazwisko -->
                            <div class="col-sm-6">
                                <label for="nazwisko" class="form-label">Nazwisko <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nazwisko" name="nazwisko"
                                       value="<?= $e($formData['nazwisko'] ?? '') ?>"
                                       minlength="2" maxlength="100" required
                                       placeholder="np. Kowalska">
                                <div class="invalid-feedback">Nazwisko musi mieć min. 2 znaki.</div>
                            </div>
                            <!-- Adres -->
                            <div class="col-12">
                                <label for="adres" class="form-label">Adres (ulica, nr domu) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="adres" name="adres"
                                       value="<?= $e($formData['adres'] ?? '') ?>"
                                       minlength="5" maxlength="255" required
                                       placeholder="np. ul. Kwiatowa 5/10">
                                <div class="invalid-feedback">Podaj pełny adres.</div>
                            </div>
                            <!-- Miasto -->
                            <div class="col-sm-6">
                                <label for="miasto" class="form-label">Miasto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="miasto" name="miasto"
                                       value="<?= $e($formData['miasto'] ?? '') ?>"
                                       minlength="2" maxlength="100" required
                                       placeholder="np. Warszawa">
                                <div class="invalid-feedback">Podaj miasto.</div>
                            </div>
                            <!-- Email -->
                            <div class="col-sm-6">
                                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= $e($formData['email'] ?? '') ?>"
                                       maxlength="255" required
                                       placeholder="np. anna@przykład.pl">
                                <div class="invalid-feedback">Podaj prawidłowy adres e-mail.</div>
                            </div>
                            <!-- Telefon -->
                            <div class="col-sm-6">
                                <label for="telefon" class="form-label">Telefon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="telefon" name="telefon"
                                       value="<?= $e($formData['telefon'] ?? '') ?>"
                                       pattern="^\+?[\d\s\-().]{7,20}$" required
                                       placeholder="np. 600 100 200">
                                <div class="invalid-feedback">Podaj prawidłowy numer telefonu.</div>
                            </div>
                            <!-- Wiek -->
                            <div class="col-sm-6">
                                <label for="wiek" class="form-label">Wiek <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="wiek" name="wiek"
                                       value="<?= $e($formData['wiek'] ?? '') ?>"
                                       min="5" max="120" required
                                       placeholder="np. 35">
                                <div class="invalid-feedback">Podaj wiek (5–120).</div>
                            </div>
                            <!-- Wzrost -->
                            <div class="col-sm-6">
                                <label for="wzrost" class="form-label">Wzrost (cm) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="wzrost" name="wzrost"
                                       value="<?= $e($formData['wzrost'] ?? '') ?>"
                                       min="50" max="250" required
                                       placeholder="np. 175">
                                <div class="invalid-feedback">Podaj wzrost w cm (50–250).</div>
                            </div>
                            <!-- Waga -->
                            <div class="col-sm-6">
                                <label for="waga" class="form-label">Waga (kg) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="waga" name="waga"
                                       value="<?= $e($formData['waga'] ?? '') ?>"
                                       min="20" max="500" step="0.1" required
                                       placeholder="np. 70">
                                <div class="invalid-feedback">Podaj wagę w kg (20–500).</div>
                            </div>

                            <div class="col-12 mt-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-check-lg me-2"></i>Zapisz się na testy nart
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /formularz -->

        <!-- ---- Lista uczestników ---- -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header-custom">
                    <i class="bi bi-people me-2"></i>Lista uczestników
                    <span class="badge bg-light text-dark ms-2"><?= $count ?> / <?= MAX_PARTICIPANTS ?></span>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($participants)): ?>
                        <p class="text-muted text-center py-3">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Brak zapisanych uczestników.
                        </p>
                    <?php else: ?>
                        <?php foreach ($participants as $i => $p): ?>
                            <div class="participant-item">
                                <div class="participant-avatar"><?= $e(mb_strtoupper(mb_substr($p['imie'], 0, 1))) ?></div>
                                <div class="participant-info">
                                    <div class="name"><?= $e($p['imie']) ?> <?= $e(mb_strtoupper(mb_substr($p['nazwisko'], 0, 1))) ?>.</div>
                                    <div class="city"><i class="bi bi-geo-alt me-1"></i><?= $e($p['miasto']) ?></div>
                                </div>
                                <span class="ms-auto badge bg-secondary"><?= $i + 1 ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info box -->
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>Informacje</h6>
                    <ul class="list-unstyled mb-0 small text-muted">
                        <li class="mb-1"><i class="bi bi-check2 text-success me-1"></i>Zajęcia bezpłatne</li>
                        <li class="mb-1"><i class="bi bi-check2 text-success me-1"></i>Limit: <?= MAX_PARTICIPANTS ?> osób</li>
                        <li class="mb-1"><i class="bi bi-check2 text-success me-1"></i>Potwierdzenie e-mailem</li>
                        <li><i class="bi bi-check2 text-success me-1"></i>Dane chronione zgodnie z RODO</li>
                    </ul>
                </div>
            </div>
        </div><!-- /lista -->

    </div>
</div>

<footer>
    &copy; <?= date('Y') ?> uciekajzamiasto.pl &nbsp;|&nbsp; Wszystkie prawa zastrzeżone
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Walidacja Bootstrap po stronie klienta
(function () {
    'use strict';
    var form = document.getElementById('signupForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
})();
</script>
</body>
</html>
