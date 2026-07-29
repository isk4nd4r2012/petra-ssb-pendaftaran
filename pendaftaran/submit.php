<?php
/**
 * submit.php — menerima data dari index.html, menyimpan file upload,
 * menyimpan/memperbarui data di database MySQL, dan mengirim email
 * notifikasi ke admin.
 *
 * Hanya "nama_lengkap" yang wajib. Field lain (termasuk upload dokumen,
 * tanda tangan, dan 3 persetujuan) boleh belum lengkap - pendaftaran akan
 * disimpan dengan status "Menunggu Kelengkapan" dan bisa dilanjutkan lagi
 * kapan saja lewat link ?lanjut=KODE (kirim kode_pendaftaran yang sama di
 * form supaya baris yang sudah ada di-UPDATE, bukan bikin baris baru).
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
    'jenis_kelamin'            => s('jenis_kelamin') ?: null,
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

    'setuju_data_pribadi'      => !empty($_POST['setuju_data_pribadi']) ? 1 : 0,
    'setuju_pernyataan_pemain' => !empty($_POST['setuju_pernyataan_pemain']) ? 1 : 0,
    'setuju_perjanjian_amatir' => !empty($_POST['setuju_perjanjian_amatir']) ? 1 : 0,

    'paket_pendaftaran'         => in_array(s('paket_pendaftaran'), ['Lunas', 'Cicilan', 'Kondisi Ekonomi'], true) ? s('paket_pendaftaran') : 'Lunas',

    'jenis_pendaftar'           => (s('jenis_pendaftar') === 'Pemain Lama') ? 'Pemain Lama' : 'Baru',
];
$data['nominal_kondisi_ekonomi'] = ($data['paket_pendaftaran'] === 'Kondisi Ekonomi' && is_numeric($_POST['nominal_kondisi_ekonomi'] ?? null))
    ? (int) $_POST['nominal_kondisi_ekonomi']
    : null;
$data['klaim_lunas_lama'] = ($data['jenis_pendaftar'] === 'Pemain Lama' && in_array(s('klaim_lunas_lama'), ['Sudah Lunas', 'Belum Lunas'], true))
    ? s('klaim_lunas_lama')
    : null;

// ---------- Satu-satunya field yang benar-benar wajib ----------
if (empty($data['nama_lengkap'])) {
    fail('Nama lengkap wajib diisi.');
}

// ---------- Cek apakah ini lanjutan dari pendaftaran yang sudah ada ----------
$kodeInput = trim($_POST['kode_pendaftaran'] ?? '');
$existing = null;
if ($kodeInput !== '') {
    try {
        $pdo0 = get_db();
        $stmt0 = $pdo0->prepare('SELECT * FROM pendaftaran WHERE kode_pendaftaran = :k');
        $stmt0->execute(['k' => $kodeInput]);
        $existing = $stmt0->fetch();
    } catch (Exception $e) {
        fail('Gagal memeriksa data pendaftaran sebelumnya. Coba lagi.', 500);
    }
}

$kode = $existing ? $existing['kode_pendaftaran'] : ('SP' . date('ym') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)));

// ---------- Siapkan folder upload untuk pendaftaran ini ----------
$folder = UPLOAD_DIR . '/' . $kode;
if (!is_dir($folder)) {
    if (!mkdir($folder, 0755, true)) fail('Gagal menyiapkan folder upload di server.', 500);
}

// ---------- Simpan file upload (kalau ada file baru) ----------
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
    'file_raport_dalam', 'file_nisn', 'file_kia', 'file_pas_foto', 'file_bukti_transfer',
];
$savedFiles = [];
$buktiTransferBaru = false;
foreach ($fileFields as $f) {
    $new = save_upload($f, $folder, $kode);
    if ($new !== null) {
        $savedFiles[$f] = $new; // file baru diupload sekarang
        if ($f === 'file_bukti_transfer') $buktiTransferBaru = true;
    } elseif ($existing && !empty($existing[$f])) {
        $savedFiles[$f] = $existing[$f]; // pertahankan file lama, jangan dihapus krn tidak diupload ulang
    } else {
        $savedFiles[$f] = null;
    }
}

// ---------- Simpan tanda tangan (base64 PNG), kalau ada yang baru ----------
$hasSignature = false;
if (!empty($_POST['tanda_tangan_dataurl'])) {
    if (preg_match('/^data:image\/png;base64,(.+)$/', $_POST['tanda_tangan_dataurl'], $m)) {
        $sigBin = base64_decode($m[1]);
        file_put_contents($folder . '/tanda_tangan.png', $sigBin);
        $savedFiles['file_tanda_tangan'] = $kode . '/tanda_tangan.png';
        $hasSignature = true;
    } else {
        fail('Format tanda tangan tidak valid.');
    }
} elseif ($existing && !empty($existing['file_tanda_tangan'])) {
    $savedFiles['file_tanda_tangan'] = $existing['file_tanda_tangan'];
    $hasSignature = true;
}

// ---------- Tentukan kelengkapan & status ----------
function hitung_lengkap($data, $savedFiles, $hasSignature) {
    if (empty($data['jenis_kelamin'])) return false;
    if (empty($data['wali_nama_lengkap'])) return false;
    if (empty($data['wali_hp'])) return false;
    if (empty($savedFiles['file_akte_lahir'])) return false;
    if (empty($savedFiles['file_kartu_keluarga'])) return false;
    if (!$hasSignature) return false;
    if (empty($data['setuju_data_pribadi']) || empty($data['setuju_pernyataan_pemain']) || empty($data['setuju_perjanjian_amatir'])) return false;
    return true;
}
$lengkap = hitung_lengkap($data, $savedFiles, $hasSignature);

if ($existing) {
    $statusBaru = $existing['status'];
    if ($statusBaru === 'Menunggu Kelengkapan' && $lengkap) {
        $statusBaru = 'Baru'; // baru saja jadi lengkap - siap ditinjau admin
    }
    // Kalau admin sudah memproses lebih lanjut (Diverifikasi/Diterima/Ditolak),
    // status itu tidak diturunkan otomatis hanya krn ada pembaruan data.
} else {
    $statusBaru = $lengkap ? 'Baru' : 'Menunggu Kelengkapan';
}

// ---------- Status pembayaran: naik ke "Menunggu Konfirmasi" begitu bukti
// transfer diupload, tapi jangan turunkan status "Lunas" yang sudah
// dikonfirmasi admin sebelumnya hanya krn ada pembaruan data lain. ----------
$statusBayarBaru = $existing['status_pembayaran'] ?? 'Belum Bayar';
if ($buktiTransferBaru && $statusBayarBaru !== 'Lunas') {
    $statusBayarBaru = 'Menunggu Konfirmasi';
}

// ---------- Simpan ke database (insert baru / update lanjutan) ----------
try {
    $pdo = get_db();

    if ($existing) {
        $cols = array_unique(array_merge(array_keys($data), array_keys($savedFiles)));
        $setParts = array_map(fn($c) => "$c = :$c", $cols);
        $setParts[] = 'status = :status_baru';
        $setParts[] = 'status_pembayaran = :status_bayar_baru';
        $sql = 'UPDATE pendaftaran SET ' . implode(', ', $setParts) . ' WHERE kode_pendaftaran = :kode_where';
        $stmt = $pdo->prepare($sql);
        foreach ($cols as $c) {
            $stmt->bindValue(':' . $c, array_key_exists($c, $data) ? $data[$c] : ($savedFiles[$c] ?? null));
        }
        $stmt->bindValue(':status_baru', $statusBaru);
        $stmt->bindValue(':status_bayar_baru', $statusBayarBaru);
        $stmt->bindValue(':kode_where', $kode);
        $stmt->execute();
    } else {
        $cols = array_merge(
            ['kode_pendaftaran', 'status', 'status_pembayaran'],
            array_keys($data),
            array_keys($savedFiles),
            ['ip_pendaftar']
        );
        $cols = array_unique($cols);

        $values = array_merge(
            ['kode_pendaftaran' => $kode, 'status' => $statusBaru, 'status_pembayaran' => $statusBayarBaru],
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
    }
} catch (Exception $e) {
    // Jangan bocorkan detail teknis ke user, tapi tetap kembalikan pesan umum
    fail('Gagal menyimpan data pendaftaran. Silakan coba lagi. (' . $e->getMessage() . ')', 500);
}

// ---------- Kirim email notifikasi ke admin ----------
// Hanya saat pendaftaran baru dibuat, atau saat baru saja menjadi lengkap -
// supaya admin tidak dibanjiri email tiap kali ada penyuntingan kecil.
$shouldEmail = !$existing || ($existing['status'] === 'Menunggu Kelengkapan' && $statusBaru === 'Baru');
if ($shouldEmail) {
    $jenisLabel = $data['jenis_pendaftar'] === 'Pemain Lama' ? 'Lengkapi Data Pemain Lama' : 'Pendaftaran Baru';
    $subject = "$jenisLabel (" . ($lengkap ? 'Lengkap' : 'Belum Lengkap') . ')'
        . ' SSB PETRA — ' . $data['nama_lengkap'] . ' (' . $kode . ')';
    $body = "Ada data masuk ($jenisLabel):\n\n"
        . "Kode: $kode\n"
        . "Status: $statusBaru\n"
        . "Nama Siswa: {$data['nama_lengkap']}\n"
        . "Jenis Kelamin: " . ($data['jenis_kelamin'] ?: '-') . "\n"
        . "Nama Wali: " . ($data['wali_nama_lengkap'] ?: '-') . "\n"
        . "HP Wali: " . ($data['wali_hp'] ?: '-') . "\n\n"
        . "Lihat detail lengkap & unduh dokumen di halaman admin:\n"
        . "https://" . ($_SERVER['HTTP_HOST'] ?? 'petrafc.com') . "/admin.php\n";

    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n"
        . "Reply-To: " . MAIL_FROM . "\r\n"
        . "Content-Type: text/plain; charset=utf-8\r\n";

    @mail(ADMIN_EMAIL, $subject, $body, $headers);
}

// ---------- Respon sukses ----------
echo json_encode(['ok' => true, 'kode' => $kode, 'lengkap' => $lengkap, 'status' => $statusBaru]);
