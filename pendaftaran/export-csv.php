<?php
/**
 * export-csv.php — unduh semua data pendaftar sbg CSV, khusus admin login.
 *
 * Urutan & label kolom sengaja dibuat MENGIKUTI PERSIS urutan tab & field
 * di form "Single Registration Player" SIAP PSSI (Pemain > Klub Sebelumnya
 * > Klub Baru), supaya admin tinggal buka file ini di sebelah form SIAP dan
 * copy-paste tiap kolom ke field yang sesuai - SIAP sendiri tidak
 * menyediakan fitur import CSV/bulk, jadi ini jembatan manual yang paling
 * realistis utk sekarang. Kolom yang datanya tidak kita kumpulkan (Provinsi,
 * Kota, Email, dll) dibiarkan kosong supaya admin isi manual di SIAP.
 * Kolom "Referensi Internal Kami" di bagian akhir BUKAN field SIAP - itu
 * cuma supaya admin gampang mencocokkan baris CSV dgn data di admin.php.
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

// Format nomor HP ke awalan 62 (format yg diminta SIAP utk No Handphone)
function format_hp_62($nomor) {
    $nomor = trim((string) $nomor);
    if ($nomor === '') return '';
    $nomor = preg_replace('/[^0-9+]/', '', $nomor);
    if (strpos($nomor, '+62') === 0) return substr($nomor, 1);
    if (strpos($nomor, '62') === 0) return $nomor;
    if (strpos($nomor, '0') === 0) return '62' . substr($nomor, 1);
    return $nomor;
}

$filename = 'siap-pssi-referensi-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM utf-8 supaya karakter & rapi terbaca di Excel

$header = [
    // ---- Tab "Pemain" di SIAP ----
    '[Pemain] Nama Pemain',
    '[Pemain] Nama Populer',
    '[Pemain] Tanggal Lahir',
    '[Pemain] NO ID',
    '[Pemain] Jenis ID',
    '[Pemain] Jenis Kelamin',
    '[Pemain] Negara Lahir',
    '[Pemain] Kota Lahir',
    '[Pemain] Kewarganegaraan',
    '[Pemain] Kaki Terkuat',
    '[Pemain] Tinggi (cm)',
    '[Pemain] Berat (kg)',
    '[Pemain] Alamat',
    '[Pemain] Provinsi (isi manual)',
    '[Pemain] Kota (isi manual)',
    '[Pemain] No Handphone (62..)',
    '[Pemain] Email',
    // ---- Tab "Klub Sebelumnya" ----
    '[Klub Sebelumnya] Federasi Asal Klub Lama',
    '[Klub Sebelumnya] Klub Lama',
    '[Klub Sebelumnya] Ada Perjanjian Klub Lama-Baru?',
    '[Klub Sebelumnya] Alasan Pemutusan Kontrak',
    // ---- Tab "Klub Baru" ----
    '[Klub Baru] Status Pemain (Amatir/Profesional)',
    '[Klub Baru] Intermediary',
    '[Klub Baru] Komisi Intermediary',
    '[Klub Baru] Punya Kontrak Kerja?',
    '[Klub Baru] Tanggal Penandatanganan Kontrak/Perjanjian',
    // ---- Referensi internal kami (BUKAN field SIAP) ----
    '[Internal] Kode Pendaftaran',
    '[Internal] Status Pendaftaran',
    '[Internal] Status Pembayaran',
    '[Internal] Nama Wali',
    '[Internal] HP Wali',
    '[Internal] Catatan Admin',
];
fputcsv($out, $header);

foreach ($rows as $r) {
    $alamatSiswa = $r['alamat_ayah'] ?: ($r['alamat_ibu'] ?: $r['wali_alamat']);
    $noHp = $r['nomor_hp_siswa'] ?: $r['wali_hp'];

    $baris = [
        // Pemain
        $r['nama_lengkap'] ?? '',
        $r['nama_panggilan'] ?? '',
        $r['tanggal_lahir'] ?? '',
        $r['nik_siswa'] ?? '',
        'NIK',
        $r['jenis_kelamin'] ?? '',
        'Indonesia',
        $r['tempat_lahir'] ?? '',
        'Indonesia',
        $r['kaki_dominan'] ?? '',
        $r['tinggi_badan'] ?? '',
        $r['berat_badan'] ?? '',
        $alamatSiswa ?? '',
        '', // Provinsi - tidak dikumpulkan form kita, isi manual
        '', // Kota - tidak dikumpulkan form kita, isi manual
        format_hp_62($noHp),
        '', // Email - tidak dikumpulkan form kita
        // Klub Sebelumnya
        'Indonesia',
        'PENDAFTARAN AWAL',
        'Tidak',
        'Pemain tidak terikat kontrak dengan klub sebelumnya (pemain amatir)',
        // Klub Baru
        'Amatir',
        '',
        '',
        'Tidak',
        $r['tanggal_daftar'] ? date('Y-m-d', strtotime($r['tanggal_daftar'])) : '',
        // Internal
        $r['kode_pendaftaran'] ?? '',
        $r['status'] ?? '',
        $r['status_pembayaran'] ?? '',
        $r['wali_nama_lengkap'] ?? '',
        $r['wali_hp'] ?? '',
        $r['catatan_admin'] ?? '',
    ];
    fputcsv($out, $baris);
}
fclose($out);
