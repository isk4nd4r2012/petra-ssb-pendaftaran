<?php
/**
 * submit.php — menerima data dari index.html, menyimpan file upload,
 * menyimpan ke database MySQL, dan mengirim email notifikasi ke admin.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Metode tidak diizinkan.', 405);
}

// ---------- Ambil & bersihkan input teks ----------
function s($key) {
    return isset($_POST[$key]) ? trim(strip_tags($_POST[$key])) : null;
}

$data = [
    'nama_lengkap'            => s('nama_lengkap'),
    'nama_panggilan'          => s('nama_panggilan'),
    'nik_siswa'                => s('nik_siswa'),
    'nisn'                     => s('nisn'),
    'jenis_kelamin'            => s('jenis_kelamin'),
    'tempat_lahir'             => s('tempat_lahir'),
    'tanggal_lahir'            => s('tanggal_lahir') ?: null,
    'pendidikan'               => s('pendidikan'),
    'nomor_hp_siswa'           => s('nomor_hp_siswa'),
    'tinggi_badan'             => s('tinggi_badan') ?: null,
    'berat_badan'              => s('berat_badan') ?: null,
    'golongan_darah'           => s('golongan_darah'),
    'riwayat_operasi_tulang'   => s('riwayat_operasi_tulang'),
    'kaki_dominan'             => s('kaki_dominan') ?: null,
    'posisi_bermain'           => s('posisi_bermain'),

    'nama_ayah'                => s('nama_ayah'),
    'alamat_ayah'              => s('alamat_ayah'),
    'hp_ayah'                  => s('hp_ayah'),
    'pekerjaan_ayah'           => s('pekerjaan_ayah'),

    'nama_ibu'                 => s('nama_ibu'),
    'alamat_ibu'               => s('alamat_ibu'),
    'hp_ibu'                   => s('hp_ibu'),
    'pekerjaan_ibu'            => s('pekerjaan_ibu'),

    'wali_nama_lengkap'        => s('wali_nama_lengkap'),
    'wali_nik'                 => s('wali_nik'),
    'wali_tempat_lahir'        => s('wali_tempat_lahir'),
    'wali_tanggal_lahir'       => s('wali_tanggal_lahir') ?: null,
    'wali_alamat'              => s('wali_alamat'),
    'wali_hp'                  => s('wali_hp'),
    'wali_hubungan'            => s('wali_hubungan'),
];

// ---------- Validasi wajib ----------
$required = ['nama_lengkap', 'jenis_kelamin', 'wali_nama_lengkap', 'wali_hp'];
foreach ($required as $r) {
    if (empty($data[$r])) fail('Field wajib belum lengkap: ' . $r);
}

foreach (['setuju_data_pribadi', 'setuju_pernyataan_pemain', 'setuju_perjanjian_amatir'] as $chk) {
    if (empty($_POST[$chk])) fail('Semua persetujuan wajib dicentang.');
}
$data['setuju_data_pribadi'] = 1;
$data['setuju_pernyataan_pemain'] = 1;
$data['setuju_perjanjian_amatir'] = 1;

if (empty($_POST['tanda_tangan_dataurl'])) {
    fail('Tanda tangan wajib diisi.');
}

// ---------- Siapkan folder upload untuk pendaftaran ini ----------
$kode = 'SP' . date('ym') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
$folder = UPLOAD_DIR . '/' . $kode;
if (!is_dir($folder)) {
    if (!mkdir($folder, 0755, true)) fail('Gagal menyiapkan folder upload di server.', 500);
}

// ---------- Simpan file upload ----------
function save_upload($fieldName, $folder, $kode) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) fail('Gagal upload file: ' . $fieldName);

    if ($file['size'] > MAX_FILE_SIZE_MB * 1024 * 1024) {
        fail('File ' . $fieldName . ' melebihi ' . MAX_FILE_SIZE_MB . 'MB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        fail('Jenis file tidak didukung untuk ' . $fieldName . ' (' . $ext . ').');
    }
    $safeName = $fieldName . '.' . $ext;
    $dest = $folder . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        fail('Gagal menyimpan file ' . $fieldName . ' di server.', 500);
    }
    return $kode . '/' . $safeName;
}

$fileFields = [
    'file_akte_lahir', 'file_ijazah_raport', 'file_kartu_keluarga',
    'file_raport_dalam', 'file_nisn', 'file_kia', 'file_pas_foto',
];
$savedFiles = [];
foreach ($fileFields as $f) {
    $savedFiles[$f] = save_upload($f, $folder, $kode);
}

// Dokumen wajib
if (empty($savedFiles['file_akte_lahir']) || empty($savedFiles['file_kartu_keluarga'])) {
    fail('Akte lahir dan Kartu Keluarga wajib dilampirkan.');
}

// ---------- Simpan tanda tangan (base64 PNG) ----------
$sigData = $_POST['tanda_tangan_dataurl'];
if (preg_match('/^data:image\/png;base64,(.+)$/', $sigData, $m)) {
    $sigBin = base64_decode($m[1]);
    $sigPath = $folder . '/tanda_tangan.png';
    file_put_contents($sigPath, $sigBin);
    $savedFiles['file_tanda_tangan'] = $kode . '/tanda_tangan.png';
} else {
    fail('Format tanda tangan tidak valid.');
}

// ---------- Simpan ke database ----------
try {
    $pdo = get_db();
    $cols = array_merge(
        ['kode_pendaftaran'],
        array_keys($data),
        ['setuju_data_pribadi', 'setuju_pernyataan_pemain', 'setuju_perjanjian_amatir'],
        array_keys($savedFiles),
        ['ip_pendaftar']
    );
    $cols = array_unique($cols);

    $values = array_merge(
        ['kode_pendaftaran' => $kode],
        $data,
        $savedFiles,
        ['ip_pendaftar' => $_SERVER['REMOTE_ADDR'] ?? null]
    );

    $placeholders = array_map(fn($c) => ':' . $c, $cols);
    $sql = 'INSERT INTO pendaftaran (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    foreach ($cols as $c) {
        $stmt->bindValue(':' . $c, $values[$c] ?? null);
    }
    $stmt->execute();
} catch (Exception $e) {
    // Jangan bocorkan detail teknis ke user, tapi tetap kembalikan pesan umum
    fail('Gagal menyimpan data pendaftaran. Silakan coba lagi. (' . $e->getMessage() . ')', 500);
}

// ---------- Kirim email notifikasi ke admin ----------
$subject = 'Pendaftaran Baru SSB PETRA — ' . $data['nama_lengkap'] . ' (' . $kode . ')';
$body = "Ada pendaftaran baru masuk:\n\n"
    . "Kode: $kode\n"
    . "Nama Siswa: {$data['nama_lengkap']}\n"
    . "Jenis Kelamin: {$data['jenis_kelamin']}\n"
    . "Nama Wali: {$data['wali_nama_lengkap']}\n"
    . "HP Wali: {$data['wali_hp']}\n\n"
    . "Lihat detail lengkap & unduh dokumen di halaman admin:\n"
    . "https://" . ($_SERVER['HTTP_HOST'] ?? 'petrafc.com') . "/admin.php\n";

$headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n"
    . "Reply-To: " . MAIL_FROM . "\r\n"
    . "Content-Type: text/plain; charset=utf-8\r\n";

@mail(ADMIN_EMAIL, $subject, $body, $headers);

// ---------- Respon sukses ----------
echo json_encode(['ok' => true, 'kode' => $kode]);
