<?php
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    do_logout();
}
header('Location: login.php');
exit;
