<?php
/**
 * inc-cetak-dokumen.php — helper & template dokumen cetak yang dipakai
 * bersama oleh cetak.php (admin, perlu login) dan cetak-publik.php
 * (orang tua, akses lewat kode_pendaftaran sendiri, tanpa login).
 * Jangan diakses langsung lewat URL - tidak ada output apa pun sendirian.
 */

function v($val, $fallback = '...........................................') {
    $val = trim((string) $val);
    return $val !== '' ? htmlspecialchars($val) : $fallback;
}
function tgl_indo($date) {
    if (!$date) return '...........';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $t = strtotime($date);
    if (!$t) return htmlspecialchars($date);
    return date('d', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function ttl($tempat, $tgl) {
    $tempat = trim((string)$tempat);
    $tgl = $tgl ? tgl_indo($tgl) : '';
    $s = trim($tempat . ($tempat && $tgl ? ', ' : '') . $tgl);
    return $s !== '' ? htmlspecialchars($s) : '...........................................';
}
// ---- kotak biaya, sesuai paket pendaftaran yang benar-benar dipilih ----
function render_biaya_box($r) {
    $materai = 15000;

    if (($r['jenis_pendaftar'] ?? 'Baru') === 'Pemain Lama') {
        $klaim = $r['klaim_lunas_lama'] ?? '-';
        ob_start(); ?>
        <div class="biaya-box">
          <b>Biaya (Lengkapi Data Pemain Lama): Rp 0,-</b>
          <ul>
            <li>Tidak ada biaya pendaftaran baru — sudah terdaftar sebelumnya secara manual</li>
            <li>Materai Dokumen Resmi (Surat Persetujuan Data Pribadi): Rp 15.000,-</li>
          </ul>
          <p style="margin:6px 0 0; font-style:italic; font-size:11pt;">Klaim status pembayaran pendaftaran lama (oleh orang tua/wali): <?= htmlspecialchars($klaim) ?>.</p>
          <p style="margin-top:8px;"><b>Total Bayar: Rp <?= number_format($materai, 0, ',', '.') ?>,-</b></p>
        </div>
        <?php return ob_get_clean();
    }

    $paket = $r['paket_pendaftaran'] ?? 'Lunas';

    if ($paket === 'Binaan') {
        $pokok = 500000;
        $judul = 'Biaya Pendaftaran (Paket Binaan)';
        $items = [
            'Terdaftar sebagai Siswa Petra FC',
            '1 pasang Seragam Latihan',
            'Kurikulum Sepakbola (Praktek dan Teori) menurut kelompok usia',
        ];
        $catatan = 'Tanpa bola latihan & seragam ke-2 (dapat dibeli terpisah).';
    } elseif ($paket === 'Kondisi Ekonomi') {
        $pokok = (int) ($r['nominal_kondisi_ekonomi'] ?? 0);
        $judul = 'Biaya Pendaftaran (Sesuai Kondisi Ekonomi)';
        $items = [
            'Registrasi Database Online',
            'Kurikulum Sepakbola (Praktek dan Teori) menurut kelompok usia',
        ];
        $catatan = null;
    } else {
        $pokok = 2500000;
        $judul = 'Biaya Pendaftaran (Paket Lunas)';
        $items = [
            'Registrasi Database Online',
            '2 pasang Seragam Latihan',
            '1 Bola Latihan',
            'Kurikulum Sepakbola (Praktek dan Teori) menurut kelompok usia',
        ];
        $catatan = null;
    }
    $total = $pokok + $materai;

    ob_start(); ?>
    <div class="biaya-box">
      <b><?= htmlspecialchars($judul) ?>: Rp <?= number_format($pokok, 0, ',', '.') ?>,-</b>
      <ul>
        <?php foreach ($items as $it): ?><li><?= htmlspecialchars($it) ?></li><?php endforeach; ?>
        <li>Materai Dokumen Resmi (Surat Persetujuan Data Pribadi): Rp 15.000,-</li>
      </ul>
      <?php if ($catatan): ?><p style="margin:6px 0 0; font-style:italic; font-size:11pt;"><?= htmlspecialchars($catatan) ?></p><?php endif; ?>
      <p style="margin-top:8px;"><b>Total Bayar: Rp <?= number_format($total, 0, ',', '.') ?>,-</b></p>
    </div>
    <?php return ob_get_clean();
}

// ---- reusable table for the 3 legal documents (wali + pemain data) ----
function info_table($r, $alamatSiswa) {
    ob_start(); ?>
    <table class="info-table">
      <tr><td class="label">Nama Lengkap</td><td class="colon">:</td><td><?= v($r['wali_nama_lengkap']) ?></td></tr>
      <tr><td class="label">N.I.K</td><td class="colon">:</td><td><?= v($r['wali_nik']) ?></td></tr>
      <tr><td class="label">Tempat / Tgl. Lahir</td><td class="colon">:</td><td><?= ttl($r['wali_tempat_lahir'], $r['wali_tanggal_lahir']) ?></td></tr>
      <tr><td class="label">Alamat</td><td class="colon">:</td><td><?= v($r['wali_alamat']) ?></td></tr>
      <tr><td class="label">Nomor Telpon / Hp</td><td class="colon">:</td><td><?= v($r['wali_hp']) ?></td></tr>
    </table>
    <p style="margin:14px 0 6px;">Sebagai orang tua / wali dari pemain :</p>
    <table class="info-table">
      <tr><td class="label">Nama Lengkap</td><td class="colon">:</td><td><?= v($r['nama_lengkap']) ?></td></tr>
      <tr><td class="label">N.I.K</td><td class="colon">:</td><td><?= v($r['nik_siswa']) ?></td></tr>
      <tr><td class="label">Tempat / Tgl. Lahir</td><td class="colon">:</td><td><?= ttl($r['tempat_lahir'], $r['tanggal_lahir']) ?></td></tr>
      <tr><td class="label">Alamat</td><td class="colon">:</td><td><?= v($alamatSiswa) ?></td></tr>
      <tr><td class="label">Nomor Telpon / Hp</td><td class="colon">:</td><td><?= v($r['nomor_hp_siswa']) ?></td></tr>
    </table>
    <?php return ob_get_clean();
}

// ---- render 4 dokumen untuk 1 pendaftar ----
function render_docs($r, $jenis) {
    $sigWebPath = $r['file_tanda_tangan'] ? 'uploads/' . $r['file_tanda_tangan'] : null;
    $sigTag = $sigWebPath ? '<img src="'.htmlspecialchars($sigWebPath).'" class="sig-img" alt="Tanda tangan">' : '';
    // alamat siswa: dokumen asli tidak memisahkan alamat siswa dari orang tua, jadi pakai alamat ayah/ibu/wali sebagai fallback
    $alamatSiswa = $r['alamat_ayah'] ?: ($r['alamat_ibu'] ?: $r['wali_alamat']);
    $today = tgl_indo(date('Y-m-d'));
    ?>

<?php if ($jenis === 'semua' || $jenis === 'formulir'): ?>
<!-- ======================= DOKUMEN 1: FORMULIR PENDAFTARAN ======================= -->
<div class="doc-page">
  <div class="logo-header">
    <img src="assets/petra-logo.png" alt="Logo">
    <div class="lh-text">
      <div class="doc-title">FORMULIR PENDAFTARAN</div>
      <div class="doc-subtitle" style="margin-bottom:0;">SEKOLAH SEPAKBOLA PETRA</div>
      <div class="doc-subtitle">SENTANI PAPUA</div>
    </div>
    <div style="width:58px;"></div>
  </div>

  <div class="section-label">BIODATA SISWA</div>
  <div class="biodata-row"><span class="lbl">Nama</span>: <?= v($r['nama_lengkap']) ?></div>
  <div class="biodata-row"><span class="lbl">Nama Panggilan</span>: <?= v($r['nama_panggilan']) ?></div>
  <div class="biodata-row"><span class="lbl">N I K</span>: <?= v($r['nik_siswa']) ?></div>
  <div class="biodata-row"><span class="lbl">Jenis Kelamin</span>: <?= v($r['jenis_kelamin']) ?></div>
  <div class="biodata-row"><span class="lbl">Tempat / Tgl. Lahir</span>: <?= ttl($r['tempat_lahir'], $r['tanggal_lahir']) ?></div>
  <div class="biodata-row"><span class="lbl">Pendidikan</span>: <?= v($r['pendidikan']) ?></div>
  <div class="biodata-row"><span class="lbl">Nomor HP</span>: <?= v($r['nomor_hp_siswa']) ?></div>
  <div class="biodata-row"><span class="lbl">Tinggi / Berat Badan</span>: <?= v($r['tinggi_badan'],'-') ?> cm &nbsp;/&nbsp; <?= v($r['berat_badan'],'-') ?> kg</div>
  <div class="biodata-row"><span class="lbl">Golongan Darah</span>: <?= v($r['golongan_darah'],'-') ?></div>
  <div class="biodata-row"><span class="lbl">Pernah Operasi Tulang</span>: <?= v($r['riwayat_operasi_tulang'],'Tidak') ?></div>

  <div class="section-label">BIODATA ORANG TUA</div>
  <div class="biodata-row"><span class="lbl">Nama Ayah</span>: <?= v($r['nama_ayah']) ?></div>
  <div class="biodata-row"><span class="lbl">Alamat Rumah</span>: <?= v($r['alamat_ayah']) ?></div>
  <div class="biodata-row"><span class="lbl">Nomor HP</span>: <?= v($r['hp_ayah']) ?></div>
  <div class="biodata-row"><span class="lbl">Pekerjaan</span>: <?= v($r['pekerjaan_ayah']) ?></div>
  <div class="biodata-row" style="margin-top:10px;"><span class="lbl">Nama Ibu</span>: <?= v($r['nama_ibu']) ?></div>
  <div class="biodata-row"><span class="lbl">Alamat Rumah</span>: <?= v($r['alamat_ibu']) ?></div>
  <div class="biodata-row"><span class="lbl">Nomor HP</span>: <?= v($r['hp_ibu']) ?></div>
  <div class="biodata-row"><span class="lbl">Pekerjaan</span>: <?= v($r['pekerjaan_ibu']) ?></div>

  <p class="body-text">Dengan ini Kami mengajukan permohonan Putra / Putri kami untuk menjadi siswa SSB PETRA SENTANI dan bersedia untuk mematuhi segala ketentuan / peraturan yang berlaku.</p>

  <div class="sign-block">
    <div class="place-date">Sentani, <?= $today ?></div>
    <div class="role">Orang Tua / Wali</div>
    <?= $sigTag ?>
    <div class="sig-line"><?= v($r['wali_nama_lengkap'],'') ?></div>
  </div>

  <?= render_biaya_box($r) ?>
</div>
<?php endif; ?>

<?php if ($jenis === 'semua' || $jenis === 'persetujuan'): ?>
<!-- ======================= DOKUMEN 2: PERSETUJUAN DATA PRIBADI ======================= -->
<div class="doc-page">
  <div class="logo-header">
    <img src="assets/petra-logo.png" alt="Logo">
    <div class="lh-text">
      <div class="doc-title">SURAT PERNYATAAN</div>
      <div class="doc-subtitle">PERSETUJUAN DATA PRIBADI</div>
    </div>
    <div style="width:58px;"></div>
  </div>

  <p class="body-text">Yang bertanda tangan di bawah ini, saya :</p>
  <?= info_table($r, $alamatSiswa) ?>

  <p class="body-text">Dengan ini menyatakan :</p>
  <ol class="consent-list">
    <li>Saya bersedia dan menyetujui untuk memberikan data pribadi saya dan dokumen sesuai dengan regulasi yang berlaku di PSSI;</li>
    <li>Bahwa PSSI akan memberikan Data Pribadi dan dokumen Saya untuk tujuan registrasi stakeholder (Pemain, Official, Management Klub, Pemegang Saham Badan Hukum Klub, Perangkat Pertandingan) sepak bola di Indonesia.</li>
    <li>Bahwa PSSI akan memberikan Data Pribadi dan dokumen Saya kepada Pihak Ketiga sebagai berikut: PT Liga Indonesia Baru; AFF; AFC; FIFA.</li>
    <li>PSSI menjamin hak Saya sebagai Subjek Data Pribadi sesuai dengan Undang-undang Perlindungan Data Pribadi No. 27/2022 yang prosedurnya diatur dalam peraturan internal PSSI;</li>
    <li>Saya menyatakan bahwa Data Pribadi Saya dan dokumen yang Saya berikan adalah sah dan mengandung informasi terkini dan benar;</li>
    <li>Bahwa apabila Saya sewaktu-waktu ingin merubah persetujuan ini, Saya dapat menghubungi PSSI.</li>
  </ol>

  <div class="sign-block">
    <div class="place-date">Sentani, <?= $today ?></div>
    <div class="role">Mengetahui,</div>
    <div style="font-style:italic; margin-bottom:4px;">materai</div>
    <?= $sigTag ?>
    <div class="sig-line"><?= v($r['wali_nama_lengkap'],'') ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($jenis === 'semua' || $jenis === 'pernyataan'): ?>
<!-- ======================= DOKUMEN 3: SURAT PERNYATAAN PEMAIN ======================= -->
<div class="doc-page">
  <div class="logo-header">
    <img src="assets/petra-logo.png" alt="Logo">
    <div class="lh-text">
      <div class="doc-title">SURAT PERNYATAAN PEMAIN</div>
    </div>
    <div style="width:58px;"></div>
  </div>
  <br>
  <p class="body-text">Yang bertanda tangan di bawah ini, saya :</p>
  <?= info_table($r, $alamatSiswa) ?>

  <p class="body-text">Dengan ini menyatakan bahwa saya belum pernah bergabung dengan klub anggota / calon anggota PSSI dan klub dari luar Indonesia.</p>

  <div class="sign-block">
    <div class="place-date">Jayapura, <?= $today ?></div>
    <div class="role">Mengetahui, Orang Tua / Wali</div>
    <?= $sigTag ?>
    <div class="sig-line"><?= v($r['wali_nama_lengkap'],'') ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($jenis === 'semua' || $jenis === 'amatir'): ?>
<!-- ======================= DOKUMEN 4: PERJANJIAN AMATIR ======================= -->
<div class="doc-page">
  <div class="logo-header">
    <img src="assets/petra-logo.png" alt="Logo">
    <div class="lh-text">
      <div class="doc-title">PERJANJIAN AMATIR</div>
    </div>
    <div style="width:58px;"></div>
  </div>
  <br>
  <p class="body-text">Yang bertanda tangan di bawah ini, saya :</p>
  <?= info_table($r, $alamatSiswa) ?>

  <p class="body-text">Dengan ini menyatakan bahwa anak saya tersebut di atas, saya ijinkan untuk mendaftarkan diri dan menjadi anggota dari <b>SSB PETRA SENTANI</b> dengan status "<b>PEMAIN AMATIR</b>".</p>
  <p class="body-text">Untuk itu saya akan memenuhi serta mematuhi segala peraturan yang diberlakukan pada anak saya sesuai peraturan <b>SSB PETRA SENTANI</b> dengan status "<b>PEMAIN AMATIR</b>".</p>

  <div class="sign-block">
    <div class="place-date">Jayapura, <?= $today ?></div>
    <div class="role">Mengetahui, Orang Tua / Wali</div>
    <?= $sigTag ?>
    <div class="sig-line"><?= v($r['wali_nama_lengkap'],'') ?></div>
  </div>
</div>
<?php endif; ?>
<?php
}

// ---- halaman cetak lengkap (head+style+print-bar+dokumen semua $rows) ----
function render_print_page($rows, $jenis, $judulBar) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cetak Dokumen<?= count($rows) === 1 ? ' — ' . v($rows[0]['nama_lengkap'], '') : ' Massal' ?></title>
<style>
  @page { size: 215mm 330mm; margin: 18mm 16mm 14mm; }
  * { box-sizing: border-box; }
  body {
    font-family: 'Times New Roman', Georgia, serif;
    font-size: 12.5pt;
    line-height: 1.55;
    color: #111;
    margin: 0;
    background: #ECECEC;
  }
  .doc-page {
    background: #fff;
    width: 215mm;
    min-height: 330mm;
    margin: 14px auto;
    padding: 18mm 16mm 14mm;
    page-break-after: always;
    box-shadow: 0 2px 10px rgba(0,0,0,.15);
  }
  .doc-page:last-child { page-break-after: auto; }

  .doc-title { text-align:center; font-weight:bold; font-size:14pt; margin:0 0 2px; text-decoration:underline; }
  .doc-title.small { font-size:13pt; }
  .doc-subtitle { text-align:center; font-weight:bold; font-size:12.5pt; margin:0 0 18px; }
  .logo-header { display:flex; align-items:center; gap:14px; margin-bottom:6px; }
  .logo-header img { width:58px; height:58px; object-fit:contain; }
  .logo-header .lh-text { text-align:center; flex:1; }
  .lh-text .doc-title, .lh-text .doc-subtitle { margin:0; }

  table.info-table { width:100%; border-collapse:collapse; margin:14px 0 16px; }
  table.info-table td { padding:4px 6px; vertical-align:top; font-size:12.5pt; }
  table.info-table td.label { width:36%; }
  table.info-table td.colon { width:14px; }

  .section-label { font-weight:bold; text-decoration:underline; margin:16px 0 6px; }

  .biodata-row { margin:6px 0; font-size:12.5pt; }
  .biodata-row .lbl { display:inline-block; width:150px; font-weight:bold; }

  ol.consent-list, ul.consent-list { margin:10px 0; padding-left:22px; }
  ol.consent-list li, ul.consent-list li { margin-bottom:8px; text-align:justify; }

  p.body-text { text-align:justify; margin:12px 0; }

  .sign-block { margin-top:34px; width:60%; margin-left:auto; text-align:center; }
  .sign-block .place-date { margin-bottom:4px; }
  .sign-block .role { margin-bottom:2px; }
  .sig-img { width:150px; max-height:70px; object-fit:contain; display:block; margin:6px auto; }
  .sig-line { margin-top:2px; font-weight:bold; border-top: 1px solid #111; display:inline-block; padding-top:4px; min-width:200px; }
  .materai-note { font-size:10pt; font-style:italic; color:#555; margin-top:2px; }

  .biaya-box { margin-top:22px; border-top:1px solid #999; padding-top:10px; }
  .biaya-box b { font-size:12.5pt; }
  .biaya-box ul { margin:6px 0 0; padding-left:20px; }

  .kode-stamp { position:absolute; top:8mm; right:10mm; font-size:8.5pt; color:#999; }

  .print-bar {
    position: sticky; top:0; z-index:50;
    background:#16302A; color:#fff; padding:10px 16px;
    display:flex; justify-content:space-between; align-items:center;
    font-family: system-ui, sans-serif; font-size:14px;
  }
  .print-bar button {
    background:#F0B429; color:#0F1F52; border:none; border-radius:8px;
    padding:9px 16px; font-weight:700; font-size:14px;
  }
  @media print {
    body { background:#fff; }
    .print-bar { display:none; }
    .doc-page { box-shadow:none; margin:0; max-width:none; }
  }
</style>
</head>
<body>

<div class="print-bar">
  <span><?= $judulBar ?></span>
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
</div>

<?php foreach ($rows as $r): ?>
<!-- ============================================================ -->
<!-- Dokumen untuk: <?= htmlspecialchars($r['kode_pendaftaran']) ?> -->
<!-- ============================================================ -->
<?php render_docs($r, $jenis); ?>
<?php endforeach; ?>

</body>
</html>
<?php
}
