<?php
require_once __DIR__ . '/_layout.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['password'] ?? '');
    $conf = (string)($_POST['confirm'] ?? '');
    $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $st->execute([(int)$admin['id']]);
    if (!password_verify($cur, (string)$st->fetchColumn())) {
        flash('error', 'Password saat ini salah.');
    } elseif ($new !== $conf) {
        flash('error', 'Konfirmasi password tidak sama.');
    } elseif ($err = password_error($new)) {
        flash('error', $err);
    } else {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), (int)$admin['id']]);
        session_regenerate_id(true);
        flash('ok', 'Password berhasil diganti.');
    }
    header('Location: profile.php');
    exit;
}

admin_header('Akun saya', 'profile', $admin);
?>
<div class="row"><div class="col-md-8 col-xl-5">
    <form method="post" class="panel p-3 p-md-4" autocomplete="off">
        <h2 class="h6 brand-font mb-1"><?= e($admin['name']) ?></h2>
        <p class="small text-muted mb-3"><?= e($admin['email']) ?></p>
        <?= csrf_field() ?>
        <div class="mb-2"><label class="form-label" for="current">Password saat ini</label><input type="password" class="form-control" id="current" name="current" required autocomplete="current-password"></div>
        <div class="mb-2"><label class="form-label" for="password">Password baru</label><input type="password" class="form-control" id="password" name="password" required minlength="10" autocomplete="new-password"><div class="form-text">Minimal 10 karakter, mengandung huruf dan angka.</div></div>
        <div class="mb-3"><label class="form-label" for="confirm">Ulangi password baru</label><input type="password" class="form-control" id="confirm" name="confirm" required autocomplete="new-password"></div>
        <button class="btn btn-primary">Simpan password</button>
    </form>
</div></div>
<?php admin_footer(); ?>
