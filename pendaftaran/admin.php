<?php
// Cegah admin.php ikut disimpan oleh cache apa pun (browser/LiteSpeed/CDN) -
// halaman ini berisi data pribadi & harus selalu tampil versi terbaru.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/config.php';

// ---------- Login ----------
if (isset($_POST['login_username'], $_POST['login_password'])) {
    if ($_POST['login_username'] === ADMIN_USERNAME && $_POST['login_password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['petra_admin'] = true;
    } else {
        $loginError = 'Username atau password salah.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ---------- Update status ----------
if (!empty($_SESSION['petra_admin']) && isset($_POST['update_id'])) {
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('UPDATE pendaftaran SET status = :status, catatan_admin = :catatan WHERE id = :id');
        $stmt->execute([
            'status' => $_POST['status'],
            'catatan' => trim(strip_tags($_POST['catatan_admin'] ?? '')),
            'id' => (int) $_POST['update_id'],
        ]);
        header('Location: admin.php?updated=1');
        exit;
    } catch (Exception $e) {
        $dbError = 'Gagal menyimpan perubahan status. Server database sedang sibuk, coba lagi sesaat lagi.';
    }
}

if (empty($_SESSION['petra_admin'])):
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login Admin — SSB PETRA</title>
<style>
  body{font-family:system-ui,sans-serif; background:#EEF1F9; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0;}
  form{background:#fff; padding:28px 24px; border-radius:14px; width:100%; max-width:320px; box-shadow:0 4px 20px rgba(0,0,0,.08);}
  h1{font-size:18px; color:#0F1F52; margin:0 0 18px;}
  input{width:100%; padding:11px; margin-bottom:12px; border:1.5px solid #DCE1F0; border-radius:9px; font-size:15px; box-sizing:border-box;}
  button{width:100%; padding:12px; background:#F0B429; color:#0F1F52; border:none; border-radius:9px; font-weight:700; font-size:15px;}
  .err{color:#D6242A; font-size:13px; margin-bottom:10px;}
</style></head><body>
<form method="post">
  <h1>Login Admin — SSB PETRA</h1>
  <?php if (!empty($loginError)): ?><div class="err"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
  <input type="text" name="login_username" placeholder="Username" required>
  <input type="password" name="login_password" placeholder="Password" required>
  <button type="submit">Masuk</button>
</form>
</body></html>
<?php
exit;
endif;

// ---------- Data list ----------
$rows = [];
$listError = null;
try {
    $pdo = get_db();
    $rows = $pdo->query('SELECT * FROM pendaftaran ORDER BY tanggal_daftar DESC')->fetchAll();
} catch (Exception $e) {
    $listError = 'Gagal memuat data pendaftar dari database. Server database sedang sibuk atau tidak merespon — coba muat ulang halaman ini dalam beberapa saat.';
}
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Pendaftaran — SSB PETRA</title>
<style>
  *{box-sizing:border-box;}
  body{font-family:system-ui,sans-serif; background:#EEF1F9; margin:0; color:#141E3C;}
  header{background:#0F1F52; color:#fff; padding:16px 18px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0;}
  header h1{font-size:16px; margin:0;}
  header a{color:#F6D9A0; font-size:13px; text-decoration:none;}
  main{max-width:900px; margin:0 auto; padding:16px;}
  .card{background:#fff; border-radius:12px; padding:16px; margin-bottom:14px; border:1px solid #DCE1F0;}
  .card summary{cursor:pointer; font-weight:600; font-size:15px; list-style:none;}
  .card summary::-webkit-details-marker{display:none;}
  .meta{color:#4B5468; font-size:12.5px; margin-top:3px;}
  .status-pill{display:inline-block; padding:3px 10px; border-radius:99px; font-size:11.5px; font-weight:600;}
  .st-Baru{background:#FCE9D8; color:#D69A0C;}
  .st-Diverifikasi{background:#E4EEF9; color:#2A5C9A;}
  .st-Diterima{background:#E4F3E9; color:#2F6B4F;}
  .st-Ditolak{background:#FCEFEC; color:#D6242A;}
  .detail-grid{display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:14px; font-size:13.5px;}
  .detail-grid div b{display:block; font-size:11px; color:#4B5468; font-weight:600;}
  .files{margin-top:14px; display:flex; flex-wrap:wrap; gap:8px;}
  .files a{font-size:12.5px; background:#EEF1F9; padding:6px 10px; border-radius:7px; color:#0F1F52; text-decoration:none; border:1px solid #DCE1F0;}
  form.status-form{margin-top:14px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;}
  form.status-form select, form.status-form input{padding:8px; border-radius:7px; border:1.5px solid #DCE1F0; font-size:13px;}
  form.status-form button{padding:8px 14px; background:#0F1F52; color:#fff; border:none; border-radius:7px; font-size:13px;}
  .empty{text-align:center; color:#4B5468; padding:40px;}
</style></head><body>
<header>
  <h1>Daftar Pendaftaran — SSB PETRA (<?= count($rows) ?>)</h1>
  <a href="?logout=1">Keluar</a>
</header>
<main>
<?php if (!empty($dbError)): ?>
  <div class="empty" style="color:#D6242A;"><?= htmlspecialchars($dbError) ?></div>
<?php endif; ?>
<?php if (!empty($listError)): ?>
  <div class="empty" style="color:#D6242A;"><?= htmlspecialchars($listError) ?></div>
<?php elseif (!$rows): ?>
  <div class="empty">Belum ada pendaftaran masuk.</div>
<?php else: foreach ($rows as $r): ?>
  <details class="card">
    <summary>
      <?= htmlspecialchars($r['nama_lengkap']) ?>
      <span class="status-pill st-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span>
      <div class="meta"><?= htmlspecialchars($r['kode_pendaftaran']) ?> — <?= htmlspecialchars($r['tanggal_daftar']) ?></div>
    </summary>

    <div class="detail-grid">
      <div><b>Jenis Kelamin</b><?= htmlspecialchars($r['jenis_kelamin'] ?? '-') ?></div>
      <div><b>Tanggal Lahir</b><?= htmlspecialchars($r['tanggal_lahir'] ?? '-') ?></div>
      <div><b>Kaki Dominan / Posisi</b><?= htmlspecialchars(trim(($r['kaki_dominan'] ?? '') . ' — ' . ($r['posisi_bermain'] ?? ''), ' —')) ?: '-' ?></div>
      <div><b>Tinggi / Berat</b><?= htmlspecialchars(($r['tinggi_badan'] ?? '-') . ' cm / ' . ($r['berat_badan'] ?? '-') . ' kg') ?></div>
      <div><b>Nama Wali</b><?= htmlspecialchars($r['wali_nama_lengkap'] ?? '-') ?></div>
      <div><b>HP Wali</b><?= htmlspecialchars($r['wali_hp'] ?? '-') ?></div>
      <div><b>Nama Ayah</b><?= htmlspecialchars($r['nama_ayah'] ?? '-') ?></div>
      <div><b>Nama Ibu</b><?= htmlspecialchars($r['nama_ibu'] ?? '-') ?></div>
    </div>

    <div class="files">
      <?php
      $fileLabels = [
        'file_akte_lahir' => 'Akte Lahir', 'file_ijazah_raport' => 'Ijazah/Raport',
        'file_kartu_keluarga' => 'Kartu Keluarga', 'file_raport_dalam' => 'Raport Dalam',
        'file_nisn' => 'NISN', 'file_kia' => 'KIA', 'file_pas_foto' => 'Pas Foto',
        'file_tanda_tangan' => 'Tanda Tangan',
      ];
      foreach ($fileLabels as $field => $label):
        if (!empty($r[$field])):
      ?>
        <a href="uploads/<?= htmlspecialchars($r[$field]) ?>" target="_blank"><?= $label ?> ↗</a>
      <?php endif; endforeach; ?>
    </div>

    <div class="files" style="margin-top:8px;">
      <a href="cetak.php?id=<?= (int)$r['id'] ?>&jenis=semua" target="_blank" style="background:#F0B429; border-color:#D69A0C; color:#0F1F52; font-weight:700;">🖨️ Cetak Semua Dokumen (4)</a>
      <a href="cetak.php?id=<?= (int)$r['id'] ?>&jenis=formulir" target="_blank">Formulir Pendaftaran</a>
      <a href="cetak.php?id=<?= (int)$r['id'] ?>&jenis=persetujuan" target="_blank">Persetujuan Data</a>
      <a href="cetak.php?id=<?= (int)$r['id'] ?>&jenis=pernyataan" target="_blank">Pernyataan Pemain</a>
      <a href="cetak.php?id=<?= (int)$r['id'] ?>&jenis=amatir" target="_blank">Perjanjian Amatir</a>
    </div>

    <form class="status-form" method="post">
      <input type="hidden" name="update_id" value="<?= (int)$r['id'] ?>">
      <select name="status">
        <?php foreach (['Baru','Diverifikasi','Diterima','Ditolak'] as $st): ?>
          <option value="<?= $st ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="catatan_admin" placeholder="Catatan admin" value="<?= htmlspecialchars($r['catatan_admin'] ?? '') ?>" style="flex:1; min-width:140px;">
      <button type="submit">Simpan</button>
    </form>
  </details>
<?php endforeach; endif; ?>
</main>
</body></html>