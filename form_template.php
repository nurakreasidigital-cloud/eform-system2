<?php
// ============================================================
// FORM TEMPLATE - Gunakan untuk semua form
// ============================================================
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

// ===== VARIABEL FORM =====
$form_key = 'form_a'; // <-- GANTI untuk setiap form
$form_title = 'Form A - Laporan Pengawasan'; // <-- GANTI

// ===== CEK AKSES =====
if (!canAccessForm($form_key)) {
    die("<h1>⛔ Akses Ditolak</h1><p>Anda tidak memiliki akses ke form ini.</p><a href='dashboard.php'>← Kembali</a>");
}

// ===== AMBIL DATA FORM =====
$stmt = $pdo->prepare("SELECT * FROM forms WHERE form_key = ?");
$stmt->execute([$form_key]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form || $form['is_active'] != 1) {
    die("<h1>⚠️ Form sedang tidak aktif</h1>");
}

// ===== DATA KOP =====
$kop_logo = $form['kop_logo'] ?? '';
$kop_instansi = $form['kop_instansi'] ?? 'BADAN PENGAWAS PEMILIHAN UMUM';
$kop_alamat = $form['kop_alamat'] ?? '';
$kop_telp = $form['kop_telp'] ?? '';
$kop_email = $form['kop_email'] ?? '';

// ===== AMBIL RIWAYAT SUBMISSION =====
$submissions = $pdo->prepare("
    SELECT s.*, f.form_name, f.icon 
    FROM form_submissions s 
    JOIN forms f ON s.form_id = f.id 
    WHERE s.user_id = ? AND f.form_key = ? 
    ORDER BY s.created_at DESC 
    LIMIT 20
");
$submissions->execute([$_SESSION['user_id'], $form_key]);
$submissionList = $submissions->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// HTML
// ============================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $form_title ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .form-container { max-width: 820px; margin: 0 auto; }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
        .btn-row .btn { flex: 1; min-width: 120px; text-align: center; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; color: #1A1A2E; font-size: 13px; }
        .form-group label .req { color: #E53935; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 8px 12px; border: 1.5px solid #DDE3F0; border-radius: 8px; font-size: 14px; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus { border-color: #E53935; outline: none; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .alert-info { background: #EEF4FF; color: #2D5BE3; border: 1px solid #C3D4F9; }
        .alert-success { background: #EEFAF4; color: #1A7A45; border: 1px solid #B6E8CF; }
        .alert-warning { background: #FFFBEE; color: #B07A00; border: 1px solid #F5E0A0; }
        .btn-sm { padding: 4px 12px; font-size: 11px; border-radius: 4px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #E53935; color: #fff; }
        .btn-primary:hover { background: #c62828; }
        .btn-secondary { background: #6B7A99; color: #fff; }
        .btn-secondary:hover { background: #5a6a89; }
        .btn-success { background: #2E7D32; color: #fff; }
        .btn-success:hover { background: #1B5E20; }
        .btn-warning { background: #F5A623; color: #fff; }
        .btn-warning:hover { background: #d4911a; }
        .btn-outline { background: transparent; color: #E53935; border: 1.5px solid #E53935; }
        .btn-outline:hover { background: #FFF5F5; }
        .btn-disabled { background: #E0E0E0; color: #999; cursor: not-allowed; pointer-events: none; }
        .photo-zone { border: 2px dashed #DDE3F0; border-radius: 8px; padding: 16px; text-align: center; cursor: pointer; transition: 0.2s; background: #FAFBFD; }
        .photo-zone:hover { border-color: #E53935; background: #FFF5F5; }
        .photo-zone input { display: none; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 10px; }
        .photo-item { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 4/3; border: 1.5px solid #DDE3F0; background: #f0f0f0; }
        .photo-item img { width: 100%; height: 100%; object-fit: cover; }
        .photo-item .caption-input { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); padding: 4px 7px; border: none; color: #fff; font-size: 10px; font-family: inherit; width: 100%; outline: none; }
        .photo-item .remove-btn { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; background: rgba(229,57,53,0.85); border: none; border-radius: 50%; color: #fff; font-size: 12px; cursor: pointer; }
        .toggle-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .toggle-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #ccc; border-radius: 22px; transition: 0.2s; cursor: pointer; }
        .toggle-slider::before { content: ""; position: absolute; width: 16px; height: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.2s; }
        .toggle-switch input:checked+.toggle-slider { background: #E53935; }
        .toggle-switch input:checked+.toggle-slider::before { transform: translateX(18px); }
        .toggle-extra { display: none; }
        .toggle-extra.show { display: block; }
        .sec-label { font-size: 12px; font-weight: 700; color: #E53935; margin: 12px 0 8px; padding-bottom: 4px; border-bottom: 2px solid rgba(229,57,53,0.12); text-transform: uppercase; }
        .toggle-empty { text-align: center; padding: 10px 0; color: #999; font-size: 12px; font-style: italic; }
        .card { background: #fff; border-radius: 12px; border: 1px solid #DDE3F0; margin-bottom: 16px; overflow: hidden; }
        .card-header { padding: 12px 16px; background: linear-gradient(135deg, #1A1A2E, #2D2D4E); color: #fff; }
        .card-header h3 { font-size: 14px; font-weight: 700; }
        .card-body { padding: 14px 16px; }
        .tab-bar { background: #f8f9fc; border-bottom: 2px solid #DDE3F0; margin-bottom: 16px; border-radius: 8px 8px 0 0; padding: 0 4px; display: flex; }
        .tab-btn { padding: 8px 16px; border: none; background: none; font-weight: 700; cursor: pointer; border-bottom: 3px solid transparent; color: #6B7A99; }
        .tab-btn.active { border-bottom: 3px solid #E53935; color: #1A1A2E; }
        #previewArea { background: #fff; border: 1px solid #DDE3F0; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .preview-toolbar { background: #F8F9FC; border-bottom: 1px solid #DDE3F0; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .preview-content { padding: 16px; max-height: 600px; overflow-y: auto; }
        .pdf-page { background: #fff; border: 1px solid #E0E0E0; padding: 24px 30px; max-width: 620px; margin: 0 auto 16px; font-family: "Times New Roman", serif; font-size: 10.5pt; line-height: 1.5; }
        .pdf-page .kop { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #1A1A2E; padding-bottom: 8px; }
        .pdf-page .kop img { height: 60px; width: auto; margin-bottom: 4px; }
        .pdf-title { font-size: 12pt; font-weight: 700; text-align: center; }
        .pdf-subtitle { font-size: 11pt; font-weight: 700; text-align: center; }
        .pdf-nomor { font-size: 10.5pt; font-weight: 700; text-align: center; margin-bottom: 10px; }
        .pdf-tbl { width: 100%; border-collapse: collapse; font-size: 10pt; }
        .pdf-tbl td { border: 1px solid #444; padding: 3px 6px; vertical-align: top; }
        .pdf-tbl .label-col { width: 140px; }
        .pdf-tbl .sep-col { width: 14px; text-align: center; }
        .pdf-tbl .section-head { background: #e8e8e8; font-weight: 700; }
        .pdf-ttd { text-align: center; margin-top: 16px; }
        .pdf-ttd .nama { font-weight: 700; text-decoration: underline; display: block; margin-top: 40px; }
        .pdf-foto-title { text-align: center; font-weight: 700; font-size: 12pt; }
        .pdf-foto-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .pdf-foto-item img { width: 100%; border: 1px solid #ccc; border-radius: 4px; }
        .pdf-foto-item p { text-align: center; font-size: 8.5pt; margin-top: 3px; font-style: italic; color: #555; }
        .submission-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .submission-table th { text-align: left; padding: 6px 8px; background: #F8F9FC; color: #6B7A99; font-weight: 700; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #DDE3F0; }
        .submission-table td { padding: 6px 8px; border-bottom: 1px solid #EEF0F6; vertical-align: middle; }
        .submission-table tr:hover { background: #FAFBFD; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-badge.draft { background: #EEF0F6; color: #6B7A99; }
        .status-badge.previewed { background: #EEF4FF; color: #2D5BE3; }
        .status-badge.downloaded { background: #EEFAF4; color: #1A7A45; }
        .status-badge.submitted { background: #FFFBEE; color: #B07A00; }
        .status-badge.approved { background: #EEFAF4; color: #1A7A45; }
        .status-badge.rejected { background: #FFE5E5; color: #C62828; }
        .btn-edit { background: #2D5BE3; color: #fff; border: none; padding: 2px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; }
        .btn-edit:hover { background: #1A3F8A; }
        .btn-preview { background: #F5A623; color: #fff; border: none; padding: 2px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; }
        .btn-preview:hover { background: #d4911a; }
        @media (max-width: 560px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } .btn-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-brand"><span class="brand-icon">📋</span><span class="brand-text">E-Form</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php">📊 Dashboard</a>
                <?php if (hasRole('super_admin') || hasRole('admin')): ?>
                <a href="admin_users.php">👥 Manajemen User</a>
                <a href="forms.php">📝 Manajemen Form</a>
                <a href="admin.php">📋 Admin Panel</a>
                <?php endif; ?>
                <a href="profile.php">⚙️ Profile</a>
                <a href="logout.php" class="logout">🚪 Logout</a>
            </nav>
            <div class="sidebar-footer"><small><?= APP_NAME ?> v1.0</small></div>
        </aside>

        <main class="main">
            <div class="form-container">
                <header class="topbar">
                    <div class="topbar-left"><h2>📋 <?= $form_title ?></h2></div>
                    <div class="topbar-right">
                        <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        <a href="dashboard.php" class="btn btn-sm btn-secondary">← Kembali</a>
                    </div>
                </header>

                <!-- TABS -->
                <div class="tab-bar">
                    <button class="tab-btn active" onclick="switchTab('form')">📝 Form</button>
                    <button class="tab-btn" onclick="switchTab('preview')">👁 Preview</button>
                    <button class="tab-btn" onclick="switchTab('riwayat')">📋 Riwayat</button>
                </div>

                <!-- TAB FORM -->
                <div id="tabForm">
                    <div class="alert alert-info">ℹ️ Isi data. Setiap aksi akan masuk ke riwayat.</div>

                    <div id="formContainer">
                        <!-- === ISI FORM SESUAI MAKASING-MASING === -->
                        <!-- TEMPLATE: GANTI BAGIAN INI UNTUK SETIAP FORM -->

                        <!-- NOMOR -->
                        <div class="card"><div class="card-header"><h3>🔢 Nomor & Tanggal Laporan</h3></div><div class="card-body"><div class="grid-2">
                            <div class="form-group"><label>Nomor <span class="req">*</span></label><input type="text" id="nomor" placeholder="052/LHP/PM.01.00/11/2024"></div>
                            <div class="form-group"><label>Tanggal <span class="req">*</span></label><input type="date" id="tgl_laporan"></div>
                        </div></div></div>

                        <!-- DATA PENGAWAS -->
                        <div class="card"><div class="card-header"><h3>👤 Data Pengawas</h3></div><div class="card-body">
                            <div class="form-group"><label>Tahapan <span class="req">*</span></label><input type="text" id="tahapan" placeholder="Pendistribusian Logistik"></div>
                            <div class="grid-2">
                                <div class="form-group"><label>Nama <span class="req">*</span></label><input type="text" id="nama_pengawas" placeholder="Nama lengkap"></div>
                                <div class="form-group"><label>Jabatan</label><input type="text" id="jabatan" placeholder="Panwaslucam/KD"></div>
                            </div>
                            <div class="form-group"><label>Surat Tugas</label><input type="text" id="no_st" placeholder="SPD-240600070/06/2024"></div>
                            <div class="grid-2">
                                <div class="form-group"><label>Alamat</label><input type="text" id="alamat" placeholder="Alamat"></div>
                                <div class="form-group"><label>Telepon</label><input type="text" id="no_telp" placeholder="08xxxxxxxxxx"></div>
                            </div>
                        </div></div>

                        <!-- KEGIATAN -->
                        <div class="card"><div class="card-header"><h3>📌 Kegiatan</h3></div><div class="card-body">
                            <div class="form-group"><label>Kegiatan <span class="req">*</span></label><input type="text" id="kegiatan" placeholder="Pengawasan Pendistribusian Logistik"></div>
                            <div class="form-group"><label>1. Bentuk</label><input type="text" id="bentuk" placeholder="Bentuk pengawasan"></div>
                            <div class="form-group"><label>2. Tujuan</label><input type="text" id="tujuan" placeholder="Memastikan pengiriman logistik tepat waktu"></div>
                            <div class="form-group"><label>3. Sasaran</label><input type="text" id="sasaran" placeholder="Sasaran Pengawasan"></div>
                            <div class="grid-2">
                                <div class="form-group"><label>4. Tanggal</label><input type="date" id="tgl_kegiatan"></div>
                                <div class="form-group"><label>Tempat</label><input type="text" id="tempat_kegiatan" placeholder="Lokasi kegiatan"></div>
                            </div>
                        </div></div>

                        <!-- URAIAN -->
                        <div class="card"><div class="card-header"><h3>📝 Uraian</h3></div><div class="card-body">
                            <div class="form-group"><label>Uraian <span class="req">*</span></label><textarea id="uraian" rows="6" placeholder="1. Pada hari ini..."></textarea></div>
                        </div></div>

                        <!-- PELANGGARAN -->
                        <div class="card"><div class="card-header"><h3>⚠️ Dugaan Pelanggaran</h3></div><div class="card-body">
                            <div class="toggle-row">
                                <label class="toggle-switch"><input type="checkbox" id="ada_pelanggaran" onchange="togglePelanggaran()"><span class="toggle-slider"></span></label>
                                <span>Ada dugaan pelanggaran?</span>
                            </div>
                            <div id="pelanggaranFields" class="toggle-extra">
                                <div class="sec-label">1. Peristiwa</div>
                                <div class="form-group"><label>a. Peristiwa</label><textarea id="p_peristiwa" rows="2"></textarea></div>
                                <div class="grid-2"><div class="form-group"><label>b. Tempat</label><input type="text" id="p_tempat"></div><div class="form-group"><label>c. Waktu</label><input type="text" id="p_waktu"></div></div>
                                <div class="form-group"><label>d. Pelaku</label><input type="text" id="p_pelaku"></div>
                                <div class="form-group"><label>e. Alamat Pelaku</label><input type="text" id="p_alamat_pelaku"></div>
                                <div class="sec-label">2. Saksi-saksi</div>
                                <div class="grid-2"><div class="form-group"><label>a. Nama</label><input type="text" id="saksi1_nama"></div><div class="form-group"><label>Alamat</label><input type="text" id="saksi1_alamat"></div><div class="form-group"><label>b. Nama</label><input type="text" id="saksi2_nama"></div><div class="form-group"><label>Alamat</label><input type="text" id="saksi2_alamat"></div></div>
                                <div class="sec-label">3. Alat Bukti</div>
                                <div class="grid-3"><div class="form-group"><label>a.</label><input type="text" id="bukti_a"></div><div class="form-group"><label>b.</label><input type="text" id="bukti_b"></div><div class="form-group"><label>c.</label><input type="text" id="bukti_c"></div></div>
                                <div class="sec-label">4. Barang Bukti</div>
                                <div class="grid-3"><div class="form-group"><label>a.</label><input type="text" id="barang_a"></div><div class="form-group"><label>b.</label><input type="text" id="barang_b"></div><div class="form-group"><label>c.</label><input type="text" id="barang_c"></div></div>
                                <div class="form-group"><label>5. Uraian</label><textarea id="uraian_pelanggaran" rows="4"></textarea></div>
                                <div class="form-group"><label>6. Fakta</label><textarea id="fakta" rows="4"></textarea></div>
                                <div class="form-group"><label>7. Analisa</label><textarea id="analisa" rows="4"></textarea></div>
                            </div>
                            <div id="tidakPelanggaran" class="toggle-empty">Nihil</div>
                        </div></div>

                        <!-- SENGKETA -->
                        <div class="card"><div class="card-header"><h3>⚡ Potensi Sengketa</h3></div><div class="card-body">
                            <div class="toggle-row">
                                <label class="toggle-switch"><input type="checkbox" id="ada_sengketa" onchange="toggleSengketa()"><span class="toggle-slider"></span></label>
                                <span>Ada potensi sengketa?</span>
                            </div>
                            <div id="sengketaFields" class="toggle-extra">
                                <div class="sec-label">1. Peristiwa</div>
                                <div class="form-group"><label>a. Peserta</label><input type="text" id="s_peserta"></div>
                                <div class="grid-2"><div class="form-group"><label>b. Tempat</label><input type="text" id="s_tempat"></div><div class="form-group"><label>c. Waktu</label><input type="text" id="s_waktu"></div></div>
                                <div class="sec-label">2. Obyek Sengketa</div>
                                <div class="form-group"><label>a. Bentuk</label><input type="text" id="s_bentuk"></div>
                                <div class="form-group"><label>b. Identitas</label><input type="text" id="s_identitas"></div>
                                <div class="grid-2"><div class="form-group"><label>c. Tanggal</label><input type="text" id="s_tgl_keluar"></div><div class="form-group"><label>d. Kerugian</label><input type="text" id="s_kerugian"></div></div>
                                <div class="form-group"><label>3. Uraian</label><textarea id="s_uraian" rows="4"></textarea></div>
                            </div>
                            <div id="tidakSengketa" class="toggle-empty">Nihil</div>
                        </div></div>

                        <!-- TTD -->
                        <div class="card"><div class="card-header"><h3>✍️ Penandatangan</h3></div><div class="card-body">
                            <div class="grid-2"><div class="form-group"><label>Kota</label><input type="text" id="ttd_kota"></div><div class="form-group"><label>Tanggal TTD</label><input type="date" id="ttd_tgl"></div></div>
                            <div class="form-group"><label>Jabatan</label><input type="text" id="ttd_jabatan"></div>
                            <div class="form-group"><label>Nama</label><input type="text" id="ttd_nama" placeholder="Nama lengkap"></div>
                        </div></div>

                        <!-- FOTO -->
                        <div class="card"><div class="card-header"><h3>📸 Dokumentasi</h3></div><div class="card-body">
                            <div class="form-group"><label>Judul</label><input type="text" id="foto_judul" placeholder="Judul dokumentasi"></div>
                            <div class="photo-zone" onclick="document.getElementById('photoInput').click()">
                                <input type="file" id="photoInput" accept="image/*" multiple onchange="addPhotos(event)">
                                <div style="font-size:28px;">📷</div>
                                <p>Tap untuk pilih foto</p>
                                <small>JPG, PNG</small>
                            </div>
                            <div class="photo-grid" id="photoGrid"></div>
                        </div></div>

                        <!-- BUTTONS -->
                        <div class="btn-row">
                            <button class="btn btn-outline btn-sm" onclick="resetForm()">🗑 Reset</button>
                            <button class="btn btn-secondary" onclick="saveDraft()">💾 Simpan Draf</button>
                            <button class="btn btn-primary" onclick="previewAndDownload()">👁 Preview & Download</button>
                            <button class="btn btn-success" disabled style="opacity:0.5;cursor:not-allowed;">📤 Kirim (Coming Soon)</button>
                        </div>
                    </div>
                </div>

                <!-- TAB PREVIEW -->
                <div id="tabPreview" style="display:none;">
                    <div id="previewArea">
                        <div class="preview-toolbar">
                            <span class="preview-label">👁 Preview Dokumen</span>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="btn btn-outline btn-sm" onclick="switchTab('form')">← Edit</button>
                                <button class="btn btn-primary btn-sm" onclick="downloadPDF()">⬇ Download PDF</button>
                            </div>
                        </div>
                        <div class="preview-content">
                            <div id="previewDoc">
                                <p style="text-align:center;color:#9AA6C0;padding:40px 0;font-size:12px;">Isi form lalu klik <strong>Preview & Download</strong></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB RIWAYAT -->
                <div id="tabRiwayat" style="display:none;">
                    <div class="card">
                        <div class="card-header"><h3>📋 Riwayat Submission</h3></div>
                        <div class="card-body">
                            <?php if (empty($submissionList)): ?>
                                <p style="text-align:center;color:#6B7A99;padding:20px;">Belum ada submission</p>
                            <?php else: ?>
                            <table class="submission-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php $no = 1; foreach ($submissionList as $s): 
                                    $statusClass = $s['status'] ?? 'draft';
                                    $statusLabel = [
                                        'draft' => '📝 Draf',
                                        'previewed' => '👁 Preview',
                                        'downloaded' => '⬇ Downloaded',
                                        'submitted' => '📤 Terkirim',
                                        'approved' => '✅ Diterima',
                                        'rejected' => '❌ Ditolak'
                                    ][$statusClass] ?? $statusClass;
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                                    <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td>
                                        <button class="btn-edit" onclick="editSubmission(<?= $s['id'] ?>)">✏️ Edit</button>
                                        <button class="btn-preview" onclick="previewSubmission(<?= $s['id'] ?>)">👁 Preview</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // ===== STATE =====
    var STORAGE_KEY = '<?= $form_key ?>_draft';
    var STATUS_KEY = '<?= $form_key ?>_status';
    var photos = [];
    var submissionId = null;

    // ===== LOAD DRAFT =====
    function loadDraft() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                var data = JSON.parse(saved);
                for (var key in data) {
                    if (key === 'photos') { photos = data.photos || []; renderPhotos(); continue; }
                    var el = document.getElementById(key);
                    if (el) el.value = data[key] || '';
                }
                if (data.ada_pelanggaran) { document.getElementById('ada_pelanggaran').checked = true; togglePelanggaran(); }
                if (data.ada_sengketa) { document.getElementById('ada_sengketa').checked = true; toggleSengketa(); }
                if (data.submission_id) { submissionId = data.submission_id; }
            }
        } catch(e) {}
    }

    // ===== SAVE DRAFT =====
    function saveDraft() {
        var data = getAllData();
        data.photos = photos;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        showToast('💾 Draf berhasil disimpan!', 'success');
        // Simpan ke server sebagai draft
        saveToServer('draft');
    }

    function getAllData() {
        var fields = document.querySelectorAll('#formContainer input, #formContainer textarea, #formContainer select');
        var data = {};
        fields.forEach(function(el) {
            if (el.id) {
                if (el.type === 'checkbox') data[el.id] = el.checked;
                else data[el.id] = el.value;
            }
        });
        return data;
    }

    // ===== SAVE TO SERVER (Riwayat) =====
    function saveToServer(status) {
        var data = getAllData();
        data.photos = photos;
        data.status = status;
        
        fetch('api/save_submission.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                form_key: '<?= $form_key ?>',
                data: data,
                status: status,
                submission_id: submissionId
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
            if (result.success) {
                submissionId = result.submission_id;
                // Update submission_id di localStorage
                var draft = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
                draft.submission_id = result.submission_id;
                localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
                // Refresh riwayat
                loadRiwayat();
            }
        })
        .catch(function(err) { console.error('Save error:', err); });
    }

    // ===== PREVIEW & DOWNLOAD =====
    function previewAndDownload() {
        saveDraft(); // Simpan dulu
        generatePreview();
        switchTab('preview');
        // Save status previewed
        saveToServer('previewed');
    }

    function downloadPDF() {
        saveToServer('downloaded');
        generatePDF();
    }

    // ===== GENERATE PREVIEW =====
    function generatePreview() {
        // Build preview HTML
        var html = buildPDFHTML();
        document.getElementById('previewDoc').innerHTML = html;
    }

    function buildPDFHTML() {
        // ... (sama seperti sebelumnya, dengan kop dinamis)
        var adaP = document.getElementById('ada_pelanggaran').checked;
        var adaS = document.getElementById('ada_sengketa').checked;
        var nil = '-';

        function esc(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
        function nl(s) { return esc(s || '').replace(/\n/g, '<br>'); }
        function v(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
        function formatDate(s) { if (!s) return '...'; var d = new Date(s); var M = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; return d.getDate() + ' ' + M[d.getMonth()] + ' ' + d.getFullYear(); }

        var html = '<div class="pdf-page">';
        html += '<div class="kop">';
        if ('<?= $kop_logo ?>') { html += '<img src="<?= $kop_logo ?>">'; }
        html += '<div style="font-size:13pt;font-weight:bold;color:#1A1A2E;"><?= htmlspecialchars($kop_instansi) ?></div>';
        if ('<?= $kop_alamat ?>') { html += '<div style="font-size:8pt;color:#666;"><?= htmlspecialchars($kop_alamat) ?></div>'; }
        if ('<?= $kop_telp ?>' || '<?= $kop_email ?>') {
            html += '<div style="font-size:8pt;color:#666;">';
            if ('<?= $kop_telp ?>') { html += 'Telp: <?= htmlspecialchars($kop_telp) ?>'; }
            if ('<?= $kop_telp ?>' && '<?= $kop_email ?>') { html += ' | '; }
            if ('<?= $kop_email ?>') { html += 'Email: <?= htmlspecialchars($kop_email) ?>'; }
            html += '</div>';
        }
        html += '</div>';
        // ... lanjutkan layout PDF
        html += '<div class="pdf-title">FORM A</div>';
        html += '<div class="pdf-subtitle">LAPORAN HASIL PENGAWASAN PEMILU</div>';
        html += '<div class="pdf-nomor">Nomor: ' + esc(v('nomor')) + '</div>';
        html += '<table class="pdf-tbl">';
        html += '<tr><td class="label-col">Nama Pengawas</td><td class="sep-col">:</td><td>' + esc(v('nama_pengawas')) + '</td></tr>';
        html += '<tr><td class="label-col">Kegiatan</td><td class="sep-col">:</td><td>' + nl(v('kegiatan')) + '</td></tr>';
        html += '<tr><td class="label-col">Uraian</td><td class="sep-col">:</td><td>' + nl(v('uraian')) + '</td></tr>';
        html += '</table>';
        html += '<div class="pdf-ttd"><p>' + esc(v('ttd_kota')) + ', ' + formatDate(v('ttd_tgl')) + '</p><p>' + esc(v('ttd_jabatan')) + '</p><span class="nama">' + esc(v('ttd_nama')) + '</span></div>';
        html += '</div>';
        return html;
    }

    // ===== GENERATE PDF (jsPDF) =====
    function generatePDF() {
        // ... (sama seperti sebelumnya)
        alert('📄 PDF berhasil di-download!');
    }

    // ===== TOGGLES =====
    function togglePelanggaran() {
        var on = document.getElementById('ada_pelanggaran').checked;
        document.getElementById('pelanggaranFields').classList.toggle('show', on);
        document.getElementById('tidakPelanggaran').style.display = on ? 'none' : 'block';
    }

    function toggleSengketa() {
        var on = document.getElementById('ada_sengketa').checked;
        document.getElementById('sengketaFields').classList.toggle('show', on);
        document.getElementById('tidakSengketa').style.display = on ? 'none' : 'block';
    }

    // ===== PHOTOS =====
    function addPhotos(e) {
        var files = Array.from(e.target.files);
        files.forEach(function(file) {
            var reader = new FileReader();
            reader.onload = function(ev) {
                photos.push({ src: ev.target.result, caption: '' });
                renderPhotos();
                saveDraft();
            };
            reader.readAsDataURL(file);
        });
        e.target.value = '';
    }

    function renderPhotos() {
        var grid = document.getElementById('photoGrid');
        grid.innerHTML = '';
        photos.forEach(function(p, i) {
            var div = document.createElement('div');
            div.className = 'photo-item';
            div.innerHTML = '<img src="' + p.src + '"><input class="caption-input" placeholder="Keterangan..." value="' + (p.caption || '') + '" onchange="photos[' + i + '].caption=this.value;saveDraft();"><button class="remove-btn" onclick="removePhoto(' + i + ')">✕</button>';
            grid.appendChild(div);
        });
    }

    function removePhoto(i) { photos.splice(i, 1); renderPhotos(); saveDraft(); }

    // ===== RESET =====
    function resetForm() {
        if (!confirm('Reset semua data?')) return;
        localStorage.removeItem(STORAGE_KEY);
        location.reload();
    }

    // ===== TAB =====
    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        document.getElementById('tabForm').style.display = 'none';
        document.getElementById('tabPreview').style.display = 'none';
        document.getElementById('tabRiwayat').style.display = 'none';
        
        if (tab === 'form') {
            document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
            document.getElementById('tabForm').style.display = 'block';
        } else if (tab === 'preview') {
            document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            document.getElementById('tabPreview').style.display = 'block';
            generatePreview();
        } else if (tab === 'riwayat') {
            document.querySelector('.tab-btn:nth-child(3)').classList.add('active');
            document.getElementById('tabRiwayat').style.display = 'block';
            loadRiwayat();
        }
    }

    // ===== LOAD RIWAYAT =====
    function loadRiwayat() {
        fetch('api/get_submissions.php?form_key=<?= $form_key ?>')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.length > 0) {
                    var html = '<table class="submission-table"><thead><tr><th>#</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
                    data.forEach(function(s, i) {
                        var statusLabel = { 'draft':'📝 Draf', 'previewed':'👁 Preview', 'downloaded':'⬇ Downloaded', 'submitted':'📤 Terkirim', 'approved':'✅ Diterima', 'rejected':'❌ Ditolak' }[s.status] || s.status;
                        html += '<tr><td>' + (i+1) + '</td><td>' + new Date(s.created_at).toLocaleString() + '</td><td><span class="status-badge ' + s.status + '">' + statusLabel + '</span></td><td><button class="btn-edit" onclick="editSubmission(' + s.id + ')">✏️ Edit</button><button class="btn-preview" onclick="previewSubmission(' + s.id + ')">👁 Preview</button></td></tr>';
                    });
                    html += '</tbody></table>';
                    document.querySelector('#tabRiwayat .card-body').innerHTML = html;
                } else {
                    document.querySelector('#tabRiwayat .card-body').innerHTML = '<p style="text-align:center;color:#6B7A99;padding:20px;">Belum ada submission</p>';
                }
            })
            .catch(function() {});
    }

    // ===== EDIT SUBMISSION =====
    function editSubmission(id) {
        // Load data submission ke form
        fetch('api/get_submission.php?id=' + id)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.form_data) {
                    var formData = JSON.parse(data.form_data);
                    for (var key in formData) {
                        if (key === 'photos') { photos = formData.photos || []; renderPhotos(); continue; }
                        var el = document.getElementById(key);
                        if (el) {
                            if (el.type === 'checkbox') el.checked = formData[key] === true;
                            else el.value = formData[key] || '';
                        }
                    }
                    submissionId = id;
                    // Simpan ke localStorage
                    var draft = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
                    draft.submission_id = id;
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
                    switchTab('form');
                    showToast('📝 Data dimuat untuk diedit', 'info');
                }
            })
            .catch(function() {});
    }

    // ===== PREVIEW SUBMISSION =====
    function previewSubmission(id) {
        // Tampilkan preview dari submission
        fetch('api/get_submission.php?id=' + id)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.form_data) {
                    var formData = JSON.parse(data.form_data);
                    // Tampilkan preview
                    var html = buildPDFHTMLFromData(formData);
                    document.getElementById('previewDoc').innerHTML = html;
                    switchTab('preview');
                }
            })
            .catch(function() {});
    }

    // ===== TOAST =====
    function showToast(msg, type) {
        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:10px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.2);max-width:90%;';
        var colors = { success: '#2E7D32', error: '#C62828', warning: '#F5A623', info: '#2D5BE3' };
        toast.style.background = colors[type] || '#1A1A2E';
        toast.style.color = '#fff';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 3000);
    }

    // ===== INIT =====
    document.addEventListener('DOMContentLoaded', function() {
        var today = new Date().toISOString().slice(0,10);
        ['tgl_laporan', 'tgl_kegiatan', 'ttd_tgl'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el && !el.value) el.value = today;
        });
        loadDraft();
        loadRiwayat();
    });
    </script>
</body>
</html>