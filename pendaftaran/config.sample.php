<?php
/**
 * KONFIGURASI SSB PETRA SENTANI - Form Pendaftaran Online
 * ---------------------------------------------------------
 * Salin file ini menjadi `config.php` LANGSUNG DI SERVER (bukan di repo git),
 * lalu isi nilai-nilai di bawah sesuai data hosting Hostinger Anda.
 * `config.php` sengaja ada di .gitignore supaya kredensial asli tidak pernah
 * ter-commit ke git.
 */

// --- Database (isi dengan data dari hPanel) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'GANTI_NAMA_DATABASE');
define('DB_USER', 'GANTI_USERNAME_DB');
define('DB_PASS', 'GANTI_PASSWORD_DB');

// --- Email notifikasi admin ---
define('ADMIN_EMAIL', 'admin@petrafc.com');   // email penerima notifikasi pendaftaran baru
define('MAIL_FROM', 'noreply@petrafc.com');   // sebaiknya alamat @petrafc.com (buat di hPanel > Emails)
define('MAIL_FROM_NAME', 'SSB PETRA Sentani - Pendaftaran');

// --- Login halaman admin (admin.php) ---
define('ADMIN_USERNAME', 'GANTI_USERNAME_ADMIN');
define('ADMIN_PASSWORD', 'GANTI_PASSWORD_ADMIN_YANG_KUAT');

// --- Batas ukuran & jenis file upload ---
define('MAX_FILE_SIZE_MB', 5);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// --- Folder penyimpanan file upload (relatif terhadap folder ini) ---
define('UPLOAD_DIR', __DIR__ . '/uploads');

// Jangan ubah di bawah ini
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
    return $pdo;
}
