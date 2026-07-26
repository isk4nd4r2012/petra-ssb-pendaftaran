# Panduan: Modul Pendaftaran Online SSB/Akademi (dari Proyek Petra FC)

> Dokumen ini merangkum arsitektur, mekanisme, dan keputusan desain dari sistem
> pendaftaran online SSB PETRA Sentani / Akademi Petra FC (repo
> `isk4nd4r2012/petra-ssb-pendaftaran`), supaya bisa dijadikan referensi saat
> membangun modul database SSB di **Petrax MatchStat**. Tujuannya: SSB/akademi
> klien MatchStat mendapat form pendaftaran online yang rapi, transparan, dan
> siap ditindaklanjuti ke federasi (PSSI) — ini bisa jadi nilai jual yang kuat
> karena kebanyakan SSB di Indonesia masih pakai formulir kertas/WhatsApp.

---

## 1. Kenapa ini nilai jual

Masalah nyata yang dialami SSB/akademi kecil di Indonesia:
- Pendaftaran manual (kertas/WA) → data tercecer, susah direkap, gampang hilang.
- Orang tua sering tidak punya semua dokumen lengkap saat pertama daftar
  (akte, KK, dsb menyusul).
- Verifikasi pembayaran manual → admin harus cocokkan mutasi bank sendiri.
- Saat mau daftar ke **SIAP PSSI** (aplikasi resmi federasi), data yang
  berantakan bikin proses jadi lambat dan rawan salah input.

Modul ini menjawab semua itu dengan satu form berbasis link, tanpa perlu akun
orang tua, yang datanya rapi dan siap dipakai admin maupun untuk dilanjutkan
ke SIAP PSSI.

---

## 2. Prinsip desain inti

1. **Cuma nama lengkap yang wajib di awal.** Semua field lain boleh menyusul.
   Ini penting karena target penggunanya (orang tua pemain SSB daerah) sering
   tidak siap dokumen lengkap saat pertama kali membuka form.
2. **Akses tanpa akun, pakai "kode" sebagai token.** Orang tua tidak perlu
   daftar akun/password. Satu `kode_pendaftaran` unik (format `SPYYMM-XXXXX`)
   berfungsi sebagai kunci untuk: melanjutkan draft (`?lanjut=KODE`) dan
   mencetak dokumen sendiri (`cetak-publik.php?kode=KODE`). Ini trade-off
   keamanan yang sadar diambil: kemudahan akses vs kerahasiaan kode (kode
   harus diperlakukan seperti password ringan, jangan dibagikan ke orang lain).
3. **Admin harus bisa memantau semuanya dari satu tempat**, termasuk siapa
   yang datanya belum lengkap, siapa yang belum bayar, dan siapa admin yang
   melakukan aksi apa (activity log) — penting begitu ada lebih dari 1 admin.
4. **Proses foto dokumen dari HP harus dituntun, bukan dibiarkan asal jepret.**
   Kualitas foto dokumen (akte, KK, dst) sangat menentukan apakah admin bisa
   memverifikasi data dengan cepat.
5. **Jangan coba otomatisasi ke sistem resmi (SIAP PSSI) yang tidak
   menyediakan API/bulk-import.** Lebih aman menyediakan data yang sudah rapi
   dan cocok formatnya untuk **disalin manual**, daripada membangun bot/scraper
   yang berisiko merusak data di database resmi federasi atau melanggar ToS.

---

## 3. Arsitektur teknis (ringkas)

- PHP polos (tanpa framework/Composer) + MySQL via PDO.
- Konfigurasi kredensial dipisah: `config.php` (real, **tidak** masuk git) vs
  `config.sample.php` (template placeholder, di-commit). `config.php` berisi
  fungsi `get_db()` sebagai PDO singleton (`PDO::ATTR_TIMEOUT` diset supaya
  tidak menggantung kalau DB server sibuk).
- Tidak ada build step — cocok untuk hosting shared (upload file langsung,
  tanpa SSH/terminal).
- Struktur file per fitur:
  - `index.html` — form publik multi-step (client-side JS berat, canvas-based).
  - `submit.php` — terima POST, insert/update ke DB, kirim email notifikasi.
  - `ambil-data.php` — API untuk isi ulang draft yang belum selesai.
  - `admin.php` — dashboard admin (login, daftar pendaftar, aksi per baris).
  - `inc-cetak-dokumen.php` — helper cetak dipakai bareng oleh 2 halaman cetak.
  - `cetak.php` (butuh login admin) & `cetak-publik.php` (pakai kode, publik).
  - `export-csv.php` — export data untuk keperluan SIAP PSSI.
  - `schema.sql` — DDL idempoten (`ADD COLUMN IF NOT EXISTS` dsb), aman
    dijalankan ulang tiap kali ada perubahan skema.

**Rekomendasi untuk MatchStat:** kalau stack MatchStat berbeda (mis. sudah
pakai framework/API terpusat), prinsip di atas tetap berlaku — hanya
implementasinya yang beda. Yang penting dipertahankan: pemisahan kredensial,
token akses tanpa akun untuk orang tua, dan status lifecycle yang jelas
(lihat bagian 5).

---

## 4. Skema data inti

Tabel utama `pendaftaran`, kolom-kolom pentingnya per kategori:

| Kategori | Kolom | Catatan |
|---|---|---|
| Identitas | `nama_lengkap` (wajib), `nama_panggilan`, `tanggal_lahir`, `tempat_lahir`, `jenis_kelamin` (nullable), `nik_siswa` | |
| Wali | `wali_nama_lengkap`, `wali_hp`, `wali_alamat`, `alamat_ayah`, `alamat_ibu` | Alamat pemain fallback ayah→ibu→wali (lihat §9, ini masih jadi utang teknis) |
| Lifecycle | `status` ENUM(`Menunggu Kelengkapan`, `Baru`, ...) | Auto-upgrade begitu field wajib kelengkapan terpenuhi |
| Jenis pendaftar | `jenis_pendaftar` ENUM(`Baru`,`Pemain Lama`) | Lihat §6.5 |
| Paket & biaya | `paket_pendaftaran` ENUM(`Lunas`,`Binaan`,`Kondisi Ekonomi`), `nominal_kondisi_ekonomi`, `materai_status` | Lihat §6.4 |
| Pembayaran | `status_pembayaran` ENUM(`Belum Bayar`,`Menunggu Konfirmasi`,`Lunas`), `file_bukti_transfer`, `klaim_lunas_lama` | Lihat §6.6 |
| Dokumen | `file_akte_lahir`, `file_kartu_keluarga`, `file_ijazah_raport`, `file_raport_dalam`, `file_nisn`, `file_kia`, `file_pas_foto` | Path file di disk, per registrant folder `uploads/{kode}/` |
| Kode akses | `kode_pendaftaran` (unik) | Token bearer-style, lihat §2 poin 2 |

Tabel pendukung: `admin_users` (multi-admin), `admin_login_log` (rate
limiting login), `admin_aktivitas_log` (audit trail aksi admin).

**Rekomendasi:** rancang `schema.sql` sebagai skrip idempoten sejak awal
(pakai `ADD COLUMN IF NOT EXISTS`). Ini membuat setiap iterasi fitur baru
gampang di-deploy ulang tanpa takut merusak data yang sudah ada — sangat
penting kalau tim MatchStat juga akan deploy ke banyak instance SSB berbeda.

---

## 5. Alur pendaftaran end-to-end

```
Orang tua buka link form
   │
   ├─ Isi minimal "Nama Lengkap" → submit
   │     → dapat kode_pendaftaran unik
   │     → status = "Menunggu Kelengkapan" (kalau belum lengkap)
   │
   ├─ Bisa lanjut isi sisanya langsung, ATAU
   │     tutup browser & lanjut nanti via link "?lanjut=KODE"
   │     (ambil-data.php mengembalikan data tersimpan + flag file mana yg sudah ada)
   │
   ├─ Upload dokumen dituntun via "guided photo scan" (lihat §6.2)
   ├─ Isi paket pendaftaran / status pembayaran lama (lihat §6.4 / §6.5)
   ├─ Tanda tangan digital: gambar di layar ATAU upload foto ttd (§6.3)
   ├─ Submit final → hitung_lengkap() cek field² wajib kelengkapan
   │     → kalau lengkap: status naik jadi "Baru", email notifikasi terkirim
   │
   ├─ Layar sukses: tombol cetak sendiri (kode-based), tombol WA konfirmasi
   │     transfer (prefilled kode+jumlah), link simpan untuk lanjut nanti
   │
Admin (admin.php)
   ├─ Pantau semua pendaftar: status kelengkapan, status pembayaran, status materai
   ├─ Update status_pembayaran, materai_status manual
   ├─ Cetak 1 atau banyak (bulk, checkbox) sekaligus
   ├─ Hapus pendaftar (dengan konfirmasi + log aktivitas) untuk data uji coba
   └─ Export CSV terformat siap-tempel ke SIAP PSSI
```

---

## 6. Fitur-fitur kunci & mekanismenya

### 6.1 Form resumable (bisa dilengkapi belakangan)
- Validasi wajib **hanya di step 1** (client-side JS `validateStep(1)`),
  server (`submit.php`) juga cuma mewajibkan `nama_lengkap`.
- `submit.php` menerima `kode_pendaftaran` opsional di POST: kalau ada →
  cabang UPDATE (dan file yang sudah pernah diupload dipertahankan bila tidak
  ada file baru dikirim); kalau tidak ada → cabang INSERT + generate kode baru.
- `hitung_lengkap()` — fungsi tunggal yang mengecek daftar field wajib
  "kelengkapan" (jenis kelamin, data wali, file akte, KK, tanda tangan, 3
  checkbox persetujuan). Dipakai untuk transisi status otomatis
  `Menunggu Kelengkapan` → `Baru`.
- Link lanjut (`?lanjut=KODE`) memanggil `ambil-data.php`, yang mengembalikan
  semua kolom DB **kecuali** path file mentah — untuk field file, dikirim flag
  boolean `sudah_ada` saja (supaya path server tidak bocor ke client, dan form
  tahu harus menampilkan "sudah ada file, ganti?" bukan input kosong).

### 6.2 Guided photo scan (untuk semua upload dokumen)
Alih-alih `<input type=file>` polos, tiap upload dokumen membuka modal 2 tahap:
1. **Tahap panduan**: checklist visual — tidak ada bayangan, tidak blur, tidak
   silau/glare, seluruh dokumen masuk frame. Tombol: kamera / galeri / "sudah
   punya file rapi" (skip proses scan kalau file sudah difoto/discan dengan baik).
2. **Tahap koreksi manual**: pengguna menggeser 4 titik sudut dokumen di atas
   foto (drag pakai Pointer Events) untuk menandai batas dokumen yang miring.

Setelah 4 sudut ditentukan, diproses **100% di browser (client-side canvas,
tanpa library)**:
- Perspective correction: quad dibagi jadi 2 segitiga, tiap segitiga dihitung
  transformasi affine 2D dari 3 pasang titik korespondensi (rumus closed-form),
  digambar ulang ke kanvas baru berbentuk persegi rapi.
- Lalu pass kedua menerapkan `ctx.filter = 'contrast(...) brightness(...)
  saturate(...)'` supaya hasilnya terlihat seperti hasil scanner berwarna
  (bukan foto HP biasa).
- Hasil akhir (blob/file) disuntik ke `<input type=file>` asli lewat
  `DataTransfer` API + event `change` manual, supaya kode upload existing
  tidak perlu diubah.

**Kenapa penting buat MatchStat:** ini yang bikin dokumen dari orang tua yang
awam foto tetap terbaca rapi oleh admin — mengurangi bolak-balik "foto ulang
dong, buram/miring".

### 6.3 Tanda tangan digital dual-mode
- Mode 1: gambar langsung di `<canvas>` pakai jari/mouse.
- Mode 2: upload/foto tanda tangan yang sudah ada di kertas (pakai ulang modal
  scan yang sama, ditandai lewat sentinel `targetField === '__signature__'`).
- Keduanya menghasilkan `data:image/png;base64,...` yang dikirim di field yang
  sama (`tanda_tangan_dataurl`) — jadi backend tidak perlu tahu mode mana yang
  dipakai.
- **Bug produksi yang pernah terjadi (penting jadi pelajaran):** listener
  `resize` window men-resize kanvas tanda tangan meski sedang disembunyikan
  (`display:none` karena bukan step aktif) → `getBoundingClientRect()`
  mengembalikan 0×0 → kanvas ke-reset tapi flag "sudah menggambar" tidak ikut
  ke-reset → hasil `toDataURL()` jadi PNG kosong/korup yang lolos ke client
  tapi ditolak validasi server. **Pelajaran:** kalau ada elemen canvas yang
  bisa disembunyikan (multi-step form), semua operasi resize/render harus
  dijaga skip total saat elemen tidak terlihat (rect 0×0), dan render ulang
  konten sebelumnya setelah resize valid — jangan asumsikan elemen selalu visible.

### 6.4 Paket pendaftaran + biaya materai
Tiga pilihan paket dengan harga & manfaat berbeda (nominal sengaja tidak
dihardcode di dokumen ini karena spesifik ke Petra FC — yang penting polanya):
- Paket "lunas" penuh dengan seragam+bola.
- Paket "binaan" — biaya lebih rendah, manfaat lebih sedikit, status pemain
  tetap terdaftar sebagai siswa akademi.
- Paket "sesuai kondisi ekonomi" — orang tua mengetik sendiri nominal yang
  sanggup dibayar (form sosial/subsidi silang).
- Di atas semua paket, ditambahkan **biaya materai** (biaya meterai digital
  + markup kecil untuk operasional admin) sebagai baris terpisah di rincian
  biaya.
- Perhitungan biaya di-duplikasi di 2 tempat secara sengaja: JS di form
  (`updateBiayaBox()`, live update saat user pilih paket) dan PHP di sisi
  cetak (`render_biaya_box($r)` di `inc-cetak-dokumen.php`) — supaya nominal
  yang tercetak di dokumen resmi selalu dihitung ulang dari data DB, bukan
  dipercaya begitu saja dari input form (anti-manipulasi harga oleh user).

### 6.5 Mode "Lengkapi Data (Pemain Lama)"
Untuk pemain yang sudah lama terdaftar secara fisik (fotokopi) tapi belum
terdigitalisasi:
- Link/form yang **sama persis**, dibedakan lewat toggle di UI + parameter
  URL `?mode=lama`.
- Saat mode ini aktif: kartu pemilihan paket diganti dengan kartu "Status
  Pembayaran Sebelumnya" (klaim sendiri oleh orang tua: Sudah Lunas / Belum
  Lunas — **bukan** status otentik, admin tetap perlu verifikasi manual).
- Seluruh tema warna form berubah (lihat §6.7) supaya admin/petugas lapangan
  langsung tahu secara visual sedang menangani form mode yang mana.
- Rangkaian field/validasi/DB sama sekali tidak diduplikasi — hanya dicabangkan
  di titik-titik tertentu (`jenis_pendaftar` sebagai penentu cabang), sehingga
  1 form bisa melayani 2 use case tanpa 2 codebase terpisah.

### 6.6 Konfirmasi pembayaran
- Rekening resmi klub ditampilkan langsung di form (nama pemilik + bank +
  nomor rekening).
- Orang tua bisa upload bukti transfer opsional.
- Tombol WhatsApp click-to-chat (`https://wa.me/<nomor>?text=...`) otomatis
  terisi kode pendaftaran + nominal, supaya admin tinggal cocokkan.
- `status_pembayaran` adalah status **otentik** yang hanya admin yang bisa
  ubah dari dashboard (beda dengan `klaim_lunas_lama` di §6.5 yang cuma klaim
  sepihak orang tua) — begitu ada file bukti transfer baru diupload, status
  otomatis naik ke "Menunggu Konfirmasi" (bukan langsung "Lunas") sampai admin
  yang mengonfirmasi manual.

### 6.7 Tema warna per mode (UX kecil tapi penting)
CSS custom properties (`--pitch-900`, `--cone-500`, dst) dipakai di seluruh
stylesheet; cukup 1 class (`body.mode-lama`) yang meng-override variabel-variabel
itu untuk mengubah seluruh nuansa warna halaman tanpa menyentuh rule tiap
komponen satu-satu. Pola ini murah untuk direplikasi kalau MatchStat perlu
banyak "mode" visual berbeda di form yang sama.

---

## 7. Admin panel — mekanisme monitoring

- **Multi-admin**: tabel `admin_users` terpisah dari kredensial "bootstrap"
  legacy di `config.php` (fallback tetap didukung untuk kompatibilitas).
- **Rate limiting login**: `admin_login_log` mencatat setiap percobaan;
  `is_rate_limited()` mengunci sementara setelah percobaan gagal beruntun.
- **Activity log** (`admin_aktivitas_log`): setiap aksi sensitif (ubah status
  pembayaran, ubah status materai, hapus pendaftar) dicatat — siapa, kapan,
  aksi apa. Wajib begitu ada >1 admin, supaya bisa saling audit.
- **Bulk print**: checkbox per baris + query param `?ids=1,2,3` (dibatasi
  maks. 50) di halaman cetak admin.
- **Hapus data (permanen)**: link aksi dengan `confirm()` JS, menghapus baris
  DB + folder upload terkait di disk + mencatat ke activity log — dibuat
  sebagai fitur resmi (bukan lewat phpMyAdmin manual) supaya ada jejak audit
  dan tidak berisiko salah hapus data produksi.

---

## 8. Jembatan ke SIAP PSSI (federasi resmi)

**Temuan penting:** SIAP PSSI (form "Single Registration Player") **tidak
punya fitur import CSV/bulk** — hanya input satu-per-satu ("Tambah"). Karena
itu:
- **Tidak disarankan** membangun bot/automation browser untuk mengisi SIAP
  otomatis — risikonya dua arah: (a) ToS otomatisasi di sistem federasi resmi
  tidak jelas/berisiko, (b) kesalahan pemetaan field bisa mengotori database
  resmi federasi yang dipakai banyak pihak.
- **Solusi yang diimplementasikan:** fitur `export-csv.php` di admin panel,
  yang urutan dan label kolomnya **dibuat identik dengan urutan tab form SIAP**
  (`[Pemain]`, `[Klub Sebelumnya]`, `[Klub Baru]`, plus `[Internal]` untuk
  referensi kami sendiri). Admin tinggal buka CSV ini berdampingan dengan
  form SIAP dan copy-paste tiap kolom ke field yang sesuai — jauh lebih cepat
  dan lebih aman daripada bolak-balik cek data mentah di database.
- Field yang tidak dikumpulkan form kami (Provinsi, Kota, Email pemain, dsb)
  sengaja dikosongkan di CSV supaya admin sadar harus isi manual, bukan
  ditebak otomatis.
- Field yang secara logis selalu sama untuk pemain baru amatir domestik
  (mis. Kewarganegaraan = Indonesia, Status Pemain = Amatir) di-hardcode di
  CSV supaya admin tidak perlu isi ulang tiap baris.

### Jebakan format yang harus diwaspadai: notasi ilmiah
NIK dan nomor HP adalah string angka panjang (16 digit). Excel/Google Sheets
**otomatis mendeteksi kolom berisi angka panjang sebagai numerik** dan
membulatkannya jadi notasi ilmiah (`9.10302E+13`), merusak data. Solusinya
adalah trik lama Excel: bungkus nilai sebagai formula string —
```
="91030161012006"
```
`fputcsv()` menuliskan `"=""91030161012006"""` (tanda kutip di-escape ganda),
dan Excel/Sheets akan mengevaluasinya sebagai formula yang menghasilkan teks
utuh, bukan angka. **Ini wajib diterapkan ke setiap kolom CSV berisi ID/nomor
HP/nomor rekening apa pun** yang akan dibuka di spreadsheet — kalau MatchStat
membuat fitur export serupa, terapkan pola yang sama dari awal supaya tidak
perlu ada siklus bug-lapor-fix seperti di proyek ini.

---

## 9. Keamanan & kebersihan operasional

- Kredensial **tidak pernah** dikomit ke git — `config.php` di `.gitignore`,
  `config.sample.php` sebagai template placeholder.
- Password admin di-hash (`password_hash`/`password_verify`), sesi diperkuat
  (`session_regenerate_id`, cookie `HttpOnly` + `SameSite=Lax`).
- Header `Cache-Control: no-store` pada semua halaman yang menampilkan data
  pribadi (supaya tidak ke-cache di browser publik/proxy).
- Setiap fitur baru yang mengubah skema DB ditulis sebagai migrasi idempoten
  di `schema.sql`, dan didokumentasikan di README sebagai "wajib dilakukan di
  server" — penting karena deploy di lingkungan ini manual (copy-paste via
  file manager hosting, tidak ada CI/CD).

---

## 10. Rekomendasi urutan pembangunan untuk MatchStat

Kalau membangun modul SSB serupa dari nol, urutan yang terbukti efektif di
proyek ini:

1. **Skema data + status lifecycle dulu** (`Menunggu Kelengkapan` → `Baru` →
   dst) sebelum UI — supaya semua fitur berikutnya (draft, resume, admin
   monitoring) punya fondasi yang jelas.
2. **Form inti resumable** (1 field wajib, sisanya opsional + kode akses).
3. **Admin dashboard minimal** (lihat semua data, login aman) — supaya tim
   bisa mulai pakai sistem walau fitur lanjutan belum selesai.
4. **Upload dokumen + guided scan** — investasi UX yang paling terasa
   dampaknya ke kualitas data.
5. **Pembayaran & paket** — baru setelah alur data dasar stabil.
6. **Export/bridge ke SIAP PSSI** — paling akhir, karena bergantung pada
   field-field yang sudah lengkap dari langkah-langkah sebelumnya.

Di setiap langkah, prioritaskan **transparansi ke klien SSB**: status yang
jelas terlihat orang tua (progress pendaftaran), status yang jelas terlihat
admin (siapa perlu ditindaklanjuti), dan jejak audit (siapa admin mengubah
apa) — ini yang membedakan produk ini dari sekadar "Google Form" biasa dan
yang bisa dijual sebagai nilai tambah MatchStat ke SSB/akademi.

---

## 11. Keterbatasan yang masih terbuka (jujur disampaikan)

- Belum ada field "Alamat Siswa" terpisah dari alamat wali — saat ini alamat
  pemain fallback ke alamat ayah → ibu → wali. Ini utang teknis yang belum
  dikerjakan di proyek Petra FC dan sebaiknya dirancang dengan benar sejak
  awal di MatchStat (field alamat sendiri untuk pemain).
- `kode_pendaftaran` sebagai token akses adalah kompromi keamanan vs
  kemudahan (5 karakter hex acak, ±1 juta kombinasi) — cukup untuk skala SSB
  kecil-menengah, tapi perlu dipertimbangkan ulang (mis. token lebih panjang,
  atau kadaluarsa otomatis) kalau skala MatchStat jauh lebih besar/multi-tenant.
- Export CSV ke SIAP PSSI masih manual copy-paste — cocok untuk skala SSB per
  klub, tapi kalau MatchStat menargetkan banyak SSB sekaligus, ada baiknya
  memantau apakah PSSI suatu saat menyediakan API resmi, agar proses ini bisa
  diotomatisasi secara sah.
