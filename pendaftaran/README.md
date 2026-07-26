# Formulir Pendaftaran Online — SSB PETRA Sentani

Form digital pengganti 4 dokumen cetak:
- SSB PETRA Form Pendaftaran (biodata siswa & orang tua)
- Surat Persetujuan Data Pribadi
- Surat Pernyataan Pemain
- Surat Perjanjian Amatir

Calon siswa/orang tua mengisi semua data di HP, upload dokumen persyaratan, tanda tangan langsung di layar, lalu kirim. Data tersimpan di database **dan** dikirim notifikasi email ke admin (sesuai pilihan Anda).

## Isi folder

| File | Fungsi |
|---|---|
| `index.html` | Formulir yang dilihat calon siswa/ortu (5 langkah) |
| `submit.php` | Menerima data, menyimpan file & tanda tangan, insert ke database, kirim email |
| `admin.php` | Halaman admin (login) untuk melihat, memfilter status, dan mengunduh dokumen tiap pendaftar |
| `config.php` | **Wajib diisi** — kredensial database, email admin, login admin |
| `schema.sql` | Struktur tabel database — diimport sekali lewat phpMyAdmin |
| `assets/petra-logo.png` | Logo PETRA FC yang tampil di kop formulir |
| `uploads/` | Tempat file hasil upload disimpan otomatis (per kode pendaftaran) |

## Langkah pasang di Hostinger (semua bisa dari HP, lewat hPanel)

**1. Buat database MySQL**
- Buka hPanel → *Databases* → *MySQL Databases*
- Buat database baru (contoh: `u123456789_petrafc`) dan user + password
- Catat: nama database, username, password

**2. Import struktur tabel**
- Di hPanel → *Databases* → *phpMyAdmin* → pilih database tadi
- Tab **Import** → upload `schema.sql` → jalankan

**3. Isi `config.php`**
Sebelum upload, buka `config.php` dan ganti:
- `DB_NAME`, `DB_USER`, `DB_PASS` — sesuai data dari langkah 1
- `ADMIN_EMAIL` — email yang akan menerima notifikasi pendaftar baru
- `MAIL_FROM` — sebaiknya buat email `noreply@petrafc.com` dulu di hPanel → *Emails*
- `ADMIN_USERNAME` dan `ADMIN_PASSWORD` — untuk login ke `admin.php` (**wajib diganti**, jangan pakai contoh)

**4. Upload semua file ke server**
- hPanel → *File Manager* → masuk ke folder `public_html` (atau subfolder jika mau, misal `public_html/pendaftaran`)
- Upload seluruh isi folder ini (bisa upload sebagai `.zip` lalu klik kanan → *Extract*, sama seperti workaround yang biasa dipakai untuk project GitHub Anda)
- Pastikan folder `uploads/` ikut terupload beserta file `.htaccess` di dalamnya

**5. Cek permission folder uploads**
- Klik kanan folder `uploads` di File Manager → *Permissions* → set ke `755` (jika belum otomatis)

**6. Uji coba**
- Buka `https://petrafc.com/index.html` (atau `https://petrafc.com/pendaftaran/index.html` jika ditaruh di subfolder) dari HP
- Isi form contoh sampai selesai, kirim
- Cek email admin masuk, lalu buka `https://petrafc.com/admin.php` untuk lihat datanya

## Alur data

```
Orang tua isi form (HP) → submit.php:
   1. Validasi data & file
   2. Simpan file (akte, KK, dll) + tanda tangan ke uploads/{kode}/
   3. Insert 1 baris ke tabel `pendaftaran`
   4. Kirim email notifikasi ke admin
→ Admin buka admin.php: lihat semua pendaftar, unduh dokumen,
   ubah status (Baru / Diverifikasi / Diterima / Ditolak)
```

## Catatan penting

- **Materai**: form digital tidak bisa menggantikan materai fisik yang diminta di Surat Persetujuan Data Pribadi. Jika PSSI/pihak berwenang mewajibkan materai asli, sebaiknya cetak ulang dokumen final untuk ditandatangani + materai saat pemain resmi terdaftar sebagai anggota klub PSSI — form ini berfungsi sebagai pendaftaran awal & pengumpulan data/dokumen.
- **Ukuran file**: dibatasi 5MB per dokumen (bisa diubah di `config.php` → `MAX_FILE_SIZE_MB`). Kalau orang tua sering upload foto besar dari HP, sampaikan agar kompres dulu atau gunakan fitur "scan dokumen" di HP yang biasanya otomatis mengecilkan ukuran.
- **Keamanan**: ganti `ADMIN_PASSWORD` dan `DB_PASS` dengan password kuat sebelum upload ke server — jangan gunakan contoh di `config.php`.
- **Backup**: file upload tidak otomatis ter-backup — aktifkan backup otomatis Hostinger (hPanel → *Backups*) agar dokumen pendaftar tidak hilang.
