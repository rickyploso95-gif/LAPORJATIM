<?php
require_once __DIR__ . '/../includes/auth.php';
send_security_headers();

if (current_admin()) { header('Location: index.php'); exit; }

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
        $error = 'Masukkan email dan password yang valid.';
    } else {
        $error = attempt_login($email, $pass);
        if ($error === null) { header('Location: index.php'); exit; }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Login Admin - LaporJatim</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="text-center mb-3">
            <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.3rem"><i class="fa-solid fa-location-dot"></i></span>
            <h1 class="h4 brand-font mb-0">Login admin</h1>
            <p class="text-muted small">Khusus pengelola LaporJatim. Pelapor tidak perlu login.</p>
        </div>
        <form method="post" class="panel p-4" autocomplete="on">
            <?= csrf_field() ?>
            <?php if ($error): ?><div class="alert alert-danger py-2 small" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary w-100" type="submit">Masuk</button>
        </form>
        <p class="text-center mt-3 small"><a href="<?= BASE_URL ?>/index.php"><i class="fa-solid fa-arrow-left me-1"></i>Kembali ke situs publik</a></p>
    </div>
</div>
</body>
</html>
