<?php
// Cegah admin.php ikut disimpan oleh cache apa pun (browser/LiteSpeed/CDN) -
// halaman ini berisi data pribadi & harus selalu tampil versi terbaru.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/config.php';

function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_login_attempt($pdo, $username, $berhasil) {
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_login_log (username, ip, berhasil) VALUES (:u, :ip, :b)');
        $stmt->execute(['u' => $username, 'ip' => client_ip(), 'b' => $berhasil ? 1 : 0]);
    } catch (Exception $e) {
        // Tabel admin_login_log mungkin belum diimport - jangan sampai gagal log menghalangi login.
    }
}

function is_rate_limited($pdo) {
    $maxGagal = 5;
    $windowMenit = 15;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM admin_login_log WHERE ip = :ip AND berhasil = 0 AND waktu > (NOW() - INTERVAL $windowMenit MINUTE)");
        $stmt->execute(['ip' => client_ip()]);
        $row = $stmt->fetch();
        return ($row['c'] ?? 0) >= $maxGagal;
    } catch (Exception $e) {
        return false; // tabel belum ada / DB bermasalah -> jangan blokir user krn error internal
    }
}

// ---------- Login ----------
$loginError = null;
if (isset($_POST['login_username'], $_POST['login_password'])) {
    try {
        $pdo = get_db();
        if (is_rate_limited($pdo)) {
            $loginError = 'Terlalu banyak percobaan gagal login dari perangkat ini. Coba lagi dalam beberapa menit.';
        } else {
            $u = trim($_POST['login_username']);
            $p = $_POST['login_password'];
            $ok = false;
            $displayName = $u;

            // 1) akun per-staff di tabel admin_users
            try {
                $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :u');
                $stmt->execute(['u' => $u]);
                $acc = $stmt->fetch();
                if ($acc && password_verify($p, $acc['password_hash'])) {
                    $ok = true;
                    $displayName = $acc['nama_tampilan'];
                }
            } catch (Exception $e) {
                // tabel admin_users belum ada (instalasi lama) - lanjut ke fallback bootstrap
            }

            // 2) fallback: akun bootstrap tunggal dari config.php
            if (!$ok && $u === ADMIN_USERNAME && $p === ADMIN_PASSWORD) {
                $ok = true;
                $displayName = 'Admin';
            }

            log_login_attempt($pdo, $u, $ok);

            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['petra_admin'] = true;
                $_SESSION['petra_admin_user'] = $u;
                $_SESSION['petra_admin_nama'] = $displayName;
            } else {
                $loginError = 'Username atau password salah.';
            }
        }
    } catch (Exception $e) {
        $loginError = 'Gagal terhubung ke database. Server database sedang sibuk, coba lagi sesaat lagi.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ---------- Update status pendaftar ----------
if (!empty($_SESSION['petra_admin']) && isset($_POST['update_id'])) {
    try {
        $pdo = get_db();
        $targetId = (int) $_POST['update_id'];
        $newStatus = $_POST['status'];
        $catatan = trim(strip_tags($_POST['catatan_admin'] ?? ''));

        $stmt = $pdo->prepare('UPDATE pendaftaran SET status = :status, catatan_admin = :catatan WHERE id = :id');
        $stmt->execute(['status' => $newStatus, 'catatan' => $catatan, 'id' => $targetId]);

        try {
            $log = $pdo->prepare('INSERT INTO admin_aktivitas_log (username, aksi, pendaftaran_id) VALUES (:u, :a, :id)');
            $log->execute([
                'u' => $_SESSION['petra_admin_user'] ?? '?',
                'a' => 'Ubah status pendaftar #' . $targetId . ' -> ' . $newStatus,
                'id' => $targetId,
            ]);
        } catch (Exception $e) {
            // tabel admin_aktivitas_log belum ada - abaikan, status tetap tersimpan
        }

        header('Location: admin.php?updated=1');
        exit;
    } catch (Exception $e) {
        $dbError = 'Gagal menyimpan perubahan status. Server database sedang sibuk, coba lagi sesaat lagi.';
    }
}

// ---------- Toggle status materai (e-meterai sudah/belum ditempel) ----------
if (!empty($_SESSION['petra_admin']) && isset($_POST['toggle_materai_id'])) {
    try {
        $pdo = get_db();
        $targetId = (int) $_POST['toggle_materai_id'];
        $statusBaru = ($_POST['materai_status_baru'] ?? 'Sudah') === 'Sudah' ? 'Sudah' : 'Belum';

        $stmt = $pdo->prepare('UPDATE pendaftaran SET materai_status = :s WHERE id = :id');
        $stmt->execute(['s' => $statusBaru, 'id' => $targetId]);

        try {
            $log = $pdo->prepare('INSERT INTO admin_aktivitas_log (username, aksi, pendaftaran_id) VALUES (:u, :a, :id)');
            $log->execute([
                'u' => $_SESSION['petra_admin_user'] ?? '?',
                'a' => 'Tandai materai pendaftar #' . $targetId . ' -> ' . $statusBaru,
                'id' => $targetId,
            ]);
        } catch (Exception $e) {
            // tabel admin_aktivitas_log belum ada - abaikan
        }

        header('Location: admin.php?updated=1');
        exit;
    } catch (Exception $e) {
        $dbError = 'Gagal menyimpan status materai. Kemungkinan kolom materai_status belum ada (lihat schema.sql) atau server database sedang sibuk.';
    }
}

// ---------- Update status pembayaran ----------
if (!empty($_SESSION['petra_admin']) && isset($_POST['update_bayar_id'])) {
    try {
        $pdo = get_db();
        $targetId = (int) $_POST['update_bayar_id'];
        $statusBayar = in_array($_POST['status_pembayaran'] ?? '', ['Belum Bayar', 'Menunggu Konfirmasi', 'Lunas'], true)
            ? $_POST['status_pembayaran'] : 'Belum Bayar';

        $stmt = $pdo->prepare('UPDATE pendaftaran SET status_pembayaran = :s WHERE id = :id');
        $stmt->execute(['s' => $statusBayar, 'id' => $targetId]);

        try {
            $log = $pdo->prepare('INSERT INTO admin_aktivitas_log (username, aksi, pendaftaran_id) VALUES (:u, :a, :id)');
            $log->execute([
                'u' => $_SESSION['petra_admin_user'] ?? '?',
                'a' => 'Ubah status pembayaran pendaftar #' . $targetId . ' -> ' . $statusBayar,
                'id' => $targetId,
            ]);
        } catch (Exception $e) {
            // tabel admin_aktivitas_log belum ada - abaikan
        }

        header('Location: admin.php?updated=1');
        exit;
    } catch (Exception $e) {
        $dbError = 'Gagal menyimpan status pembayaran. Kemungkinan kolom status_pembayaran belum ada (lihat schema.sql) atau server database sedang sibuk.';
    }
}

// ---------- Kelola admin: tambah akun ----------
$adminMgmtError = null;
if (!empty($_SESSION['petra_admin']) && isset($_POST['add_admin_username'])) {
    try {
        $pdo = get_db();
        $newUser = trim($_POST['add_admin_username']);
        $newNama = trim($_POST['add_admin_nama'] ?? '') ?: $newUser;
        $newPass = $_POST['add_admin_password'] ?? '';
        if ($newUser === '' || strlen($newPass) < 8) {
            $adminMgmtError = 'Username wajib diisi & password minimal 8 karakter.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, nama_tampilan) VALUES (:u, :h, :n)');
            $stmt->execute(['u' => $newUser, 'h' => password_hash($newPass, PASSWORD_DEFAULT), 'n' => $newNama]);
            header('Location: admin.php?admin_added=1');
            exit;
        }
    } catch (Exception $e) {
        $adminMgmtError = 'Gagal menambah akun admin. Kemungkinan username sudah dipakai, atau tabel admin_users belum diimport (lihat schema.sql).';
    }
}

// ---------- Kelola admin: hapus akun ----------
if (!empty($_SESSION['petra_admin']) && isset($_GET['delete_admin_id'])) {
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => (int) $_GET['delete_admin_id']]);
        header('Location: admin.php?admin_deleted=1');
        exit;
    } catch (Exception $e) {
        $adminMgmtError = 'Gagal menghapus akun admin.';
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

// ---------- Siapkan koneksi utk sisa halaman ----------
$pdo = null;
$connError = null;
try {
    $pdo = get_db();
} catch (Exception $e) {
    $connError = 'Tidak bisa terhubung ke database. Server database sedang sibuk atau tidak merespon — coba muat ulang halaman ini dalam beberapa saat.';
}

$rows = [];
$listError = null;
if ($pdo && !$connError) {
    try {
        $rows = $pdo->query('SELECT * FROM pendaftaran ORDER BY tanggal_daftar DESC')->fetchAll();
    } catch (Exception $e) {
        $listError = 'Gagal memuat data pendaftar dari database. Coba muat ulang halaman ini dalam beberapa saat.';
    }
}

$adminList = [];
$adminListError = null;
if ($pdo && !$connError) {
    try {
        $adminList = $pdo->query('SELECT * FROM admin_users ORDER BY dibuat_pada DESC')->fetchAll();
    } catch (Exception $e) {
        $adminListError = 'Tabel admin_users belum tersedia — import ulang schema.sql lewat phpMyAdmin untuk mengaktifkan fitur multi-admin.';
    }
}

$aktivitasList = [];
$loginLogList = [];
if ($pdo && !$connError) {
    try { $aktivitasList = $pdo->query('SELECT * FROM admin_aktivitas_log ORDER BY waktu DESC LIMIT 30')->fetchAll(); } catch (Exception $e) {}
    try { $loginLogList = $pdo->query('SELECT * FROM admin_login_log ORDER BY waktu DESC LIMIT 30')->fetchAll(); } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Pendaftaran — SSB PETRA</title>
<style>
  *{box-sizing:border-box;}
  body{font-family:system-ui,sans-serif; background:#EEF1F9; margin:0; color:#141E3C;}
  header{background:#0F1F52; color:#fff; padding:16px 18px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:60;}
  header h1{font-size:16px; margin:0;}
  header .who{font-size:12px; color:#B9C4E8; margin-top:2px;}
  header a{color:#F6D9A0; font-size:13px; text-decoration:none;}
  main{max-width:900px; margin:0 auto; padding:16px;}
  .card{background:#fff; border-radius:12px; padding:16px; margin-bottom:14px; border:1px solid #DCE1F0;}
  .card summary{cursor:pointer; font-weight:600; font-size:15px; list-style:none;}
  .card summary::-webkit-details-marker{display:none;}
  .meta{color:#4B5468; font-size:12.5px; margin-top:3px;}
  .status-pill{display:inline-block; padding:3px 10px; border-radius:99px; font-size:11.5px; font-weight:600; margin-left:4px;}
  .st-Menunggu-Kelengkapan{background:#EFEFEF; color:#6B7280;}
  .st-Baru{background:#FCE9D8; color:#D69A0C;}
  .st-Diverifikasi{background:#E4EEF9; color:#2A5C9A;}
  .st-Diterima{background:#E4F3E9; color:#2F6B4F;}
  .st-Ditolak{background:#FCEFEC; color:#D6242A;}
  .mt-Belum{background:#FCEFEC; color:#D6242A;}
  .mt-Sudah{background:#E4F3E9; color:#2F6B4F;}
  .by-Belum-Bayar{background:#FCEFEC; color:#D6242A;}
  .by-Menunggu-Konfirmasi{background:#FCE9D8; color:#D69A0C;}
  .by-Lunas{background:#E4F3E9; color:#2F6B4F;}
  .jp-lama{background:#F3E8D8; color:#8B5A1F;}
  form.materai-form{margin-top:10px; display:inline-block;}
  form.materai-form button{padding:6px 12px; border-radius:7px; font-size:12.5px; font-weight:600; border:1.5px solid #DCE1F0; background:#fff; color:#0F1F52;}
  .detail-grid{display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:14px; font-size:13.5px;}
  .detail-grid div b{display:block; font-size:11px; color:#4B5468; font-weight:600;}
  .files{margin-top:14px; display:flex; flex-wrap:wrap; gap:8px;}
  .files a{font-size:12.5px; background:#EEF1F9; padding:6px 10px; border-radius:7px; color:#0F1F52; text-decoration:none; border:1px solid #DCE1F0;}
  form.status-form{margin-top:14px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;}
  form.status-form select, form.status-form input{padding:8px; border-radius:7px; border:1.5px solid #DCE1F0; font-size:13px;}
  form.status-form button{padding:8px 14px; background:#0F1F52; color:#fff; border:none; border-radius:7px; font-size:13px;}
  .empty{text-align:center; color:#4B5468; padding:40px;}
  .bulk-bar{background:#fff; border:1px solid #DCE1F0; border-radius:12px; padding:10px 14px; margin-bottom:14px; display:flex; align-items:center; gap:14px; font-size:13.5px;}
  .bulk-bar button{background:#0F1F52; color:#fff; border:none; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600;}
  .bulk-bar button:disabled{opacity:.4;}
  .pilih-cetak{margin-right:8px; transform:scale(1.15);}
  .mgmt-card summary{display:flex; align-items:center; gap:6px;}
  .log-row{font-size:12.5px; padding:5px 0; border-bottom:1px solid #EEF1F9;}
  .log-row:last-child{border-bottom:none;}
  .ok-text{color:#2F6B4F;}
  .fail-text{color:#D6242A;}
  .add-admin-form{display:flex; flex-direction:column; gap:8px; max-width:340px; margin-top:10px;}
  .add-admin-form input{padding:9px; border-radius:7px; border:1.5px solid #DCE1F0; font-size:13px;}
  .add-admin-form button{padding:9px; background:#0F1F52; color:#fff; border:none; border-radius:7px; font-size:13px; font-weight:600;}
</style></head><body>
<header>
  <div>
    <h1>Daftar Pendaftaran — SSB PETRA (<?= count($rows) ?>)</h1>
    <div class="who">Login sebagai <?= htmlspecialchars($_SESSION['petra_admin_nama'] ?? 'Admin') ?></div>
  </div>
  <a href="?logout=1">Keluar</a>
</header>
<main>
<?php if (!empty($connError)): ?>
  <div class="empty" style="color:#D6242A;"><?= htmlspecialchars($connError) ?></div>
<?php endif; ?>

<details class="card mgmt-card">
  <summary>⚙️ Kelola Akun Admin (<?= count($adminList) ?>)</summary>
  <?php if ($adminListError): ?>
    <p style="color:#D6242A; font-size:13px;"><?= htmlspecialchars($adminListError) ?></p>
  <?php else: ?>
    <?php if ($adminMgmtError): ?><div class="err" style="color:#D6242A; font-size:13px; margin:8px 0;"><?= htmlspecialchars($adminMgmtError) ?></div><?php endif; ?>
    <?php if (!$adminList): ?>
      <p style="color:#4B5468; font-size:13px; margin-top:8px;">Belum ada akun tambahan — masih memakai akun bootstrap dari <code>config.php</code>.</p>
    <?php else: foreach ($adminList as $a): ?>
      <div class="log-row" style="display:flex; justify-content:space-between; align-items:center;">
        <span><b><?= htmlspecialchars($a['nama_tampilan']) ?></b> (<?= htmlspecialchars($a['username']) ?>) — dibuat <?= htmlspecialchars($a['dibuat_pada']) ?></span>
        <a href="?delete_admin_id=<?= (int)$a['id'] ?>" onclick="return confirm('Hapus akun ini?')" style="color:#D6242A; font-size:12.5px;">Hapus</a>
      </div>
    <?php endforeach; endif; ?>

    <form method="post" class="add-admin-form">
      <input type="text" name="add_admin_nama" placeholder="Nama tampilan (mis. Budi)" required>
      <input type="text" name="add_admin_username" placeholder="Username baru" required>
      <input type="password" name="add_admin_password" placeholder="Password (min 8 karakter)" minlength="8" required>
      <button type="submit">+ Tambah Akun Admin</button>
    </form>
  <?php endif; ?>
</details>

<details class="card mgmt-card">
  <summary>🕒 Log Aktivitas &amp; Login</summary>
  <div style="font-weight:bold; margin:14px 0 4px; font-size:13px;">Aktivitas terakhir</div>
  <?php if (!$aktivitasList): ?>
    <p style="color:#4B5468; font-size:13px;">Belum ada aktivitas tercatat.</p>
  <?php else: foreach ($aktivitasList as $a): ?>
    <div class="log-row"><b><?= htmlspecialchars($a['username']) ?></b> — <?= htmlspecialchars($a['aksi']) ?> <span style="color:#4B5468;">(<?= htmlspecialchars($a['waktu']) ?>)</span></div>
  <?php endforeach; endif; ?>

  <div style="font-weight:bold; margin:14px 0 4px; font-size:13px;">Percobaan login terakhir</div>
  <?php if (!$loginLogList): ?>
    <p style="color:#4B5468; font-size:13px;">Belum ada catatan login.</p>
  <?php else: foreach ($loginLogList as $l): ?>
    <div class="log-row">
      <b><?= htmlspecialchars($l['username']) ?></b>
      — <span class="<?= $l['berhasil'] ? 'ok-text' : 'fail-text' ?>"><?= $l['berhasil'] ? 'berhasil' : 'gagal' ?></span>
      dari IP <?= htmlspecialchars($l['ip']) ?>
      <span style="color:#4B5468;">(<?= htmlspecialchars($l['waktu']) ?>)</span>
    </div>
  <?php endforeach; endif; ?>
</details>

<?php if (!empty($dbError)): ?>
  <div class="empty" style="color:#D6242A;"><?= htmlspecialchars($dbError) ?></div>
<?php endif; ?>

<?php if (!empty($listError)): ?>
  <div class="empty" style="color:#D6242A;"><?= htmlspecialchars($listError) ?></div>
<?php else: ?>
  <div class="bulk-bar">
    <?php if ($rows): ?>
      <label><input type="checkbox" id="selectAll"> Pilih semua</label>
      <button type="button" id="btnCetakTerpilih" disabled>🖨️ Cetak Terpilih (<span id="selCount">0</span>)</button>
    <?php endif; ?>
    <a href="export-csv.php" style="margin-left:auto; background:#fff; border:1.5px solid #DCE1F0; color:#0F1F52; padding:7px 12px; border-radius:7px; font-size:12.5px; font-weight:600; text-decoration:none;">📊 Export Data (CSV)</a>
  </div>
<?php endif; ?>

<?php if (empty($listError) && !$rows): ?>
  <div class="empty">Belum ada pendaftaran masuk.</div>
<?php elseif (empty($listError)): foreach ($rows as $r): ?>
  <details class="card">
    <summary>
      <input type="checkbox" class="pilih-cetak" value="<?= (int)$r['id'] ?>" onclick="event.stopPropagation()">
      <?= htmlspecialchars($r['nama_lengkap']) ?>
      <span class="status-pill st-<?= htmlspecialchars(str_replace(' ', '-', $r['status'])) ?>"><?= htmlspecialchars($r['status']) ?></span>
      <span class="status-pill mt-<?= ($r['materai_status'] ?? 'Belum') === 'Sudah' ? 'Sudah' : 'Belum' ?>">🖋 Materai: <?= ($r['materai_status'] ?? 'Belum') === 'Sudah' ? 'Sudah' : 'Belum' ?></span>
      <?php $bayar = $r['status_pembayaran'] ?? 'Belum Bayar'; ?>
      <span class="status-pill by-<?= htmlspecialchars(str_replace(' ', '-', $bayar)) ?>">💰 <?= htmlspecialchars($bayar) ?></span>
      <?php if (($r['jenis_pendaftar'] ?? 'Baru') === 'Pemain Lama'): ?>
        <span class="status-pill jp-lama">📋 Pemain Lama</span>
      <?php endif; ?>
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
      <?php if (($r['jenis_pendaftar'] ?? 'Baru') === 'Pemain Lama'): ?>
        <div>
          <b>Klaim Pembayaran Lama</b>
          <?= htmlspecialchars($r['klaim_lunas_lama'] ?? '-') ?>
        </div>
      <?php else: ?>
        <div>
          <b>Paket Pendaftaran</b>
          <?php
          $paket = $r['paket_pendaftaran'] ?? 'Lunas';
          $labelPaket = ['Lunas' => 'Lunas (Rp 2.500.000)', 'Binaan' => 'Binaan (Rp 500.000)', 'Kondisi Ekonomi' => 'Sesuai Kondisi Ekonomi'];
          echo htmlspecialchars($labelPaket[$paket] ?? $paket);
          if ($paket === 'Kondisi Ekonomi') {
              echo ' — Rp ' . number_format((int)($r['nominal_kondisi_ekonomi'] ?? 0), 0, ',', '.');
          }
          ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="files">
      <?php
      $fileLabels = [
        'file_akte_lahir' => 'Akte Lahir', 'file_ijazah_raport' => 'Ijazah/Raport',
        'file_kartu_keluarga' => 'Kartu Keluarga', 'file_raport_dalam' => 'Raport Dalam',
        'file_nisn' => 'NISN', 'file_kia' => 'KIA', 'file_pas_foto' => 'Pas Foto',
        'file_tanda_tangan' => 'Tanda Tangan', 'file_bukti_transfer' => 'Bukti Transfer',
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

    <form class="materai-form" method="post">
      <input type="hidden" name="toggle_materai_id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="materai_status_baru" value="<?= ($r['materai_status'] ?? 'Belum') === 'Sudah' ? 'Belum' : 'Sudah' ?>">
      <?php if (($r['materai_status'] ?? 'Belum') === 'Sudah'): ?>
        <button type="submit">↺ Tandai Materai Belum Ditempel</button>
      <?php else: ?>
        <button type="submit">🖋 Tandai Materai Sudah Ditempel</button>
      <?php endif; ?>
    </form>

    <form class="status-form" method="post" style="margin-top:8px;">
      <input type="hidden" name="update_bayar_id" value="<?= (int)$r['id'] ?>">
      <select name="status_pembayaran">
        <?php foreach (['Belum Bayar','Menunggu Konfirmasi','Lunas'] as $sb): ?>
          <option value="<?= $sb ?>" <?= ($r['status_pembayaran'] ?? 'Belum Bayar') === $sb ? 'selected' : '' ?>>💰 <?= $sb ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Simpan Status Bayar</button>
    </form>
  </details>
<?php endforeach; endif; ?>
</main>
<script>
(function(){
  var boxes = document.querySelectorAll('.pilih-cetak');
  var btn = document.getElementById('btnCetakTerpilih');
  var countEl = document.getElementById('selCount');
  var selectAll = document.getElementById('selectAll');

  function updateSelCount(){
    var n = document.querySelectorAll('.pilih-cetak:checked').length;
    if (countEl) countEl.textContent = n;
    if (btn) btn.disabled = n === 0;
  }
  boxes.forEach(function(cb){ cb.addEventListener('change', updateSelCount); });
  if (selectAll) selectAll.addEventListener('change', function(){
    boxes.forEach(function(cb){ cb.checked = selectAll.checked; });
    updateSelCount();
  });
  if (btn) btn.addEventListener('click', function(){
    var ids = Array.prototype.slice.call(document.querySelectorAll('.pilih-cetak:checked')).map(function(cb){ return cb.value; });
    if (!ids.length) return;
    window.open('cetak.php?ids=' + ids.join(',') + '&jenis=semua', '_blank');
  });
  updateSelCount();
})();
</script>
</body></html>
