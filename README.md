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
├── submit.php           ← backend penerima submit form (insert baru / lanjutkan yg sudah ada)
├── ambil-data.php        ← API kecil: ambil ulang data pendaftaran (utk mode "lanjutkan")
├── admin.php               ← panel admin (login, lihat pendaftar, ubah status)
├── inc-cetak-dokumen.php     ← helper & template dokumen bersama (dipakai cetak.php & cetak-publik.php)
├── cetak.php                   ← cetak dokumen versi admin (perlu login), bisa 1 atau banyak (?ids=1,2,3)
├── cetak-publik.php              ← cetak dokumen versi orang tua sendiri, akses via kode_pendaftaran, tanpa login
├── config.sample.php               ← TEMPLATE kredensial — salin jadi config.php DI SERVER, isi nilai asli di sana saja
├── schema.sql                        ← struktur tabel database
├── assets/                             ← logo & thumbnail share medsos
└── uploads/                              ← 1 folder per pendaftar (upload dokumen + tanda tangan) — isi asli TIDAK ada di repo ini
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
Orang tua isi index.html (5 langkah, di HP) - HANYA Nama Lengkap yang wajib
   → submit.php: simpan apa adanya (lengkap atau belum) → simpan file & tanda
        tangan yang ADA ke uploads/{kode}/ → INSERT/UPDATE ke tabel pendaftaran
        → status = "Menunggu Kelengkapan" (belum lengkap) atau "Baru" (lengkap)
        → email admin (hanya saat baru dibuat / baru selesai jadi lengkap)
   → Kalau belum lengkap: layar sukses kasih link "?lanjut=KODE" utk
        melengkapi lagi kapan saja - buka lagi form yg sama, otomatis terisi
        data sebelumnya (ambil-data.php), file yang sudah ada tidak perlu
        diupload ulang.
   → Orang tua juga bisa cetak dokumennya sendiri kapan saja lewat
        cetak-publik.php?kode=KODE (tanpa perlu login admin).
   → admin.php (login): lihat semua pendaftar (termasuk yg blm lengkap),
        unduh dokumen, tulis catatan (mis. "lengkapi X"), ubah status
        → cetak.php: sama seperti cetak-publik.php tapi versi admin,
          bisa banyak pendaftar sekaligus (checkbox "Cetak Terpilih")
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

- **Pendaftaran bisa dilengkapi belakangan**: hanya "Nama Lengkap" siswa yang
  benar-benar wajib diisi utk mengirim form. Field lain (jenis kelamin, data
  wali, upload dokumen, persetujuan, tanda tangan) boleh kosong/salah dulu —
  status pendaftaran otomatis "Menunggu Kelengkapan" sampai semua bagian
  penting terisi, baru berubah jadi "Baru" (siap ditinjau admin). Orang tua
  dapat link unik `index.html?lanjut=KODE` di layar sukses utk buka lagi form
  yg sama, otomatis terisi data sebelumnya, tanpa perlu mengulang dari nol.
  Catatan admin (`catatan_admin`) ikut ditampilkan sbg banner ke orang tua saat
  mereka membuka link ini, jadi bisa dipakai admin utk minta perbaikan data
  spesifik.
- **Cetak mandiri utk orang tua**: `cetak-publik.php?kode=KODE&jenis=...` —
  versi `cetak.php` tanpa perlu login admin, cukup dgn kode pendaftaran milik
  sendiri. Muncul otomatis sbg tombol di layar sukses setelah submit.
  ⚠️ **Model keamanan**: siapa pun yang tahu `kode_pendaftaran` (mis.
  `SP2607-1F27B`) bisa membuka/cetak dokumen tsb tanpa password — sama seperti
  link `?lanjut=KODE`. Kode ini pengacakan 5 karakter hex (~1 juta kombinasi)
  jadi sulit ditebak, tapi jangan disebar sembarangan (mis. jangan pernah post
  kode pendaftaran orang lain di grup publik).
- **Alat bantu foto dokumen ("scan")**: khusus 6 field dokumen (Akte Lahir,
  Ijazah/Raport, Kartu Keluarga, Raport Dalam, Bukti NISN, KIA — tidak
  termasuk Pas Foto), tombol upload dibuka lewat modal panduan dulu (cahaya
  tidak silau, tidak ada bayangan, tegak lurus di atas dokumen, seluruh
  dokumen masuk layar, jangan blur), lalu orang tua bisa: ambil foto langsung,
  pilih dari galeri, atau langsung pakai file JPG/PNG/PDF yang sudah rapi.
  Untuk hasil foto/galeri, muncul layar "Sesuaikan Sudut Dokumen" — geser 4
  titik ke tepi dokumen, sistem otomatis meluruskan (perspective warp 2
  segitiga via `<canvas>`) dan mempertajam kontras/kecerahan/saturasi supaya
  hasilnya mirip hasil scanner berwarna. Murni JavaScript di `index.html`
  (tidak ada perubahan PHP/database) — file akhir tetap dikirim lewat
  `submit.php` seperti biasa, jadi tidak ada langkah tambahan di server
  selain upload ulang `index.html`.
- **Biaya materai (e-meterai)**: baris biaya "Materai Dokumen Resmi (Surat
  Persetujuan Data Pribadi): Rp 15.000" ditambahkan terpisah dari biaya
  pendaftaran Rp2.500.000 (total jadi Rp2.515.000) — tampil di form
  (`index.html` langkah 5) dan di dokumen cetak Formulir Pendaftaran
  (`inc-cetak-dokumen.php`). Angka Rp15.000 hardcode di 2 tempat itu — kalau
  mau diubah, ubah di keduanya supaya konsisten.
  ⚠️ Ini murni tampilan biaya; pembelian & penempelan e-meterai fisik ke
  dokumen PDF final tetap proses **manual admin di luar sistem** (lewat
  PosPay/portal e-meterai resmi) — sebelum jalan produksi, pastikan dulu ke
  CS PosPay/PERURI apakah pola "beli & tempel utk banyak dokumen pihak lain
  sbg bagian layanan berbayar" ini perlu status mitra/distributor resmi.
- **Pelacakan status materai**: kolom baru `materai_status` (`Belum`/`Sudah`)
  per pendaftar. Di `admin.php`, tiap kartu pendaftar sekarang ada pill
  "🖋 Materai: Belum/Sudah" + tombol utk menandai kapan materainya sudah
  dibeli & ditempel admin — berguna krn pendaftar masuk tidak menentu tiap
  hari, jadi admin perlu cara gampang melacak siapa yg masih perlu diurus.

### ⚠️ Wajib dilakukan di server setelah update materai ini

1. **Import ulang `schema.sql` lewat phpMyAdmin** (tab Import) — nambah
   kolom `materai_status`. Aman, tidak menghapus data pendaftar yang sudah ada.
2. Upload ulang `admin.php`, `inc-cetak-dokumen.php`, dan `index.html`
   (ketiganya berubah).
