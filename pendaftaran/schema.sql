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
    jenis_kelamin ENUM('Laki-laki','Perempuan') NOT NULL,
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

    status ENUM('Baru','Diverifikasi','Diterima','Ditolak') NOT NULL DEFAULT 'Baru',
    catatan_admin TEXT,

    ip_pendaftar VARCHAR(45)
);
