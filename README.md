# Formulir Pendaftaran Online — SSB & Akademi Petra FC (SSB PETRA Sentani)

PHP + MySQL murni (tanpa framework/Composer), dirancang untuk hosting shared
cPanel/hPanel (Hostinger) dan bisa di-deploy sepenuhnya lewat File Manager,
tanpa akses terminal/SSH.

**Status: sudah live** di `petrafc.com`, dipakai untuk pendaftaran nyata siswa/pemain.

## Struktur

```
daftar/
└── index.php          ← halaman shortlink + share-preview (redirect + Open Graph meta)
pendaftaran/
├── index.html          ← formulir yang diisi calon siswa/ortu (5 langkah)
├── submit.php           ← backend penerima submit form
├── admin.php             ← panel admin (login, lihat pendaftar, ubah status)
├── cetak.php               ← generator 4 dokumen resmi siap cetak (F4)
├── config.sample.php         ← TEMPLATE kredensial — salin jadi config.php DI SERVER, isi nilai asli di sana saja
├── schema.sql                  ← struktur tabel database
├── assets/                       ← logo & thumbnail share medsos
└── uploads/                        ← 1 folder per pendaftar (upload dokumen + tanda tangan) — isi asli TIDAK ada di repo ini
```

Lihat `pendaftaran/README.md` untuk detail langkah pasang di Hostinger.

## ⚠️ Kredensial

`config.php` (kredensial DB, email, login admin) **sengaja tidak ada di repo ini**
(ada di `.gitignore`). Isi kredensial asli hanya boleh ada di server, dibuat dari
`pendaftaran/config.sample.php`. Jangan pernah commit `config.php` yang sudah terisi.

Kalau kredensial pernah ter-share di luar server (chat, dokumen, dsb), sebaiknya
segera diganti (`DB_PASS`, `ADMIN_PASSWORD`).

## Alur data

```
Orang tua isi index.html (5 langkah, di HP)
   → submit.php: validasi → simpan file & tanda tangan ke uploads/{kode}/
        → INSERT ke tabel pendaftaran → email notifikasi ke admin
   → admin.php (login): lihat semua pendaftar, unduh dokumen, ubah status
        → cetak.php: render 4 dokumen resmi siap cetak (F4), data + ttd otomatis
```

## Isu yang diketahui / belum selesai

- **`petrafc.com/daftar` (tanpa trailing slash / `/index.php`) masih 404** —
  kemungkinan besar tertangkap oleh rewrite WordPress di root `.htaccess`
  situs utama. Solusi sementara: pakai `petrafc.com/daftar/index.php` (sudah
  jalan, sudah dipakai untuk share). Perbaikan permanen perlu melihat isi
  root `.htaccess` di `public_html` (di luar repo ini) — backup dulu sebelum ubah.
- **Field alamat siswa terpisah dari alamat orang tua** — dokumen cetak
  (`cetak.php`) saat ini pakai alamat ayah → ibu → wali sbg fallback karena
  form tidak mengumpulkan alamat siswa secara terpisah. Perlu keputusan:
  tambah field baru di `index.html`/`submit.php`/`schema.sql`, atau biarkan.
- **Cetak massal (bulk print)** belum ada — `cetak.php` baru bisa 1 pendaftar per klik.
- **Keamanan admin**: 1 username/password tetap, tanpa rate limiting/log aktivitas.
  Cukup untuk skala kecil.
- **Backup**: aktifkan Backup otomatis Hostinger (hPanel → Backups) untuk `uploads/`.

## Perbaikan yang sudah dilakukan di repo ini

- `admin.php` & `cetak.php`: header `Cache-Control: no-store` eksplisit supaya
  halaman berisi data pribadi tidak pernah ikut ter-cache oleh layer manapun
  (browser/LiteSpeed/CDN) — sebelumnya keluhan "kadang eror/lambat" bisa jadi
  gejala cache atau koneksi DB yang tidak tertangani.
- Query database di `admin.php`/`cetak.php` dibungkus try/catch → kalau koneksi
  MySQL sempat gagal/lambat (umum di shared hosting), tampil pesan yang jelas
  ("coba lagi sesaat lagi") alih-alih halaman blank/fatal error.
- Session admin: `session_regenerate_id()` setelah login berhasil + cookie
  `HttpOnly`/`SameSite=Lax` (pengerasan keamanan session).
