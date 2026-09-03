<?php
// ============================================================
// FORM A - LAPORAN HASIL PENGAWASAN PEMILIHAN
// ============================================================
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$form_key = 'form_a';
$form_title = 'Form A - Laporan Hasil Pengawasan Pemilu';

if (!canAccessForm($form_key)) {
    die("<h1>⛔ Akses Ditolak</h1><p>Anda tidak memiliki akses ke form ini.</p><a href='dashboard.php'>← Kembali</a>");
}

$stmt = $pdo->prepare("SELECT * FROM forms WHERE form_key = ?");
$stmt->execute([$form_key]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form || $form['is_active'] != 1) {
    die("<h1>⚠️ Form sedang tidak aktif</h1>");
}

$kop_logo = $form['kop_logo'] ?? '';
$kop_instansi = $form['kop_instansi'] ?? 'BADAN PENGAWAS PEMILIHAN UMUM';
$kop_alamat = $form['kop_alamat'] ?? '';
$kop_telp = $form['kop_telp'] ?? '';
$kop_email = $form['kop_email'] ?? '';

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $form_title ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
        * { box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; margin:0; }
        .form-container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; background: #fff; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .topbar h2 { margin: 0; font-size: 18px; color: #1A1A2E; }
        .tab-bar { display: flex; gap: 4px; background: #fff; border-radius: 12px; padding: 4px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); flex-wrap:wrap; }
        .tab-btn { padding: 10px 24px; border: none; border-radius: 8px; background: transparent; font-weight: 600; cursor: pointer; color: #6B7A99; transition: 0.2s; font-size: 14px; }
        .tab-btn.active { background: #E53935; color: #fff; }
        .tab-btn:hover:not(.active) { background: #f0f0f0; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 16px; overflow: hidden; }
        .card-header { padding: 12px 20px; background: #f8f9fc; border-bottom: 1px solid #eee; font-weight: 700; color: #1A1A2E; font-size: 14px; }
        .card-body { padding: 16px 20px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #2c3e50; margin-bottom: 4px; }
        .form-group label .req { color: #E53935; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px 12px; border: 1.5px solid #DDE3F0; border-radius: 8px; font-size: 14px; font-family: inherit; transition: 0.2s; background: #fff; }
        .form-group input:focus, .form-group textarea:focus { border-color: #E53935; outline: none; box-shadow: 0 0 0 3px rgba(229,57,53,0.1); }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .btn { padding: 8px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #E53935; color: #fff; }
        .btn-primary:hover { background: #c62828; }
        .btn-secondary { background: #6B7A99; color: #fff; }
        .btn-secondary:hover { background: #5a6a89; }
        .btn-success { background: #2E7D32; color: #fff; }
        .btn-success:hover { background: #1B5E20; }
        .btn-outline { background: transparent; color: #E53935; border: 1.5px solid #E53935; }
        .btn-outline:hover { background: #FFF5F5; }
        .btn-sm { padding: 4px 12px; font-size: 12px; }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .btn-row .btn { flex: 1; min-width: 100px; text-align: center; }
        .alert { padding: 10px 16px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; }
        .alert-info { background: #EEF4FF; color: #2D5BE3; border: 1px solid #C3D4F9; }
        .toggle-row { display: flex; align-items: center; gap: 12px; margin: 8px 0; }
        .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #ccc; border-radius: 24px; transition: 0.2s; cursor: pointer; }
        .toggle-slider::before { content: ""; position: absolute; width: 18px; height: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.2s; }
        .toggle-switch input:checked + .toggle-slider { background: #E53935; }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
        .toggle-extra { display: none; padding: 10px 0; }
        .toggle-extra.show { display: block; }
        .toggle-empty { text-align: center; padding: 10px 0; color: #999; font-size: 12px; font-style: italic; }
        .sec-label { font-size: 12px; font-weight: 700; color: #E53935; margin: 12px 0 6px; padding-bottom: 4px; border-bottom: 2px solid rgba(229,57,53,0.12); text-transform: uppercase; }
        .photo-zone { border: 2px dashed #DDE3F0; border-radius: 8px; padding: 16px; text-align: center; cursor: pointer; transition: 0.2s; background: #FAFBFD; }
        .photo-zone:hover { border-color: #E53935; background: #FFF5F5; }
        .photo-zone input { display: none; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 10px; }
        .photo-item { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 4/3; border: 1.5px solid #DDE3F0; background: #f0f0f0; }
        .photo-item img { width: 100%; height: 100%; object-fit: cover; }
        .photo-item .caption-input { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); padding: 4px 7px; border: none; color: #fff; font-size: 10px; font-family: inherit; width: 100%; outline: none; }
        .photo-item .remove-btn { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; background: rgba(229,57,53,0.85); border: none; border-radius: 50%; color: #fff; font-size: 12px; cursor: pointer; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-badge.draft { background: #EEF0F6; color: #6B7A99; }
        .status-badge.previewed { background: #EEF4FF; color: #2D5BE3; }
        .status-badge.downloaded { background: #EEFAF4; color: #1A7A45; }
        .status-badge.submitted { background: #FFFBEE; color: #B07A00; }
        .status-badge.approved { background: #EEFAF4; color: #1A7A45; }
        .status-badge.rejected { background: #FFE5E5; color: #C62828; }
        .submission-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .submission-table th { text-align: left; padding: 6px 8px; background: #F8F9FC; color: #6B7A99; font-weight: 700; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #DDE3F0; }
        .submission-table td { padding: 6px 8px; border-bottom: 1px solid #EEF0F6; vertical-align: middle; }
        .submission-table tr:hover { background: #FAFBFD; }
        .btn-edit { background: #2D5BE3; color: #fff; border: none; padding: 2px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; }
        .btn-edit:hover { background: #1A3F8A; }
        .btn-preview { background: #F5A623; color: #fff; border: none; padding: 2px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; }
        .btn-preview:hover { background: #d4911a; }
        #previewArea { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow: hidden; }
        .preview-toolbar { background: #f8f9fc; padding: 12px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .preview-content { padding: 20px; max-height: 700px; overflow-y: auto; }
        .pdf-page { background: #fff; padding: 30px 35px; max-width: 680px; margin: 0 auto 16px; font-family: 'Times New Roman', serif; font-size: 10.5pt; line-height: 1.6; border: 1px solid #ddd; border-radius: 4px; }
        .pdf-page .kop { text-align: center; margin-bottom: 12px; border-bottom: 2px solid #1A1A2E; padding-bottom: 10px; }
        .pdf-page .kop img { max-height: 60px; width: auto; }
        .pdf-page .kop .instansi { font-size: 13pt; font-weight: 700; color: #1A1A2E; }
        .pdf-page .kop .alamat { font-size: 8.5pt; color: #666; }
        .pdf-title { font-size: 12pt; font-weight: 700; text-align: center; text-transform: uppercase; }
        .pdf-subtitle { font-size: 11pt; font-weight: 700; text-align: center; text-transform: uppercase; margin-bottom: 4px; }
        .pdf-nomor { font-size: 10.5pt; font-weight: 700; text-align: center; margin-bottom: 12px; }
        .pdf-section { font-weight: 700; font-size: 11pt; margin: 10px 0 4px; }
        .pdf-tbl { width: 100%; border-collapse: collapse; font-size: 10pt; margin: 4px 0; }
        .pdf-tbl td { border: 1px solid #444; padding: 4px 8px; vertical-align: top; }
        .pdf-tbl .label-col { width: 180px; font-weight: 600; }
        .pdf-tbl .sep-col { width: 14px; text-align: center; }
        .pdf-ttd { text-align: center; margin-top: 20px; }
        .pdf-ttd .nama { font-weight: 700; text-decoration: underline; display: block; margin-top: 40px; }
        .pdf-ttd .jabatan { font-weight: 600; display: block; }
        .pdf-foto-title { text-align: center; font-weight: 700; font-size: 11pt; margin: 12px 0 8px; }
        .pdf-foto-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .pdf-foto-item img { width: 100%; border: 1px solid #ccc; border-radius: 4px; }
        .pdf-foto-item p { text-align: center; font-size: 8.5pt; margin-top: 3px; font-style: italic; color: #555; }
        .app { display: flex; min-height: 100vh; }
        .sidebar { width: 240px; background: #1A1A2E; color: #fff; padding: 20px; flex-shrink: 0; }
        .sidebar-brand { font-size: 20px; font-weight: 700; margin-bottom: 30px; }
        .sidebar-nav a { display: block; padding: 10px 12px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: 0.2s; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar-nav .logout { color: #E53935; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 16px; }
        .main { flex: 1; padding: 20px; background: #f0f2f5; }
        .hamburger { display: none; background: none; border: none; font-size: 24px; cursor: pointer; }
        .user-badge { background: rgba(0,0,0,0.08); padding: 4px 12px; border-radius: 20px; font-size: 13px; }
        .ql-editor { min-height: 200px; font-size: 14px; }
        @media (max-width: 768px) { .sidebar { display: none; } .sidebar.open { display: block; position: fixed; top: 0; left: 0; height: 100%; z-index: 1000; width: 260px; } .hamburger { display: block; } .main { padding: 10px; } .grid-2, .grid-3 { grid-template-columns: 1fr; } .btn-row { flex-direction: column; } .pdf-page { padding: 15px; } }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">📋 E-Form</div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <?php if (hasRole('super_admin') || hasRole('admin')): ?>
            <a href="admin_users.php">👥 Manajemen User</a>
            <a href="forms.php">📝 Manajemen Form</a>
            <?php endif; ?>
            <a href="profile.php">⚙️ Profile</a>
            <a href="logout.php" class="logout">🚪 Logout</a>
        </nav>
    </aside>
    <main class="main">
        <div class="form-container">
            <div class="topbar">
                <div style="display:flex;align-items:center;gap:10px;">
                    <button class="hamburger" onclick="toggleSidebar()">☰</button>
                    <h2>📋 <?= $form_title ?></h2>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?></span>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm" style="padding:4px 12px;font-size:12px;">← Kembali</a>
                </div>
            </div>

            <div class="tab-bar">
                <button class="tab-btn active" onclick="switchTab('form')">📝 Form</button>
                <button class="tab-btn" onclick="switchTab('preview')">👁 Preview</button>
                <button class="tab-btn" onclick="switchTab('riwayat')">📋 Riwayat</button>
            </div>

            <!-- TAB FORM -->
            <div id="tabForm">
                <div class="alert alert-info">ℹ️ Isi data lengkap. Klik <strong>Preview</strong> untuk melihat hasil dokumen.</div>
                <div id="formContainer">

                <!-- NOMOR LAPORAN -->
                <div class="card">
                    <div class="card-header">🔢 Nomor Laporan</div>
                    <div class="card-body">
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Nomor Laporan <span class="req">*</span></label>
                                <input type="text" id="nomor" placeholder="">
                            </div>
                            <div class="form-group">
                                <label>Tanggal Laporan <span class="req">*</span></label>
                                <input type="date" id="tgl_laporan">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- I. DATA PENGAWASAN -->
                <div class="card">
                    <div class="card-header">I. 👤 Data Pengawasan</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>1. Tahapan yang diawasi <span class="req">*</span></label>
                            <input type="text" id="tahapan" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>2. Nama Pelaksana Tugas Pengawasan <span class="req">*</span></label>
                            <input type="text" id="nama_pengawas" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>3. Jabatan <span class="req">*</span></label>
                            <input type="text" id="jabatan" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>4. Nomor Surat Perintah Tugas</label>
                            <input type="text" id="no_st" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>5. Alamat Sekretariat</label>
                            <input type="text" id="alamat" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" id="no_telp" placeholder="">
                        </div>
                    </div>
                </div>

                <!-- II. KEGIATAN PENGAWASAN -->
                <div class="card">
                    <div class="card-header">II. 📋 Kegiatan Pengawasan</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>1. Bentuk <span class="req">*</span></label>
                            <input type="text" id="bentuk" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>2. Tujuan <span class="req">*</span></label>
                            <textarea id="tujuan" rows="2" placeholder=""></textarea>
                        </div>
                        <div class="form-group">
                            <label>3. Sasaran <span class="req">*</span></label>
                            <input type="text" id="sasaran" placeholder="">
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>4. Tanggal</label>
                                <input type="date" id="tgl_kegiatan">
                            </div>
                            <div class="form-group">
                                <label>Tempat</label>
                                <input type="text" id="tempat_kegiatan" placeholder="">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- III. URAIAN SINGKAT -->
                <div class="card">
                    <div class="card-header">III. 📝 Uraian Singkat Hasil Pengawasan</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Uraian <span class="req">*</span></label>
                            <div id="editor_uraian" style="height: 300px; border: 1px solid #DDE3F0; border-radius: 8px;"></div>
                            <input type="hidden" id="uraian" name="uraian">
                        </div>
                    </div>
                </div>

                <!-- IV. DUGAAAN PELANGGARAN -->
                <div class="card">
                    <div class="card-header">IV. ⚠️ Informasi Dugaan Pelanggaran</div>
                    <div class="card-body">
                        <div class="toggle-row">
                            <label class="toggle-switch">
                                <input type="checkbox" id="ada_pelanggaran" onchange="togglePelanggaran()">
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Ada dugaan pelanggaran?</span>
                        </div>
                        <div id="pelanggaranFields" class="toggle-extra">
                            <div class="sec-label">1. Peristiwa</div>
                            <div class="form-group"><label>a. Peristiwa</label><textarea id="p_peristiwa" rows="2" placeholder=""></textarea></div>
                            <div class="grid-2">
                                <div class="form-group"><label>b. Tempat</label><input type="text" id="p_tempat" placeholder=""></div>
                                <div class="form-group"><label>c. Waktu</label><input type="text" id="p_waktu" placeholder=""></div>
                            </div>
                            <div class="grid-2">
                                <div class="form-group"><label>d. Pelaku</label><input type="text" id="p_pelaku" placeholder=""></div>
                                <div class="form-group"><label>e. Alamat Pelaku</label><input type="text" id="p_alamat_pelaku" placeholder=""></div>
                            </div>
                            <div class="sec-label">2. Saksi-saksi</div>
                            <div class="grid-2">
                                <div class="form-group"><label>a. Nama</label><input type="text" id="saksi1_nama" placeholder=""></div>
                                <div class="form-group"><label>Alamat</label><input type="text" id="saksi1_alamat" placeholder=""></div>
                            </div>
                            <div class="grid-2">
                                <div class="form-group"><label>b. Nama</label><input type="text" id="saksi2_nama" placeholder=""></div>
                                <div class="form-group"><label>Alamat</label><input type="text" id="saksi2_alamat" placeholder=""></div>
                            </div>
                            <div class="sec-label">3. Alat Bukti</div>
                            <div class="grid-3">
                                <div class="form-group"><label>a.</label><input type="text" id="bukti_a" placeholder=""></div>
                                <div class="form-group"><label>b.</label><input type="text" id="bukti_b" placeholder=""></div>
                                <div class="form-group"><label>c.</label><input type="text" id="bukti_c" placeholder=""></div>
                            </div>
                            <div class="sec-label">4. Barang Bukti</div>
                            <div class="grid-3">
                                <div class="form-group"><label>a.</label><input type="text" id="barang_a" placeholder=""></div>
                                <div class="form-group"><label>b.</label><input type="text" id="barang_b" placeholder=""></div>
                                <div class="form-group"><label>c.</label><input type="text" id="barang_c" placeholder=""></div>
                            </div>
                            <div class="sec-label">5. Uraian Singkat Dugaan Pelanggaran</div>
                            <div class="form-group"><textarea id="uraian_pelanggaran" rows="4" placeholder=""></textarea></div>
                            <div class="sec-label">6. Fakta dan Keterangan</div>
                            <div class="form-group"><textarea id="fakta" rows="4" placeholder=""></textarea></div>
                            <div class="sec-label">7. Analisa</div>
                            <div class="form-group"><textarea id="analisa" rows="4" placeholder=""></textarea></div>
                        </div>
                        <div id="tidakPelanggaran" class="toggle-empty">Nihil</div>
                    </div>
                </div>

                <!-- V. POTENSI SENGKETA -->
                <div class="card">
                    <div class="card-header">V. ⚡ Informasi Potensi Sengketa</div>
                    <div class="card-body">
                        <div class="toggle-row">
                            <label class="toggle-switch">
                                <input type="checkbox" id="ada_sengketa" onchange="toggleSengketa()">
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Ada potensi sengketa?</span>
                        </div>
                        <div id="sengketaFields" class="toggle-extra">
                            <div class="sec-label">1. Peristiwa</div>
                            <div class="form-group"><label>a. Peserta</label><input type="text" id="s_peserta" placeholder=""></div>
                            <div class="grid-2">
                                <div class="form-group"><label>b. Tempat</label><input type="text" id="s_tempat" placeholder=""></div>
                                <div class="form-group"><label>c. Waktu</label><input type="text" id="s_waktu" placeholder=""></div>
                            </div>
                            <div class="sec-label">2. Obyek Sengketa</div>
                            <div class="form-group"><label>a. Bentuk</label><input type="text" id="s_bentuk" placeholder=""></div>
                            <div class="form-group"><label>b. Identitas</label><input type="text" id="s_identitas" placeholder=""></div>
                            <div class="grid-2">
                                <div class="form-group"><label>c. Tanggal</label><input type="text" id="s_tgl_keluar" placeholder=""></div>
                                <div class="form-group"><label>d. Kerugian</label><input type="text" id="s_kerugian" placeholder=""></div>
                            </div>
                            <div class="sec-label">3. Uraian Singkat Potensi Sengketa</div>
                            <div class="form-group"><textarea id="s_uraian" rows="4" placeholder=""></textarea></div>
                        </div>
                        <div id="tidakSengketa" class="toggle-empty">Nihil</div>
                    </div>
                </div>

                <!-- VI. TTD -->
                <div class="card">
                    <div class="card-header">VI. ✍️ Penandatangan</div>
                    <div class="card-body">
                        <div class="grid-2">
                            <div class="form-group"><label>Kota <span class="req">*</span></label><input type="text" id="ttd_kota" placeholder=""></div>
                            <div class="form-group"><label>Tanggal TTD <span class="req">*</span></label><input type="date" id="ttd_tgl"></div>
                        </div>
                        <div class="form-group"><label>Jabatan <span class="req">*</span></label><input type="text" id="ttd_jabatan" placeholder=""></div>
                        <div class="form-group"><label>Nama Lengkap <span class="req">*</span></label><input type="text" id="ttd_nama" placeholder=""></div>
                    </div>
                </div>

                <!-- DOKUMENTASI -->
                <div class="card">
                    <div class="card-header">📸 Dokumentasi</div>
                    <div class="card-body">
                        <div class="form-group"><label>Judul Dokumentasi</label><input type="text" id="foto_judul" placeholder=""></div>
                        <div class="photo-zone" onclick="document.getElementById('photoInput').click()">
                            <input type="file" id="photoInput" accept="image/*" multiple onchange="addPhotos(event)">
                            <div style="font-size:28px;">📷</div>
                            <p>Tap untuk pilih foto</p>
                            <small>JPG, PNG</small>
                        </div>
                        <div class="photo-grid" id="photoGrid"></div>
                    </div>
                </div>

                <!-- BUTTONS -->
                <div class="btn-row">
                    <button class="btn btn-outline" onclick="resetForm()">🗑 Reset</button>
                    <button class="btn btn-secondary" onclick="saveDraft()">💾 Simpan Draf</button>
                    <button class="btn btn-primary" onclick="previewAndDownload()">👁 Preview</button>
                    <button class="btn btn-success" onclick="downloadPDF()">⬇ Download PDF</button>
                </div>

                </div><!-- end formContainer -->
            </div><!-- end tabForm -->

            <!-- TAB PREVIEW -->
            <div id="tabPreview" style="display:none;">
                <div id="previewArea">
                    <div class="preview-toolbar">
                        <span>👁 Preview Dokumen</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="btn btn-outline btn-sm" onclick="switchTab('form')">← Edit</button>
                            <button class="btn btn-primary btn-sm" onclick="downloadPDF()">⬇ Download PDF</button>
                        </div>
                    </div>
                    <div class="preview-content">
                        <div id="previewDoc">
                            <p style="text-align:center;color:#9AA6C0;padding:40px 0;">Isi form lalu klik <strong>Preview</strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB RIWAYAT -->
            <div id="tabRiwayat" style="display:none;">
                <div class="card">
                    <div class="card-header">📋 Riwayat Submission</div>
                    <div class="card-body" id="riwayatContent">
                        <?php if (empty($submissionList)): ?>
                            <p style="text-align:center;color:#6B7A99;padding:20px;">Belum ada submission</p>
                        <?php else: ?>
                        <table class="submission-table">
                            <thead><tr><th>#</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php $no=1; foreach ($submissionList as $s):
                                $statusLabel = ['draft'=>'📝 Draf','previewed'=>'👁 Preview','downloaded'=>'⬇ Downloaded','submitted'=>'📤 Terkirim','approved'=>'✅ Diterima','rejected'=>'❌ Ditolak'][$s['status']] ?? $s['status'];
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                                <td><span class="status-badge <?= $s['status'] ?>"><?= $statusLabel ?></span></td>
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

        </div><!-- form-container -->
    </main>
</div>

<script>
// ================================================================
// STATE
// ================================================================
var STORAGE_KEY = 'form_a_draft';
var photos = [];
var submissionId = null;
var quill = null;

// ================================================================
// SIDEBAR TOGGLE
// ================================================================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// ================================================================
// TOGGLE PELANGGARAN & SENGKETA
// ================================================================
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

// ================================================================
// PHOTOS
// ================================================================
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
        div.innerHTML = '<img src="' + p.src + '"><input class="caption-input" placeholder="Keterangan..." value="' + (p.caption||'') + '" onchange="photos['+i+'].caption=this.value;saveDraft();"><button class="remove-btn" onclick="removePhoto('+i+')">✕</button>';
        grid.appendChild(div);
    });
}

function removePhoto(i) { photos.splice(i,1); renderPhotos(); saveDraft(); }

// ================================================================
// GET ALL DATA
// ================================================================
function getAllData() {
    var fields = document.querySelectorAll('#formContainer input, #formContainer textarea, #formContainer select');
    var data = {};
    fields.forEach(function(el) {
        if (el.id) {
            if (el.type === 'checkbox') data[el.id] = el.checked;
            else data[el.id] = el.value;
        }
    });
    if (quill) {
        data.uraian = quill.root.innerHTML;
    }
    data.photos = photos;
    return data;
}

// ================================================================
// SAVE DRAFT
// ================================================================
function saveDraft() {
    var data = getAllData();
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    showToast('💾 Draf disimpan', 'success');
    saveToServer('draft');
}

function saveToServer(status) {
    var data = getAllData();
    fetch('api/save_submission.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            form_key: 'form_a',
            data: data,
            status: status,
            submission_id: submissionId
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(result) {
        if (result.success) {
            submissionId = result.submission_id;
            var draft = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            draft.submission_id = result.submission_id;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
        }
    })
    .catch(function() {});
}

// ================================================================
// LOAD DRAFT
// ================================================================
function loadDraft() {
    try {
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            var data = JSON.parse(saved);
            for (var key in data) {
                if (key === 'photos') { photos = data.photos || []; renderPhotos(); continue; }
                if (key === 'uraian') {
                    if (quill && data.uraian) {
                        quill.root.innerHTML = data.uraian;
                    }
                    document.getElementById('uraian').value = data.uraian || '';
                    continue;
                }
                var el = document.getElementById(key);
                if (el) {
                    if (el.type === 'checkbox') el.checked = data[key] === true;
                    else el.value = data[key] || '';
                }
            }
            if (data.ada_pelanggaran) { document.getElementById('ada_pelanggaran').checked = true; togglePelanggaran(); }
            if (data.ada_sengketa) { document.getElementById('ada_sengketa').checked = true; toggleSengketa(); }
            if (data.submission_id) submissionId = data.submission_id;
        }
    } catch(e) {}
}

// ================================================================
// RESET
// ================================================================
function resetForm() {
    if (!confirm('Reset semua data?')) return;
    localStorage.removeItem(STORAGE_KEY);
    location.reload();
}

// ================================================================
// PREVIEW
// ================================================================
function previewAndDownload() {
    saveDraft();
    generatePreview();
    switchTab('preview');
    saveToServer('previewed');
}

function generatePreview() {
    var html = buildPDFHTML();
    document.getElementById('previewDoc').innerHTML = html;
}

// ================================================================
// BUILD PDF HTML - UNTUK PREVIEW
// ================================================================
function buildPDFHTML() {
    function v(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
    function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function nl(s) { return esc(s||'').replace(/\n/g,'<br>'); }
    function fdate(s) { if(!s) return ''; var d=new Date(s); var M=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; return d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear(); }

    var uraianContent = quill ? quill.root.innerHTML : document.getElementById('uraian').value;

    var html = '<div class="pdf-page">';
    html += '<div class="kop">';
    if ('<?= $kop_logo ?>') html += '<img src="<?= $kop_logo ?>"><br>';
    html += '<div class="instansi"><?= htmlspecialchars($kop_instansi) ?></div>';
    if ('<?= $kop_alamat ?>') html += '<div class="alamat"><?= htmlspecialchars($kop_alamat) ?></div>';
    if ('<?= $kop_telp ?>' || '<?= $kop_email ?>') {
        html += '<div class="alamat">';
        if ('<?= $kop_telp ?>') html += 'Telp: <?= htmlspecialchars($kop_telp) ?>';
        if ('<?= $kop_telp ?>' && '<?= $kop_email ?>') html += ' | ';
        if ('<?= $kop_email ?>') html += 'Email: <?= htmlspecialchars($kop_email) ?>';
        html += '</div>';
    }
    html += '</div>';

    html += '<div class="pdf-title">FORM A</div>';
    html += '<div class="pdf-subtitle">LAPORAN HASIL PENGAWASAN PEMILIHAN</div>';
    html += '<div class="pdf-nomor">Nomor: '+esc(v('nomor'))+'</div>';

    html += '<div class="pdf-section">I. Data Pengawasan</div>';
    html += '<table class="pdf-tbl">';
    html += '<tr><td class="label-col">1. Tahapan yang diawasi</td><td class="sep-col">:</td><td>'+esc(v('tahapan'))+'</td></tr>';
    html += '<tr><td class="label-col">2. Nama Pelaksana Tugas Pengawasan</td><td class="sep-col">:</td><td>'+nl(v('nama_pengawas'))+'</td></tr>';
    html += '<tr><td class="label-col">3. Jabatan</td><td class="sep-col">:</td><td>'+nl(v('jabatan'))+'</td></tr>';
    html += '<tr><td class="label-col">4. Nomor Surat Perintah Tugas</td><td class="sep-col">:</td><td>'+esc(v('no_st'))+'</td></tr>';
    html += '<tr><td class="label-col">5. Alamat</td><td class="sep-col">:</td><td>'+nl(v('alamat'))+'</td></tr>';
    html += '</table>';

    html += '<div class="pdf-section">II. Kegiatan Pengawasan</div>';
    html += '<table class="pdf-tbl">';
    html += '<tr><td class="label-col">1. Bentuk</td><td class="sep-col">:</td><td>'+esc(v('bentuk'))+'</td></tr>';
    html += '<tr><td class="label-col">2. Tujuan</td><td class="sep-col">:</td><td>'+nl(v('tujuan'))+'</td></tr>';
    html += '<tr><td class="label-col">3. Sasaran</td><td class="sep-col">:</td><td>'+nl(v('sasaran'))+'</td></tr>';
    html += '<tr><td class="label-col">4. Waktu dan Tempat</td><td class="sep-col">:</td><td>'+fdate(v('tgl_kegiatan'))+'<br>'+esc(v('tempat_kegiatan'))+'</td></tr>';
    html += '</table>';

    html += '<div class="pdf-section">III. Uraian Singkat Hasil Pengawasan</div>';
    html += '<div style="text-align:justify;">'+uraianContent+'</div>';

    html += '<div class="pdf-section">IV. Informasi Dugaan Pelanggaran</div>';
    var adaP = document.getElementById('ada_pelanggaran').checked;
    if (adaP) {
        html += '<table class="pdf-tbl">';
        html += '<tr><td class="label-col">1. Peristiwa</td><td class="sep-col">:</td><td>'+nl(v('p_peristiwa'))+'</td></tr>';
        html += '<tr><td class="label-col">b. Tempat Kejadian</td><td class="sep-col">:</td><td>'+esc(v('p_tempat'))+'</td></tr>';
        html += '<tr><td class="label-col">c. Waktu Kejadian</td><td class="sep-col">:</td><td>'+esc(v('p_waktu'))+'</td></tr>';
        html += '<tr><td class="label-col">d. Pelaku</td><td class="sep-col">:</td><td>'+esc(v('p_pelaku'))+'</td></tr>';
        html += '<tr><td class="label-col">e. Alamat</td><td class="sep-col">:</td><td>'+esc(v('p_alamat_pelaku'))+'</td></tr>';
        html += '<tr><td class="label-col">2. Saksi-saksi</td><td class="sep-col">:</td><td>'+esc(v('saksi1_nama'))+' ('+esc(v('saksi1_alamat'))+')<br>'+esc(v('saksi2_nama'))+' ('+esc(v('saksi2_alamat'))+')</td></tr>';
        html += '<tr><td class="label-col">3. Alat Bukti</td><td class="sep-col">:</td><td>'+esc(v('bukti_a'))+'<br>'+esc(v('bukti_b'))+'<br>'+esc(v('bukti_c'))+'</td></tr>';
        html += '<tr><td class="label-col">4. Barang Bukti</td><td class="sep-col">:</td><td>'+esc(v('barang_a'))+'<br>'+esc(v('barang_b'))+'<br>'+esc(v('barang_c'))+'</td></tr>';
        html += '<tr><td class="label-col">5. Uraian Singkat</td><td class="sep-col">:</td><td>'+nl(v('uraian_pelanggaran'))+'</td></tr>';
        html += '<tr><td class="label-col">6. Fakta dan Keterangan</td><td class="sep-col">:</td><td>'+nl(v('fakta'))+'</td></tr>';
        html += '<tr><td class="label-col">7. Analisa</td><td class="sep-col">:</td><td>'+nl(v('analisa'))+'</td></tr>';
        html += '</table>';
    } else {
        html += '<p style="font-style:italic;color:#666;">Nihil</p>';
    }

    html += '<div class="pdf-section">V. Informasi Potensi Sengketa</div>';
    var adaS = document.getElementById('ada_sengketa').checked;
    if (adaS) {
        html += '<table class="pdf-tbl">';
        html += '<tr><td class="label-col">1. Peristiwa</td><td class="sep-col">:</td><td>'+esc(v('s_peserta'))+'</td></tr>';
        html += '<tr><td class="label-col">b. Tempat Kejadian</td><td class="sep-col">:</td><td>'+esc(v('s_tempat'))+'</td></tr>';
        html += '<tr><td class="label-col">c. Waktu Kejadian</td><td class="sep-col">:</td><td>'+esc(v('s_waktu'))+'</td></tr>';
        html += '<tr><td class="label-col">2. Obyek Sengketa</td><td class="sep-col">:</td><td>'+esc(v('s_bentuk'))+'</td></tr>';
        html += '<tr><td class="label-col">b. Identitas</td><td class="sep-col">:</td><td>'+esc(v('s_identitas'))+'</td></tr>';
        html += '<tr><td class="label-col">c. Tanggal</td><td class="sep-col">:</td><td>'+esc(v('s_tgl_keluar'))+'</td></tr>';
        html += '<tr><td class="label-col">d. Kerugian</td><td class="sep-col">:</td><td>'+esc(v('s_kerugian'))+'</td></tr>';
        html += '<tr><td class="label-col">3. Uraian Singkat</td><td class="sep-col">:</td><td>'+nl(v('s_uraian'))+'</td></tr>';
        html += '</table>';
    } else {
        html += '<p style="font-style:italic;color:#666;">Nihil</p>';
    }

    html += '<div class="pdf-ttd">';
    html += '<p>'+esc(v('ttd_kota'))+', '+fdate(v('ttd_tgl'))+'</p>';
    html += '<p class="jabatan">'+esc(v('ttd_jabatan'))+'</p>';
    html += '<span class="nama">'+esc(v('ttd_nama'))+'</span>';
    html += '</div>';

    if (photos.length > 0) {
        html += '<div style="page-break-before: always;"></div>';
        html += '<div class="pdf-page" style="text-align:center;">';
        html += '<div class="pdf-foto-title">DOKUMENTASI KEGIATAN</div>';
        if (v('foto_judul')) html += '<p style="text-align:center;font-size:9pt;font-style:italic;margin-bottom:12px;">'+esc(v('foto_judul'))+'</p>';
        html += '<div class="pdf-foto-grid">';
        photos.forEach(function(p) {
            html += '<div class="pdf-foto-item" style="margin-bottom:10px;">';
            html += '<img src="'+p.src+'" style="width:100%;max-width:280px;border:1px solid #ccc;border-radius:4px;">';
            html += '<p style="text-align:center;font-size:8.5pt;margin-top:3px;font-style:italic;color:#555;">'+esc(p.caption||'')+'</p>';
            html += '</div>';
        });
        html += '</div>';
        html += '</div>';
    }

    html += '</div>';
    return html;
}

// ================================================================
// DOWNLOAD PDF - DENGAN LOGO
// ================================================================
function downloadPDF() {
    saveToServer('downloaded');
    
    function v(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
    function esc(s) { return s || ''; }
    function fdate(s) { if(!s) return ''; var d=new Date(s); var M=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; return d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear(); }
    
    var nomor = esc(v('nomor'));
    var tahapan = esc(v('tahapan'));
    var nama_pengawas = esc(v('nama_pengawas'));
    var jabatan = esc(v('jabatan'));
    var no_st = esc(v('no_st'));
    var alamat = esc(v('alamat'));
    var bentuk = esc(v('bentuk'));
    var tujuan = esc(v('tujuan'));
    var sasaran = esc(v('sasaran'));
    var tgl_kegiatan = fdate(v('tgl_kegiatan'));
    var tempat_kegiatan = esc(v('tempat_kegiatan'));
    var uraian = quill ? quill.root.innerText || quill.root.textContent : esc(v('uraian'));
    var ttd_kota = esc(v('ttd_kota'));
    var ttd_tgl = fdate(v('ttd_tgl'));
    var ttd_jabatan = esc(v('ttd_jabatan'));
    var ttd_nama = esc(v('ttd_nama'));
    var foto_judul = esc(v('foto_judul'));
    var adaP = document.getElementById('ada_pelanggaran').checked;
    var adaS = document.getElementById('ada_sengketa').checked;
    
    var pdf = new jspdf.jsPDF('p', 'mm', [210, 330]);
    var pdfWidth = pdf.internal.pageSize.getWidth();
    var pdfHeight = pdf.internal.pageSize.getHeight();
    var margin = 10;
    var contentWidth = pdfWidth - (margin * 2);
    var y = margin;
    
    // ===== KOP DENGAN LOGO =====
    var logoUrl = '<?= $kop_logo ?>';
    if (logoUrl) {
        try {
            pdf.addImage(logoUrl, 'JPEG', pdfWidth/2 - 12, y, 25, 25);
            y += 30;
        } catch(e) {
            console.log('Logo tidak bisa dimuat: ' + e.message);
            y += 5;
        }
    }
    
    pdf.setFontSize(12);
    pdf.setFont('times', 'bold');
    var kopText = '<?= htmlspecialchars($kop_instansi) ?>';
    pdf.text(kopText, pdfWidth/2, y, { align: 'center' });
    y += 6;
    
    pdf.setFontSize(8);
    pdf.setFont('times', 'normal');
    var alamatKop = '<?= htmlspecialchars($kop_alamat) ?>';
    if (alamatKop) { 
        pdf.text(alamatKop, pdfWidth/2, y, { align: 'center' }); 
        y += 4;  // <-- jarak Perumahan ke Telp/Email (2→4)
    }
    var telpKop = '<?= htmlspecialchars($kop_telp) ?>';
    var emailKop = '<?= htmlspecialchars($kop_email) ?>';
    if (telpKop || emailKop) {
        var infoKop = '';
        if (telpKop) infoKop += 'Telp: ' + telpKop;
        if (telpKop && emailKop) infoKop += ' | ';
        if (emailKop) infoKop += 'Email: ' + emailKop;
        pdf.text(infoKop, pdfWidth/2, y, { align: 'center' });
        y += 3;  // <-- jarak Telp/Email ke garis (2→3)
    }
    pdf.line(margin, y, pdfWidth - margin, y);
    y += 8;  // <-- jarak garis ke FORM A (4→8)
    
    // ===== JUDUL =====
    pdf.setFontSize(12);
    pdf.setFont('times', 'bold');
    pdf.text('FORM A', pdfWidth/2, y, { align: 'center' });
    y += 7;
    pdf.setFontSize(11);
    pdf.text('LAPORAN HASIL PENGAWASAN PEMILIHAN', pdfWidth/2, y, { align: 'center' });
    y += 8;
    pdf.setFontSize(10);
    pdf.text('Nomor: ' + (nomor || '-'), pdfWidth/2, y, { align: 'center' });
    y += 10;
    
    // ===== I. DATA PENGAWASAN =====
    pdf.setFontSize(10);
    pdf.setFont('times', 'bold');
    pdf.text('I. Data Pengawasan', margin, y);
    y += 6;
    pdf.setFont('times', 'normal');
    pdf.setFontSize(9);
    var dataRows = [
        ['1. Tahapan yang diawasi', tahapan || '-'],
        ['2. Nama Pelaksana', nama_pengawas || '-'],
        ['3. Jabatan', jabatan || '-'],
        ['4. Nomor Surat Tugas', no_st || '-'],
        ['5. Alamat', alamat || '-']
    ];
    dataRows.forEach(function(row) {
        if (y > pdfHeight - margin) { pdf.addPage(); y = margin; }
        pdf.text(row[0], margin, y);
        var splitText = pdf.splitTextToSize(row[1], 120);
        pdf.text(splitText, margin + 60, y);
        y += (splitText.length * 5) + 2;
    });
    y += 4;
    
    // ===== II. KEGIATAN =====
    if (y > pdfHeight - margin - 20) { pdf.addPage(); y = margin; }
    pdf.setFont('times', 'bold');
    pdf.setFontSize(10);
    pdf.text('II. Kegiatan Pengawasan', margin, y);
    y += 6;
    pdf.setFont('times', 'normal');
    pdf.setFontSize(9);
    var kegiatanRows = [
        ['1. Bentuk', bentuk || '-'],
        ['2. Tujuan', tujuan || '-'],
        ['3. Sasaran', sasaran || '-'],
        ['4. Waktu dan Tempat', (tgl_kegiatan || '-') + ', ' + (tempat_kegiatan || '-')]
    ];
    kegiatanRows.forEach(function(row) {
        if (y > pdfHeight - margin) { pdf.addPage(); y = margin; }
        pdf.text(row[0], margin, y);
        var splitText = pdf.splitTextToSize(row[1], 120);
        pdf.text(splitText, margin + 60, y);
        y += (splitText.length * 5) + 2;
    });
    y += 4;
    
    // ===== III. URAIAN =====
    if (y > pdfHeight - margin - 20) { pdf.addPage(); y = margin; }
    pdf.setFont('times', 'bold');
    pdf.setFontSize(10);
    pdf.text('III. Uraian Singkat Hasil Pengawasan', margin, y);
    y += 6;
    pdf.setFont('times', 'normal');
    pdf.setFontSize(9);
    var uraianText = uraian || '-';
    var uraianSplit = pdf.splitTextToSize(uraianText, contentWidth);
    uraianSplit.forEach(function(line) {
        if (y > pdfHeight - margin) { pdf.addPage(); y = margin; }
        pdf.text(line, margin, y);
        y += 5;
    });
    y += 4;
    
    // ===== IV. PELANGGARAN =====
    if (y > pdfHeight - margin - 20) { pdf.addPage(); y = margin; }
    pdf.setFont('times', 'bold');
    pdf.setFontSize(10);
    pdf.text('IV. Informasi Dugaan Pelanggaran', margin, y);
    y += 6;
    pdf.setFont('times', 'normal');
    pdf.setFontSize(9);
    if (adaP) {
        var pelanggaranData = [
            ['1. Peristiwa', v('p_peristiwa') || '-'],
            ['   b. Tempat', v('p_tempat') || '-'],
            ['   c. Waktu', v('p_waktu') || '-'],
            ['   d. Pelaku', v('p_pelaku') || '-'],
            ['   e. Alamat', v('p_alamat_pelaku') || '-'],
            ['2. Saksi-saksi', (v('saksi1_nama') || '-') + ' (' + (v('saksi1_alamat') || '-') + '), ' + (v('saksi2_nama') || '-') + ' (' + (v('saksi2_alamat') || '-') + ')'],
            ['3. Alat Bukti', (v('bukti_a') || '-') + ', ' + (v('bukti_b') || '-') + ', ' + (v('bukti_c') || '-')],
            ['4. Barang Bukti', (v('barang_a') || '-') + ', ' + (v('barang_b') || '-') + ', ' + (v('barang_c') || '-')],
            ['5. Uraian', v('uraian_pelanggaran') || '-'],
            ['6. Fakta', v('fakta') || '-'],
            ['7. Analisa', v('analisa') || '-']
        ];
        pelanggaranData.forEach(function(row) {
            if (y > pdfHeight - margin) { pdf.addPage(); y = margin; }
            pdf.text(row[0], margin, y);
            var splitText = pdf.splitTextToSize(row[1], 120);
            pdf.text(splitText, margin + 60, y);
            y += (splitText.length * 5) + 2;
        });
    } else {
        pdf.text('Nihil', margin, y);
        y += 6;
    }
    y += 4;
    
    // ===== V. SENGKETA =====
    if (y > pdfHeight - margin - 20) { pdf.addPage(); y = margin; }
    pdf.setFont('times', 'bold');
    pdf.setFontSize(10);
    pdf.text('V. Informasi Potensi Sengketa', margin, y);
    y += 6;
    pdf.setFont('times', 'normal');
    pdf.setFontSize(9);
    if (adaS) {
        var sengketaData = [
            ['1. Peristiwa', v('s_peserta') || '-'],
            ['   b. Tempat', v('s_tempat') || '-'],
            ['   c. Waktu', v('s_waktu') || '-'],
            ['2. Obyek Sengketa', v('s_bentuk') || '-'],
            ['   b. Identitas', v('s_identitas') || '-'],
            ['   c. Tanggal', v('s_tgl_keluar') || '-'],
            ['   d. Kerugian', v('s_kerugian') || '-'],
            ['3. Uraian', v('s_uraian') || '-']
        ];
        sengketaData.forEach(function(row) {
            if (y > pdfHeight - margin) { pdf.addPage(); y = margin; }
            pdf.text(row[0], margin, y);
            var splitText = pdf.splitTextToSize(row[1], 120);
            pdf.text(splitText, margin + 60, y);
            y += (splitText.length * 5) + 2;
        });
    } else {
        pdf.text('Nihil', margin, y);
        y += 6;
    }
    y += 4;
    
    // ===== VI. TTD =====
    if (y > pdfHeight - margin - 40) { pdf.addPage(); y = margin; }
    pdf.setFont('times', 'bold');
    pdf.setFontSize(10);
    pdf.text('VI. Penandatangan', margin, y);
    y += 8;
    pdf.setFont('times', 'normal');
    pdf.setFontSize(9);
    var ttdText = (ttd_kota || '-') + ', ' + (ttd_tgl || '-');
    pdf.text(ttdText, margin + 80, y);
    y += 8;
    pdf.text(ttd_jabatan || '-', margin + 80, y);
    y += 8;
    pdf.text('_________________________', margin + 75, y);
    y += 6;
    pdf.setFont('times', 'bold');
    pdf.text(ttd_nama || '-', margin + 80, y);
    y += 6;
    pdf.setFont('times', 'normal');
    pdf.setFontSize(8);
    pdf.text('Nama Lengkap', margin + 75, y);
    y += 10;
    
    // ===== DOKUMENTASI =====
    if (photos.length > 0) {
        pdf.addPage();
        y = margin + 20;
        pdf.setFont('times', 'bold');
        pdf.setFontSize(12);
        pdf.text('DOKUMENTASI KEGIATAN', pdfWidth/2, y, { align: 'center' });
        y += 10;
        
        if (foto_judul) {
            pdf.setFont('times', 'italic');
            pdf.setFontSize(9);
            pdf.text(foto_judul, pdfWidth/2, y, { align: 'center' });
            y += 10;
        }
        
        var imgWidth = 80;
        var imgHeight = 60;
        var cols = 2;
        var spacing = 10;
        var totalWidth = (cols * imgWidth) + ((cols - 1) * spacing);
        var startX = (pdfWidth - totalWidth) / 2;
        
        photos.forEach(function(p, index) {
            var col = index % cols;
            var row = Math.floor(index / cols);
            var x = startX + (col * (imgWidth + spacing));
            var yPos = y + (row * (imgHeight + spacing + 10));
            
            if (yPos + imgHeight > pdfHeight - margin) {
                pdf.addPage();
                y = margin + 20;
                yPos = y + (row * (imgHeight + spacing + 10));
            }
            
            try {
                pdf.addImage(p.src, 'JPEG', x, yPos, imgWidth, imgHeight);
                if (p.caption) {
                    pdf.setFont('times', 'italic');
                    pdf.setFontSize(7);
                    pdf.text(p.caption, x + imgWidth/2, yPos + imgHeight + 4, { align: 'center' });
                }
            } catch(e) {
                console.log('Gagal load foto: ' + e.message);
            }
        });
    }
    
    pdf.save('Laporan_Hasil_Pengawasan_' + new Date().toISOString().slice(0,10) + '.pdf');
    showToast('📄 PDF F4 berhasil di-download!', 'success');
}

// ================================================================
// TAB
// ================================================================
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
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

// ================================================================
// LOAD RIWAYAT
// ================================================================
function loadRiwayat() {
    fetch('api/get_submissions.php?form_key=form_a')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var container = document.getElementById('riwayatContent');
            if (data && data.length > 0) {
                var html = '<table class="submission-table"><thead><tr><th>#</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
                data.forEach(function(s, i) {
                    var label = { 'draft':'📝 Draf', 'previewed':'👁 Preview', 'downloaded':'⬇ Downloaded', 'submitted':'📤 Terkirim', 'approved':'✅ Diterima', 'rejected':'❌ Ditolak' }[s.status] || s.status;
                    html += '<tr><td>'+(i+1)+'</td><td>'+new Date(s.created_at).toLocaleString()+'</td><td><span class="status-badge '+s.status+'">'+label+'</span></td><td><button class="btn-edit" onclick="editSubmission('+s.id+')">✏️ Edit</button><button class="btn-preview" onclick="previewSubmission('+s.id+')">👁 Preview</button></td></tr>';
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p style="text-align:center;color:#6B7A99;padding:20px;">Belum ada submission</p>';
            }
        })
        .catch(function() {});
}

// ================================================================
// EDIT SUBMISSION
// ================================================================
function editSubmission(id) {
    fetch('api/get_submission.php?id='+id)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.form_data) {
                var formData = JSON.parse(data.form_data);
                for (var key in formData) {
                    if (key === 'photos') { photos = formData.photos || []; renderPhotos(); continue; }
                    if (key === 'uraian') {
                        if (quill && formData.uraian) {
                            quill.root.innerHTML = formData.uraian;
                        }
                        document.getElementById('uraian').value = formData.uraian || '';
                        continue;
                    }
                    var el = document.getElementById(key);
                    if (el) {
                        if (el.type === 'checkbox') el.checked = formData[key] === true;
                        else el.value = formData[key] || '';
                    }
                }
                submissionId = id;
                var draft = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
                draft.submission_id = id;
                localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
                if (formData.ada_pelanggaran) { document.getElementById('ada_pelanggaran').checked = true; togglePelanggaran(); }
                if (formData.ada_sengketa) { document.getElementById('ada_sengketa').checked = true; toggleSengketa(); }
                switchTab('form');
                showToast('📝 Data dimuat untuk diedit', 'info');
            }
        })
        .catch(function() {});
}

function previewSubmission(id) {
    fetch('api/get_submission.php?id='+id)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.form_data) {
                var formData = JSON.parse(data.form_data);
                for (var key in formData) {
                    if (key === 'photos') { photos = formData.photos || []; continue; }
                    if (key === 'uraian') {
                        if (quill && formData.uraian) {
                            quill.root.innerHTML = formData.uraian;
                        }
                        document.getElementById('uraian').value = formData.uraian || '';
                        continue;
                    }
                    var el = document.getElementById(key);
                    if (el) {
                        if (el.type === 'checkbox') el.checked = formData[key] === true;
                        else el.value = formData[key] || '';
                    }
                }
                generatePreview();
                switchTab('preview');
            }
        })
        .catch(function() {});
}

// ================================================================
// TOAST
// ================================================================
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

// ================================================================
// INIT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var today = new Date().toISOString().slice(0,10);
    ['tgl_laporan', 'tgl_kegiatan', 'ttd_tgl'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && !el.value) el.value = today;
    });
    
    quill = new Quill('#editor_uraian', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'font': [] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'indent': '-1' }, { 'indent': '+1' }],
                [{ 'align': [] }],
                ['blockquote', 'code-block'],
                ['link'],
                ['clean']
            ]
        },
        placeholder: 'Tulis uraian hasil pengawasan di sini...'
    });
    
    quill.on('text-change', function() {
        document.getElementById('uraian').value = quill.root.innerHTML;
    });
    
    loadDraft();
    loadRiwayat();
});
</script>
</body>
</html>