<?php
/**
 * ambil-data.php — dipanggil oleh index.html saat orang tua membuka link
 * ?lanjut=KODE untuk melengkapi pendaftaran yang sudah pernah dimulai.
 * Mengembalikan data pendaftaran (tanpa path file mentah, cukup penanda
 * "sudah ada/belum") berdasarkan kode_pendaftaran yang persis cocok.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/config.php';

$kode = trim($_GET['kode'] ?? '');
if ($kode === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Kode tidak valid.']);
    exit;
}

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM pendaftaran WHERE kode_pendaftaran = :k');
    $stmt->execute(['k' => $kode]);
    $r = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal mengambil data. Server database sedang sibuk, coba lagi sesaat lagi.']);
    exit;
}

if (!$r) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Kode pendaftaran tidak ditemukan. Periksa kembali link/kode Anda.']);
    exit;
}

$fileFields = [
    'file_akte_lahir', 'file_ijazah_raport', 'file_kartu_keluarga',
    'file_raport_dalam', 'file_nisn', 'file_kia', 'file_pas_foto', 'file_tanda_tangan',
    'file_bukti_transfer',
];
$sudahAda = [];
foreach ($fileFields as $f) {
    $sudahAda[$f] = !empty($r[$f]);
}

$data = $r;
foreach ($fileFields as $f) unset($data[$f]); // jangan kirim path file mentah
unset($data['ip_pendaftar'], $data['id']);

echo json_encode(['ok' => true, 'data' => $data, 'sudah_ada' => $sudahAda]);
