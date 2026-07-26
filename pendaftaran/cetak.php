<?php
// Dokumen ini berisi data pribadi & harus selalu di-generate ulang, tidak boleh
// ikut disimpan oleh cache apa pun (browser/LiteSpeed/CDN).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc-cetak-dokumen.php';

if (empty($_SESSION['petra_admin'])) {
    http_response_code(403);
    die('Akses ditolak. Silakan login lewat admin.php terlebih dahulu.');
}

// ---------- Ambil daftar ID: satu (?id=) atau banyak sekaligus (?ids=1,2,3) ----------
$ids = [];
if (!empty($_GET['ids'])) {
    foreach (explode(',', $_GET['ids']) as $v) {
        $v = (int) trim($v);
        if ($v > 0) $ids[] = $v;
    }
    $ids = array_values(array_unique($ids));
    if (count($ids) > 50) $ids = array_slice($ids, 0, 50); // batas wajar sekali cetak
} elseif (!empty($_GET['id'])) {
    $ids = [(int) $_GET['id']];
}
if (!$ids) die('ID pendaftaran tidak valid.');

try {
    $pdo = get_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM pendaftaran WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll() as $row) $byId[$row['id']] = $row;
    // urutkan sesuai urutan ids yang diminta (mis. urutan pilih di admin.php)
    $rows = [];
    foreach ($ids as $reqId) if (isset($byId[$reqId])) $rows[] = $byId[$reqId];
} catch (Exception $e) {
    die('Gagal mengambil data dari database. Server database sedang sibuk, coba lagi sesaat lagi.');
}
if (!$rows) die('Data pendaftaran tidak ditemukan.');

$jenis = $_GET['jenis'] ?? 'semua';
$validJenis = ['semua','formulir','persetujuan','pernyataan','amatir'];
if (!in_array($jenis, $validJenis)) $jenis = 'semua';

$judulBar = count($rows) === 1
    ? v($rows[0]['nama_lengkap'], '') . ' — ' . htmlspecialchars($rows[0]['kode_pendaftaran'])
    : count($rows) . ' dokumen pendaftar terpilih';

render_print_page($rows, $jenis, $judulBar);
