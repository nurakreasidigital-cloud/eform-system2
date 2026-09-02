<?php
require_once '../config/database.php';
require_once '../config/auth.php';
requireAdmin();

// Ambil daftar surat masuk untuk dropdown
$suratMasuk = $pdo->query("SELECT id, nomor_surat, pengirim, perihal FROM surat_masuk ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $surat_masuk_id = (int)$_POST['surat_masuk_id'];
    $tujuan = trim($_POST['tujuan'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $batas_waktu = $_POST['batas_waktu'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    $file_path = '';

    if ($surat_masuk_id && $tujuan && $isi) {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = 'disposisi_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $target = '../uploads/surat/' . $filename;
                move_uploaded_file($_FILES['file']['tmp_name'], $target);
                $file_path = 'uploads/surat/' . $filename;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO disposisi (surat_masuk_id, tujuan_disposisi, isi_disposisi, batas_waktu, status, file_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$surat_masuk_id, $tujuan, $isi, $batas_waktu, $status, $file_path, $_SESSION['user_id']]);
        $message = '✅ Disposisi berhasil ditambahkan!';
        $messageType = 'success';
    } else {
        $message = '⚠️ Semua field harus diisi!';
        $messageType = 'error';
    }
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT file_path FROM disposisi WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data && $data['file_path'] && file_exists('../' . $data['file_path'])) {
        unlink('../' . $data['file_path']);
    }
    $stmt = $pdo->prepare("DELETE FROM disposisi WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: disposisi.php?msg=deleted');
    exit;
}

// Update status
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    $stmt = $pdo->prepare("UPDATE disposisi SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    header('Location: disposisi.php');
    exit;
}

$editData = null;
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM disposisi WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $surat_masuk_id = (int)$_POST['surat_masuk_id'];
    $tujuan = trim($_POST['tujuan'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $batas_waktu = $_POST['batas_waktu'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    $file_path = $_POST['existing_file'] ?? '';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if ($file_path && file_exists('../' . $file_path)) {
                unlink('../' . $file_path);
            }
            $filename = 'disposisi_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = '../uploads/surat/' . $filename;
            move_uploaded_file($_FILES['file']['tmp_name'], $target);
            $file_path = 'uploads/surat/' . $filename;
        }
    }

    if ($surat_masuk_id && $tujuan && $isi) {
        $stmt = $pdo->prepare("UPDATE disposisi SET surat_masuk_id = ?, tujuan_disposisi = ?, isi_disposisi = ?, batas_waktu = ?, status = ?, file_path = ? WHERE id = ?");
        $stmt->execute([$surat_masuk_id, $tujuan, $isi, $batas_waktu, $status, $file_path, $id]);
        $message = '✅ Disposisi berhasil diupdate!';
        $messageType = 'success';
        $editData = null;
    }
}

$disposisi = $pdo->query("
    SELECT d.*, s.nomor_surat, s.pengirim, s.perihal 
    FROM disposisi d 
    JOIN surat_masuk s ON d.surat_masuk_id = s.id 
    ORDER BY d.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disposisi - <?= APP_NAME ?></title>
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
        .btn-success { background: #2E7D32; color: #fff; }
        .btn-success:hover { background: #1B5E20; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 3px; color: #1A1A2E; font-size: 12px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 6px 10px; border: 1.5px solid #DDE3F0; border-radius: 6px; font-size: 13px; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { border-color: #E53935; outline: none; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .alert-success { background: #EEFAF4; color: #1A7A45; border: 1px solid #B6E8CF; }
        .alert-error { background: #FFE5E5; color: #C62828; border: 1px solid #FFB3B3; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .status-badge.pending { background: #FFFBEE; color: #B07A00; }
        .status-badge.proses { background: #EEF4FF; color: #2D5BE3; }
        .status-badge.selesai { background: #EEFAF4; color: #1A7A45; }
        .file-upload-wrapper { position: relative; overflow: hidden; display: inline-block; }
        .file-upload-wrapper input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
        .file-upload-label { display: inline-block; padding: 4px 12px; background: #EEF4FF; color: #2D5BE3; border-radius: 4px; font-size: 12px; cursor: pointer; border: 1px solid #C3D4F9; }
        .file-upload-label:hover { background: #DCE8FF; }
        .file-name { font-size: 11px; color: #6B7A99; margin-left: 8px; }
        .btn-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
        .filter-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
        .filter-badge { padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; text-decoration: none; border: 1px solid #DDE3F0; color: #6B7A99; }
        .filter-badge:hover { background: #F0F2F8; }
        .filter-badge.active { background: #E53935; color: #fff; border-color: #E53935; }
    </style>
</head>
<body>
<?php include "../sidebar.php"; ?>
    <div class="app">

        <main class="main">
            <div class="container">
                <header class="topbar">
                    <div class="topbar-left"><h2>📋 Disposisi</h2></div>
                    <div class="topbar-right">
                        <a href="index.php" class="btn btn-sm btn-secondary">← Kembali</a>
                    </div>
                </header>

                <?php if (isset($message)): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">✅ Disposisi berhasil dihapus!</div>
                <?php endif; ?>

                <!-- FORM -->
                <div class="card">
                    <div class="card-header"><h3><?= $editData ? '✏️ Edit Disposisi' : '📋 Tambah Disposisi' ?></h3></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php if ($editData): ?>
                            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                            <input type="hidden" name="existing_file" value="<?= $editData['file_path'] ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label>Surat Masuk <span style="color:#E53935;">*</span></label>
                                <select name="surat_masuk_id" required>
                                    <option value="">-- Pilih Surat Masuk --</option>
                                    <?php foreach ($suratMasuk as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= ($editData && $editData['surat_masuk_id'] == $s['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['nomor_surat']) ?> - <?= htmlspecialchars($s['pengirim']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Tujuan Disposisi <span style="color:#E53935;">*</span></label><input type="text" name="tujuan" value="<?= $editData['tujuan_disposisi'] ?? '' ?>" required></div>
                            <div class="form-group"><label>Isi Disposisi <span style="color:#E53935;">*</span></label><textarea name="isi" rows="3" required><?= $editData['isi_disposisi'] ?? '' ?></textarea></div>
                            <div class="grid-2">
                                <div class="form-group"><label>Batas Waktu</label><input type="date" name="batas_waktu" value="<?= $editData['batas_waktu'] ?? '' ?>"></div>
                                <div class="form-group"><label>Status</label>
                                    <select name="status">
                                        <option value="pending" <?= ($editData && $editData['status'] === 'pending') ? 'selected' : '' ?>>⏳ Pending</option>
                                        <option value="proses" <?= ($editData && $editData['status'] === 'proses') ? 'selected' : '' ?>>🔄 Proses</option>
                                        <option value="selesai" <?= ($editData && $editData['status'] === 'selesai') ? 'selected' : '' ?>>✅ Selesai</option>
                                    </select>
                                </div>
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
                            <button type="submit" name="<?= $editData ? 'update' : 'tambah' ?>" class="btn btn-primary"><?= $editData ? '💾 Update' : '📋 Simpan' ?></button>
                            <?php if ($editData): ?>
                            <a href="disposisi.php" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- DAFTAR -->
                <div class="card">
                    <div class="card-header"><h3>📋 Daftar Disposisi</h3></div>
                    <div class="card-body">
                        <div class="filter-badges">
                            <a href="disposisi.php" class="filter-badge <?= !isset($_GET['filter']) ? 'active' : '' ?>">📋 Semua</a>
                            <a href="?filter=pending" class="filter-badge <?= (isset($_GET['filter']) && $_GET['filter'] === 'pending') ? 'active' : '' ?>">⏳ Pending</a>
                            <a href="?filter=proses" class="filter-badge <?= (isset($_GET['filter']) && $_GET['filter'] === 'proses') ? 'active' : '' ?>">🔄 Proses</a>
                            <a href="?filter=selesai" class="filter-badge <?= (isset($_GET['filter']) && $_GET['filter'] === 'selesai') ? 'active' : '' ?>">✅ Selesai</a>
                        </div>
                        <?php if (empty($disposisi)): ?>
                        <p style="text-align:center;color:#6B7A99;padding:20px;">Belum ada disposisi</p>
                        <?php else: ?>
                        <table class="table">
                            <thead><tr><th>No</th><th>Surat</th><th>Tujuan</th><th>Isi</th><th>Status</th><th>File</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php $no=1; foreach ($disposisi as $d): 
                                $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
                                if ($filter && $d['status'] !== $filter) continue;
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($d['nomor_surat']) ?></strong><br><small><?= htmlspecialchars($d['pengirim']) ?></small></td>
                                <td><?= htmlspecialchars($d['tujuan_disposisi']) ?></td>
                                <td><?= htmlspecialchars(substr($d['isi_disposisi'], 0, 50)) ?>...</td>
                                <td><span class="status-badge <?= $d['status'] ?>"><?= ['pending'=>'⏳ Pending','proses'=>'🔄 Proses','selesai'=>'✅ Selesai'][$d['status']] ?? $d['status'] ?></span></td>
                                <td>
                                    <?php if ($d['file_path']): ?>
                                    <a href="../<?= $d['file_path'] ?>" target="_blank" class="btn-sm btn-primary">📄</a>
                                    <?php else: ?>
                                    <span style="color:#6B7A99;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?status=pending&id=<?= $d['id'] ?>" class="btn-sm btn-warning">⏳</a>
                                    <a href="?status=proses&id=<?= $d['id'] ?>" class="btn-sm btn-primary">🔄</a>
                                    <a href="?status=selesai&id=<?= $d['id'] ?>" class="btn-sm btn-success">✅</a>
                                    <a href="?edit=1&id=<?= $d['id'] ?>" class="btn-sm btn-warning">✏️</a>
                                    <a href="?delete=1&id=<?= $d['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('Hapus disposisi ini?')">🗑</a>
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
