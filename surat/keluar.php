<?php
require_once '../config/database.php';
require_once '../config/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nomor = trim($_POST['nomor_surat'] ?? '');
    $tujuan = trim($_POST['tujuan'] ?? '');
    $perihal = trim($_POST['perihal'] ?? '');
    $tgl_surat = $_POST['tgl_surat'] ?? '';
    $tgl_kirim = $_POST['tgl_kirim'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');
    $file_path = '';

    if ($nomor && $tujuan && $perihal && $tgl_surat && $tgl_kirim) {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = 'keluar_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $target = '../uploads/surat/' . $filename;
                move_uploaded_file($_FILES['file']['tmp_name'], $target);
                $file_path = 'uploads/surat/' . $filename;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO surat_keluar (nomor_surat, tujuan, perihal, tanggal_surat, tanggal_kirim, file_path, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nomor, $tujuan, $perihal, $tgl_surat, $tgl_kirim, $file_path, $keterangan, $_SESSION['user_id']]);
        $message = '✅ Surat keluar berhasil ditambahkan!';
        $messageType = 'success';
    } else {
        $message = '⚠️ Semua field harus diisi!';
        $messageType = 'error';
    }
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT file_path FROM surat_keluar WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data && $data['file_path'] && file_exists('../' . $data['file_path'])) {
        unlink('../' . $data['file_path']);
    }
    $stmt = $pdo->prepare("DELETE FROM surat_keluar WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: keluar.php?msg=deleted');
    exit;
}

$editData = null;
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM surat_keluar WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $nomor = trim($_POST['nomor_surat'] ?? '');
    $tujuan = trim($_POST['tujuan'] ?? '');
    $perihal = trim($_POST['perihal'] ?? '');
    $tgl_surat = $_POST['tgl_surat'] ?? '';
    $tgl_kirim = $_POST['tgl_kirim'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');
    $file_path = $_POST['existing_file'] ?? '';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if ($file_path && file_exists('../' . $file_path)) {
                unlink('../' . $file_path);
            }
            $filename = 'keluar_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = '../uploads/surat/' . $filename;
            move_uploaded_file($_FILES['file']['tmp_name'], $target);
            $file_path = 'uploads/surat/' . $filename;
        }
    }

    if ($nomor && $tujuan && $perihal && $tgl_surat && $tgl_kirim) {
        $stmt = $pdo->prepare("UPDATE surat_keluar SET nomor_surat = ?, tujuan = ?, perihal = ?, tanggal_surat = ?, tanggal_kirim = ?, file_path = ?, keterangan = ? WHERE id = ?");
        $stmt->execute([$nomor, $tujuan, $perihal, $tgl_surat, $tgl_kirim, $file_path, $keterangan, $id]);
        $message = '✅ Surat keluar berhasil diupdate!';
        $messageType = 'success';
        $editData = null;
    }
}

$surat = $pdo->query("SELECT * FROM surat_keluar ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Keluar - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; }
        .table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .table th { text-align: left; padding: 6px 8px; background: #F8F9FC; color: #6B7A99; font-weight: 700; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #DDE3F0; }
        .table td { padding: 6px 8px; border-bottom: 1px solid #EEF0F6; vertical-align: middle; }
        .table tr:hover { background: #FAFBFD; }
        .btn-sm { padding: 2px 8px; font-size: 10px; border-radius: 4px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-warning { background: #F5A623; color: #fff; }
        .btn-warning:hover { background: #d4911a; }
        .btn-danger { background: #C62828; color: #fff; }
        .btn-danger:hover { background: #8E0000; }
        .btn-primary { background: #E53935; color: #fff; }
        .btn-primary:hover { background: #c62828; }
        .btn-secondary { background: #6B7A99; color: #fff; }
        .btn-secondary:hover { background: #5a6a89; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 3px; color: #1A1A2E; font-size: 12px; }
        .form-group input, .form-group textarea { width: 100%; padding: 6px 10px; border: 1.5px solid #DDE3F0; border-radius: 6px; font-size: 13px; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { border-color: #E53935; outline: none; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .alert-success { background: #EEFAF4; color: #1A7A45; border: 1px solid #B6E8CF; }
        .alert-error { background: #FFE5E5; color: #C62828; border: 1px solid #FFB3B3; }
        .file-upload-wrapper { position: relative; overflow: hidden; display: inline-block; }
        .file-upload-wrapper input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
        .file-upload-label { display: inline-block; padding: 4px 12px; background: #EEF4FF; color: #2D5BE3; border-radius: 4px; font-size: 12px; cursor: pointer; border: 1px solid #C3D4F9; }
        .file-upload-label:hover { background: #DCE8FF; }
        .file-name { font-size: 11px; color: #6B7A99; margin-left: 8px; }
        .btn-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
    </style>
</head>
<body>
<?php include "../sidebar.php"; ?>
    <div class="app">

        <main class="main">
            <div class="container">
                <header class="topbar">
                    <div class="topbar-left"><h2>📤 Surat Keluar</h2></div>
                    <div class="topbar-right">
                        <a href="index.php" class="btn btn-sm btn-secondary">← Kembali</a>
                    </div>
                </header>

                <?php if (isset($message)): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">✅ Surat berhasil dihapus!</div>
                <?php endif; ?>

                <!-- FORM -->
                <div class="card">
                    <div class="card-header"><h3><?= $editData ? '✏️ Edit Surat Keluar' : '📤 Tambah Surat Keluar' ?></h3></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php if ($editData): ?>
                            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                            <input type="hidden" name="existing_file" value="<?= $editData['file_path'] ?>">
                            <?php endif; ?>
                            <div class="grid-2">
                                <div class="form-group"><label>Nomor Surat <span style="color:#E53935;">*</span></label><input type="text" name="nomor_surat" value="<?= $editData['nomor_surat'] ?? '' ?>" required></div>
                                <div class="form-group"><label>Tujuan <span style="color:#E53935;">*</span></label><input type="text" name="tujuan" value="<?= $editData['tujuan'] ?? '' ?>" required></div>
                            </div>
                            <div class="form-group"><label>Perihal <span style="color:#E53935;">*</span></label><input type="text" name="perihal" value="<?= $editData['perihal'] ?? '' ?>" required></div>
                            <div class="grid-2">
                                <div class="form-group"><label>Tanggal Surat <span style="color:#E53935;">*</span></label><input type="date" name="tgl_surat" value="<?= $editData['tanggal_surat'] ?? '' ?>" required></div>
                                <div class="form-group"><label>Tanggal Kirim <span style="color:#E53935;">*</span></label><input type="date" name="tgl_kirim" value="<?= $editData['tanggal_kirim'] ?? '' ?>" required></div>
                            </div>
                            <div class="form-group">
                                <label>📎 Lampiran File</label>
                                <div class="file-upload-wrapper">
                                    <span class="file-upload-label">📤 Pilih File</span>
                                    <input type="file" name="file" onchange="updateFileName(this)">
                                </div>
                                <span class="file-name" id="fileName"><?= $editData && $editData['file_path'] ? '📎 ' . basename($editData['file_path']) : 'Tidak ada file' ?></span>
                                <div style="font-size:10px;color:#6B7A99;margin-top:2px;">📌 PDF, DOC, JPG, PNG, ZIP | Max 5MB</div>
                            </div>
                            <div class="form-group"><label>Keterangan</label><textarea name="keterangan" rows="2"><?= $editData['keterangan'] ?? '' ?></textarea></div>
                            <button type="submit" name="<?= $editData ? 'update' : 'tambah' ?>" class="btn btn-primary"><?= $editData ? '💾 Update' : '📤 Simpan' ?></button>
                            <?php if ($editData): ?>
                            <a href="keluar.php" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- DAFTAR -->
                <div class="card">
                    <div class="card-header"><h3>📋 Daftar Surat Keluar</h3></div>
                    <div class="card-body">
                        <?php if (empty($surat)): ?>
                        <p style="text-align:center;color:#6B7A99;padding:20px;">Belum ada surat keluar</p>
                        <?php else: ?>
                        <table class="table">
                            <thead><tr><th>No</th><th>Nomor</th><th>Tujuan</th><th>Perihal</th><th>Tanggal</th><th>File</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php $no=1; foreach ($surat as $s): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($s['nomor_surat']) ?></strong></td>
                                <td><?= htmlspecialchars($s['tujuan']) ?></td>
                                <td><?= htmlspecialchars($s['perihal']) ?></td>
                                <td><?= date('d/m/Y', strtotime($s['tanggal_surat'])) ?></td>
                                <td>
                                    <?php if ($s['file_path']): ?>
                                    <a href="../<?= $s['file_path'] ?>" target="_blank" class="btn-sm btn-primary">📄</a>
                                    <?php else: ?>
                                    <span style="color:#6B7A99;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=1&id=<?= $s['id'] ?>" class="btn-sm btn-warning">✏️</a>
                                    <a href="?delete=1&id=<?= $s['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('Hapus surat ini?')">🗑</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
    function updateFileName(input) {
        var name = document.getElementById('fileName');
        if (input.files && input.files.length > 0) {
            name.textContent = '📎 ' + input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
        } else {
            name.textContent = 'Tidak ada file';
        }
    }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
