<?php
/**
 * cetak-publik.php — versi cetak.php untuk orang tua/wali sendiri, TANPA
 * perlu login admin. Aksesnya lewat kode_pendaftaran yang hanya diketahui
 * pendaftar ybs (ditampilkan di layar sukses & link ?lanjut= setelah
 * submit.php) - bukan lewat id database berurutan seperti di admin.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc-cetak-dokumen.php';

$kode = trim($_GET['kode'] ?? '');
if ($kode === '') die('Kode pendaftaran tidak valid.');

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM pendaftaran WHERE kode_pendaftaran = :k');
    $stmt->execute(['k' => $kode]);
    $r = $stmt->fetch();
} catch (Exception $e) {
    die('Gagal mengambil data dari database. Server database sedang sibuk, coba lagi sesaat lagi.');
}
if (!$r) die('Kode pendaftaran tidak ditemukan. Periksa kembali kode/link Anda.');

$jenis = $_GET['jenis'] ?? 'semua';
$validJenis = ['semua','formulir','persetujuan','pernyataan','amatir'];
if (!in_array($jenis, $validJenis)) $jenis = 'semua';

$judulBar = v($r['nama_lengkap'], '') . ' — ' . htmlspecialchars($r['kode_pendaftaran']);

render_print_page([$r], $jenis, $judulBar);
