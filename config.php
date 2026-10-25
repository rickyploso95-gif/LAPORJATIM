<?php
// Konfigurasi aplikasi. Sesuaikan dengan lingkungan XAMPP Anda.
define('APP_NAME', 'LaporJatim');
define('APP_DEBUG', false);                  // true hanya saat pengembangan
define('BASE_URL', '/laporjatim');           // ubah jika nama folder di htdocs berbeda ('' jika di root)
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

define('MAX_UPLOAD_BYTES', 3 * 1024 * 1024); // 3 MB
define('PHOTO_REQUIRED', true);              // false = foto bukti opsional
define('PER_PAGE_PUBLIC', 9);
define('PER_PAGE_ADMIN', 15);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);

// Batas kasar koordinat Jawa Timur (termasuk Madura dan kepulauan)
define('LAT_MIN', -9.2);  define('LAT_MAX', -5.3);
define('LNG_MIN', 110.8); define('LNG_MAX', 116.0);

date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
