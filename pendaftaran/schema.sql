-- Skema database Form Pendaftaran SSB PETRA SENTANI
-- Import file ini lewat hPanel > Databases > phpMyAdmin > tab "Import"

CREATE TABLE IF NOT EXISTS pendaftaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_pendaftaran VARCHAR(20) NOT NULL UNIQUE,
    tanggal_daftar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Biodata siswa
    nama_lengkap VARCHAR(150) NOT NULL,
    nama_panggilan VARCHAR(100),
    nik_siswa VARCHAR(20),
    nisn VARCHAR(20),
    jenis_kelamin ENUM('Laki-laki','Perempuan') NULL,
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    pendidikan VARCHAR(100),
    nomor_hp_siswa VARCHAR(30),
    tinggi_badan DECIMAL(5,1),
    berat_badan DECIMAL(5,1),
    golongan_darah VARCHAR(5),
    riwayat_operasi_tulang TEXT,
    kaki_dominan ENUM('Kanan','Kiri','Dua-duanya'),
    posisi_bermain VARCHAR(100),

    -- Biodata ayah
    nama_ayah VARCHAR(150),
    alamat_ayah TEXT,
    hp_ayah VARCHAR(30),
    pekerjaan_ayah VARCHAR(100),

    -- Biodata ibu
    nama_ibu VARCHAR(150),
    alamat_ibu TEXT,
    hp_ibu VARCHAR(30),
    pekerjaan_ibu VARCHAR(100),

    -- Data wali yang mengisi & menandatangani (untuk 3 surat pernyataan)
    wali_nama_lengkap VARCHAR(150),
    wali_nik VARCHAR(20),
    wali_tempat_lahir VARCHAR(100),
    wali_tanggal_lahir DATE,
    wali_alamat TEXT,
    wali_hp VARCHAR(30),
    wali_hubungan VARCHAR(50),

    -- Persetujuan (checkbox) - 1 = disetujui
    setuju_data_pribadi TINYINT(1) NOT NULL DEFAULT 0,
    setuju_pernyataan_pemain TINYINT(1) NOT NULL DEFAULT 0,
    setuju_perjanjian_amatir TINYINT(1) NOT NULL DEFAULT 0,

    -- File-file (path relatif di folder uploads/)
    file_akte_lahir VARCHAR(255),
    file_ijazah_raport VARCHAR(255),
    file_kartu_keluarga VARCHAR(255),
    file_raport_dalam VARCHAR(255),
    file_nisn VARCHAR(255),
    file_kia VARCHAR(255),
    file_pas_foto VARCHAR(255),
    file_tanda_tangan VARCHAR(255),
    file_bukti_transfer VARCHAR(255),

    status ENUM('Menunggu Kelengkapan','Baru','Diverifikasi','Diterima','Ditolak') NOT NULL DEFAULT 'Menunggu Kelengkapan',
    catatan_admin TEXT,

    -- Pelacakan e-meterai utk dokumen Persetujuan Data Pribadi (dibeli &
    -- ditempel manual oleh admin per pendaftar, di luar sistem ini)
    materai_status ENUM('Belum','Sudah') NOT NULL DEFAULT 'Belum',

    -- Paket pendaftaran yang dipilih di form (hanya relevan utk jenis_pendaftar = 'Baru')
    paket_pendaftaran ENUM('Lunas','Binaan','Kondisi Ekonomi') NOT NULL DEFAULT 'Lunas',
    nominal_kondisi_ekonomi DECIMAL(12,0) NULL,

    -- Pendaftaran baru vs pemain lama yang melengkapi data digital
    jenis_pendaftar ENUM('Baru','Pemain Lama') NOT NULL DEFAULT 'Baru',
    -- klaim ORANG TUA sendiri soal lunas/belum riwayat bayar lama (bukan status resmi admin)
    klaim_lunas_lama ENUM('Sudah Lunas','Belum Lunas') NULL,

    -- Status pembayaran resmi yg dikontrol admin (Belum Bayar -> Menunggu
    -- Konfirmasi setelah bukti transfer diupload -> Lunas setelah diverifikasi admin)
    status_pembayaran ENUM('Belum Bayar','Menunggu Konfirmasi','Lunas') NOT NULL DEFAULT 'Belum Bayar',

    ip_pendaftar VARCHAR(45)
);

-- Perbarui kolom di instalasi lama yang tabel `pendaftaran`-nya sudah ada
-- sebelum perubahan ini (CREATE TABLE di atas dilewati krn IF NOT EXISTS).
-- Aman dijalankan berulang kali - tidak menghapus/mengubah data yang sudah ada.
ALTER TABLE pendaftaran MODIFY COLUMN jenis_kelamin ENUM('Laki-laki','Perempuan') NULL;
ALTER TABLE pendaftaran MODIFY COLUMN status ENUM('Menunggu Kelengkapan','Baru','Diverifikasi','Diterima','Ditolak') NOT NULL DEFAULT 'Menunggu Kelengkapan';
ALTER TABLE pendaftaran ADD COLUMN IF NOT EXISTS materai_status ENUM('Belum','Sudah') NOT NULL DEFAULT 'Belum';
ALTER TABLE pendaftaran ADD COLUMN IF NOT EXISTS paket_pendaftaran ENUM('Lunas','Binaan','Kondisi Ekonomi') NOT NULL DEFAULT 'Lunas';
ALTER TABLE pendaftaran ADD COLUMN IF NOT EXISTS nominal_kondisi_ekonomi DECIMAL(12,0) NULL;
ALTER TABLE pendaftaran ADD COLUMN IF NOT EXISTS file_bukti_transfer VARCHAR(255) NULL;
ALTER TABLE pendaftaran ADD COLUMN IF NOT EXISTS jenis_pendaftar ENUM('Baru','Pemain Lama') NOT NULL DEFAULT 'Baru';
ALTER TABLE pendaftaran ADD COLUMN IF NOT EXISTS klaim_lunas_lama ENUM('Sudah Lunas','Belum Lunas') NULL;
ALTER TABLE pendaftaran ADD COLUMN IF NOT EXISTS status_pembayaran ENUM('Belum Bayar','Menunggu Konfirmasi','Lunas') NOT NULL DEFAULT 'Belum Bayar';

-- Akun admin (multi-user) untuk login ke admin.php.
-- ADMIN_USERNAME/ADMIN_PASSWORD di config.php tetap berfungsi sbg akun
-- cadangan (bootstrap) untuk membuat akun-akun di tabel ini lewat menu
-- "Kelola Admin" setelah login pertama kali.
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama_tampilan VARCHAR(100) NOT NULL,
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Catatan setiap percobaan login (sukses maupun gagal) - dipakai untuk
-- rate limiting (blokir sementara setelah beberapa kali gagal beruntun).
CREATE TABLE IF NOT EXISTS admin_login_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45),
    berhasil TINYINT(1) NOT NULL
);

-- Catatan aktivitas admin (siapa mengubah status pendaftar yang mana, kapan)
-- supaya admin bisa saling pantau siapa memproses pendaftar yang mana.
CREATE TABLE IF NOT EXISTS admin_aktivitas_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aksi VARCHAR(255) NOT NULL,
    pendaftaran_id INT NULL
);
