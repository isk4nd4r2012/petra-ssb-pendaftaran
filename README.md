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

- ~~`petrafc.com/daftar` (tanpa trailing slash / `/index.php`) 404~~ —
  **sudah dicek ulang (2026-07) dan TIDAK terjadi lagi**: root `public_html`
  ternyata tidak punya `.htaccess` sama sekali (situs ini bukan WordPress),
  jadi dugaan lama soal rewrite WordPress tidak relevan. Akses langsung ke
  `petrafc.com/daftar` sekarang otomatis masuk ke formulir pendaftaran.
  Kalau suatu saat 404 muncul lagi, kemungkinan penyebabnya bukan `.htaccess`
  tapi konfigurasi lain di sisi Hostinger (perlu ditelusuri ulang).
- **Field alamat siswa terpisah dari alamat orang tua** — dokumen cetak
  (`cetak.php`) saat ini pakai alamat ayah → ibu → wali sbg fallback karena
  form tidak mengumpulkan alamat siswa secara terpisah. Perlu keputusan:
  tambah field baru di `index.html`/`submit.php`/`schema.sql`, atau biarkan.
- ~~**Cetak massal (bulk print)** belum ada~~ — **sudah ditambahkan**, lihat di bawah.
- ~~**Keamanan admin**: 1 username/password tetap, tanpa rate limiting/log aktivitas~~ —
  **sudah ditambahkan** (multi-akun, rate limiting, log aktivitas), lihat di bawah.
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
- **Multi-akun admin**: tabel baru `admin_users` — tiap staff bisa punya
  username/password sendiri (password di-hash, bukan plain text), dikelola
  lewat menu "⚙️ Kelola Akun Admin" di `admin.php` setelah login. Akun
  `ADMIN_USERNAME`/`ADMIN_PASSWORD` di `config.php` tetap berfungsi sebagai
  akun bootstrap/cadangan untuk membuat akun-akun staff pertama kali.
- **Rate limiting login**: setelah 5x gagal login beruntun dari IP yang sama
  dalam 15 menit, percobaan login diblokir sementara (tabel `admin_login_log`).
- **Log aktivitas**: setiap perubahan status pendaftar dicatat (siapa, kapan,
  ke status apa) di tabel `admin_aktivitas_log`, ditampilkan di menu
  "🕒 Log Aktivitas & Login" — supaya admin bisa saling pantau.
- **Cetak massal**: di `admin.php` sekarang ada checkbox di tiap pendaftar +
  tombol "🖨️ Cetak Terpilih" untuk membuka satu halaman cetak berisi dokumen
  semua pendaftar yang dicentang sekaligus (`cetak.php?ids=1,2,3`), siap
  "Simpan sebagai PDF" satu kali untuk semua. Link cetak satu-per-satu yang
  lama (`cetak.php?id=...`) tetap berfungsi seperti biasa.

### ⚠️ Wajib dilakukan di server setelah update ini

`schema.sql` bertambah 3 tabel baru (`admin_users`, `admin_login_log`,
`admin_aktivitas_log`). **Import ulang `schema.sql` lewat phpMyAdmin**
(tab Import, `CREATE TABLE IF NOT EXISTS` — aman, tidak akan menghapus data
pendaftar yang sudah ada) supaya fitur multi-admin, rate limiting, dan log
aktivitas aktif. Selama tabel-tabel ini belum diimport, login lama
(`ADMIN_USERNAME`/`ADMIN_PASSWORD` di `config.php`) tetap berfungsi seperti
biasa — hanya menu "Kelola Akun Admin"/"Log Aktivitas" yang akan menampilkan
pesan bahwa tabelnya belum tersedia.
