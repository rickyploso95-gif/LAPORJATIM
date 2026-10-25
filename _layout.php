<?php
require_once __DIR__ . '/../includes/auth.php';

function admin_header(string $title, string $active, array $admin, bool $leaflet = false): void {
    send_security_headers();
    $nav = [
        'index'   => ['index.php', 'fa-gauge-high', 'Ringkasan'],
        'reports' => ['reports.php', 'fa-list-check', 'Semua laporan'],
        'map'     => ['map.php', 'fa-map-location-dot', 'Peta laporan'],
    ];
    if ($admin['role'] === 'superadmin') $nav['admins'] = ['admins.php', 'fa-users-gear', 'Akun admin'];
    $nav['profile'] = ['profile.php', 'fa-user-shield', 'Akun saya'];
    $alertClass = ['ok' => 'success', 'error' => 'danger', 'warn' => 'warning'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> - LaporJatim Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <?php if ($leaflet): ?><link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet"><?php endif; ?>
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body" data-base="<?= e(BASE_URL) ?>">
<aside class="sidebar" id="sidebar">
    <a class="brand brand-font" href="index.php"><span class="brand-mark"><i class="fa-solid fa-location-dot"></i></span>LaporJatim</a>
    <nav>
        <?php foreach ($nav as $key => [$href, $icon, $label]): ?>
            <a class="nav-item-link <?= $key === $active ? 'active' : '' ?>" href="<?= e($href) ?>"><i class="fa-solid <?= e($icon) ?>"></i><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="nav-item-link" href="<?= BASE_URL ?>/index.php" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i>Lihat situs publik</a>
    </nav>
    <div class="side-foot">
        <div class="text-white fw-semibold text-truncate"><?= e($admin['name']) ?></div>
        <div class="text-truncate mb-2"><?= e($admin['email']) ?></div>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-light w-100"><i class="fa-solid fa-right-from-bracket me-1"></i>Keluar</button>
        </form>
    </div>
</aside>
<div class="admin-main">
    <header class="admin-top">
        <button class="btn btn-outline-secondary d-lg-none" id="sb-toggle" type="button" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
        <h1 class="h5 mb-0 brand-font"><?= e($title) ?></h1>
        <span class="ms-auto badge text-bg-light border"><?= $admin['role'] === 'superadmin' ? 'Superadmin' : 'Admin' ?></span>
    </header>
    <div class="admin-content">
    <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= $alertClass[$type] ?? 'info' ?> alert-dismissible fade show" role="alert">
            <?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endforeach; ?>
<?php
}

function admin_footer(bool $leaflet = false): void {
?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($leaflet): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
<?php
}
