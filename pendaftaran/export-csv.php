<?php
/**
 * export-csv.php — unduh semua data pendaftar sbg CSV, khusus admin login.
 * Berguna sbg cadangan data & bahan input manual ke sistem lain (mis. SIAP
 * PSSI) yang belum terintegrasi otomatis dengan sistem ini.
 */

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['petra_admin'])) {
    http_response_code(403);
    die('Akses ditolak. Silakan login lewat admin.php terlebih dahulu.');
}

try {
    $pdo = get_db();
    $rows = $pdo->query('SELECT * FROM pendaftaran ORDER BY tanggal_daftar ASC')->fetchAll();
} catch (Exception $e) {
    http_response_code(500);
    die('Gagal mengambil data dari database. Server database sedang sibuk, coba lagi sesaat lagi.');
}

$filename = 'pendaftaran-ssb-petra-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM utf-8 supaya karakter & rapi terbaca di Excel

$kolom = [
    'kode_pendaftaran' => 'Kode Pendaftaran',
    'tanggal_daftar' => 'Tanggal Daftar',
    'jenis_pendaftar' => 'Jenis Pendaftar',
    'status' => 'Status',
    'status_pembayaran' => 'Status Pembayaran',
    'materai_status' => 'Status Materai',
    'nama_lengkap' => 'Nama Lengkap Siswa',
    'nama_panggilan' => 'Nama Panggilan',
    'nik_siswa' => 'NIK Siswa',
    'nisn' => 'NISN',
    'jenis_kelamin' => 'Jenis Kelamin',
    'tempat_lahir' => 'Tempat Lahir',
    'tanggal_lahir' => 'Tanggal Lahir',
    'pendidikan' => 'Pendidikan',
    'nomor_hp_siswa' => 'No. HP Siswa',
    'tinggi_badan' => 'Tinggi Badan (cm)',
    'berat_badan' => 'Berat Badan (kg)',
    'golongan_darah' => 'Golongan Darah',
    'kaki_dominan' => 'Kaki Dominan',
    'posisi_bermain' => 'Posisi Bermain',
    'nama_ayah' => 'Nama Ayah',
    'hp_ayah' => 'No. HP Ayah',
    'nama_ibu' => 'Nama Ibu',
    'hp_ibu' => 'No. HP Ibu',
    'wali_nama_lengkap' => 'Nama Wali',
    'wali_nik' => 'NIK Wali',
    'wali_hp' => 'No. HP Wali',
    'wali_hubungan' => 'Hubungan Wali',
    'paket_pendaftaran' => 'Paket Pendaftaran',
    'nominal_kondisi_ekonomi' => 'Nominal Kondisi Ekonomi',
    'klaim_lunas_lama' => 'Klaim Lunas (Pemain Lama)',
    'catatan_admin' => 'Catatan Admin',
];

fputcsv($out, array_values($kolom));
foreach ($rows as $r) {
    $baris = [];
    foreach (array_keys($kolom) as $c) {
        $baris[] = $r[$c] ?? '';
    }
    fputcsv($out, $baris);
}
fclose($out);
