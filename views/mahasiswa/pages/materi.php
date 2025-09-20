<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once __DIR__.'/../../../includes/config.php';

// Verifikasi koneksi database
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if ($role !== 'mahasiswa') {
    header("Location: ../../login.php");
    exit();
}

// Ambil data mahasiswa
$mahasiswa_sql = "SELECT m.*, ps.nama_prodi 
                  FROM mahasiswa m
                  LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi
                  WHERE m.user_id = ?";
$mahasiswa_stmt = $conn->prepare($mahasiswa_sql);
$mahasiswa_stmt->bind_param("i", $user_id);
$mahasiswa_stmt->execute();
$mahasiswa_result = $mahasiswa_stmt->get_result();
$mahasiswa_data = $mahasiswa_result->fetch_assoc();

if (!$mahasiswa_data) {
    $_SESSION['error_message'] = "Data mahasiswa tidak ditemukan!";
    header("Location: ../../login.php");
    exit();
}

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');

// Fungsi untuk membersihkan input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Fungsi pencarian dan filter
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_matkul = isset($_GET['matkul']) ? clean_input($_GET['matkul']) : '';
$filter_jenis = isset($_GET['jenis']) ? clean_input($_GET['jenis']) : '';
$filter_pertemuan = isset($_GET['pertemuan']) ? clean_input($_GET['pertemuan']) : '';

// Query untuk materi yang bisa diakses mahasiswa
$where_clauses = ["mp.matkul_id IN (
    SELECT DISTINCT jk.matkul_id 
    FROM krs k 
    JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
    WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
)"];

$params = [$mahasiswa_data['id']];
$param_types = 'i';

if (!empty($search)) {
    $where_clauses[] = "(mp.judul LIKE ? OR mp.deskripsi LIKE ? OR mk.nama_matkul LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_matkul)) {
    $where_clauses[] = "mp.kode_matkul = ?";
    $params[] = $filter_matkul;
    $param_types .= 's';
}

if (!empty($filter_jenis)) {
    $where_clauses[] = "mp.jenis_materi = ?";
    $params[] = $filter_jenis;
    $param_types .= 's';
}

if (!empty($filter_pertemuan)) {
    $where_clauses[] = "mp.pertemuan_ke = ?";
    $params[] = (int)$filter_pertemuan;
    $param_types .= 'i';
}

$where_clause = "WHERE " . implode(' AND ', $where_clauses);

// Hitung total materi
$count_sql = "SELECT COUNT(*) 
              FROM materi_perkuliahan mp
              LEFT JOIN matkul mk ON mp.matkul_id = mk.id
              $where_clause";

$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) {
    die("Error prepare count: " . $conn->error);
}

$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

// Statistik materi
$stats_total_sql = "SELECT COUNT(*) as total_materi 
                   FROM materi_perkuliahan mp
                   WHERE mp.matkul_id IN (
                       SELECT DISTINCT jk.matkul_id 
                       FROM krs k 
                       JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                       WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                   )";
$stats_total_stmt = $conn->prepare($stats_total_sql);
$stats_total_stmt->bind_param("i", $mahasiswa_data['id']);
$stats_total_stmt->execute();
$stats_total_stmt->bind_result($total_materi_available);
$stats_total_stmt->fetch();
$stats_total_stmt->close();

$matkul_with_materi_sql = "SELECT COUNT(DISTINCT mp.matkul_id) as total_matkul 
                          FROM materi_perkuliahan mp
                          WHERE mp.matkul_id IN (
                              SELECT DISTINCT jk.matkul_id 
                              FROM krs k 
                              JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                              WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                          )";
$matkul_with_materi_stmt = $conn->prepare($matkul_with_materi_sql);
$matkul_with_materi_stmt->bind_param("i", $mahasiswa_data['id']);
$matkul_with_materi_stmt->execute();
$matkul_with_materi_stmt->bind_result($total_matkul_with_materi);
$matkul_with_materi_stmt->fetch();
$matkul_with_materi_stmt->close();

$recent_upload_sql = "SELECT mp.judul, mp.tanggal_upload, mk.matakuliah as nama_matkul 
                     FROM materi_perkuliahan mp
                     LEFT JOIN matkul mk ON mp.matkul_id = mk.id
                     WHERE mp.matkul_id IN (
                         SELECT DISTINCT jk.matkul_id 
                         FROM krs k 
                         JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                         WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                     )
                     ORDER BY mp.tanggal_upload DESC LIMIT 1";
$recent_upload_stmt = $conn->prepare($recent_upload_sql);
$recent_upload_stmt->bind_param("i", $mahasiswa_data['id']);
$recent_upload_stmt->execute();
$recent_result = $recent_upload_stmt->get_result();
$recent_upload = $recent_result->fetch_assoc();
$recent_upload_stmt->close();

// Query utama untuk menampilkan materi
$materi_sql = "SELECT mp.*, mk.matakuliah as nama_matkul, d.nama as nama_dosen 
              FROM materi_perkuliahan mp
              LEFT JOIN matkul mk ON mp.matkul_id = mk.id
              LEFT JOIN dosen d ON mp.dosen_nip = d.nip
              $where_clause
              ORDER BY mp.tanggal_upload DESC
              LIMIT $offset, $per_page";

$stmt = $conn->prepare($materi_sql);
if (!$stmt) {
    die("Error prepare stmt: " . $conn->error);
}

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Ambil daftar matkul untuk filter dropdown
$matkul_mahasiswa_sql = "SELECT DISTINCT jk.matkul_id as kode_matkul, mk.matakuliah as nama_matkul, mk.sks, d.nama as nama_dosen
                        FROM krs k
                        JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id
                        JOIN matkul mk ON jk.matkul_id = mk.id
                        JOIN dosen d ON jk.dosen_nip = d.nip
                        JOIN mahasiswa m ON k.mahasiswa_id = m.id
                        WHERE m.id = ? AND k.status = 'aktif'
                        ORDER BY mk.matakuliah ASC";
$matkul_mahasiswa_stmt = $conn->prepare($matkul_mahasiswa_sql);
$matkul_mahasiswa_stmt->bind_param("i", $mahasiswa_data['id']);
$matkul_mahasiswa_stmt->execute();
$matkul_mahasiswa_result = $matkul_mahasiswa_stmt->get_result();

// Fungsi untuk format ukuran file
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Fungsi untuk mendapatkan ikon file
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':
            return 'bx bxs-file-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'bx bxs-file-doc text-primary';
        case 'ppt':
        case 'pptx':
            return 'bx bxs-file-ppt text-warning';
        case 'xls':
        case 'xlsx':
            return 'bx bxs-file-xls text-success';
        default:
            return 'bx bxs-file text-secondary';
    }
}

// Handler untuk download file
if (isset($_GET['download']) && isset($_GET['id'])) {
    $materi_id = (int)clean_input($_GET['id']);
    
    $download_sql = "SELECT mp.file_path, mp.file_name 
                    FROM materi_perkuliahan mp
                    WHERE mp.id = ? AND mp.matkul_id IN (
                        SELECT DISTINCT jk.matkul_id 
                        FROM krs k 
                        JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                        WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                    )";
    $download_stmt = $conn->prepare($download_sql);
    $download_stmt->bind_param("ii", $materi_id, $mahasiswa_data['id']);
    $download_stmt->execute();
    $download_result = $download_stmt->get_result();
    $download_data = $download_result->fetch_assoc();
    $download_stmt->close();
    
    if ($download_data && file_exists($download_data['file_path'])) {
        // Set headers untuk download file
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($download_data['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($download_data['file_path']));
        
        // Output file
        readfile($download_data['file_path']);
        exit();
    } else {
        $_SESSION['error_message'] = "File tidak ditemukan atau Anda tidak memiliki akses";
        header("Location: materi.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi Perkuliahan - Portal Akademik</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .nav_submenu a {
            padding-left: 20px !important;
        }
        .materi-card {
            transition: transform 0.3s ease;
        }
        .materi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .materi-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .materi-meta {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        .materi-meta i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        .file-download {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
        }
        .file-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        .file-info {
            overflow: hidden;
        }
        .file-info h6 {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .stats-card {
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-3px);
        }
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body id="body-pd">
    <header class="header" id="header">
        <div class="header_toggle">
            <i class='bx bx-menu' id="header-toggle"></i>
        </div>
        <!-- Welcome Header with Date/Time -->
        <div class="d-flex align-items-center ms-auto">
            <div class="text-end me-3" style="color: white;">
                <small class="d-block"><?php echo $current_date; ?></small>
                <small><?php echo $current_time; ?> WIB</small>
            </div>
        </div>
    </header>
    <div class="l-navbar" id="nav-bar">
        <nav class="nav">
            <div>
                <a href="#" class="nav_logo">
                    <i class="bx bx-layer nav_logo-icon"></i>
                    <span class="nav_logo-name">UIN Sumatera Utara</span><br />
                    <span class="nav_logo-name">Mahasiswa Portal</span>
                </a>
                <div class="nav_list">
                    <a href="../dashboard.php" class="nav_link">
                        <i class="bx bx-home"></i>
                        <span class="nav_name">Dashboard</span>
                    </a>
                    <a href="#" class="nav_link">
                        <i class="bx bxs-user"></i>
                        <span class="nav_name">Biodata</span>
                    </a>
                    <div class="accordion-item bg-transparent border-0">
                        <a class="nav_link collapsed active" data-bs-toggle="collapse" href="#submenuAkademik" role="button" aria-expanded="false" aria-controls="submenuAkademik">
                            <i class="bx bxs-graduation"></i>
                            <span class="nav_name d-flex align-items-center w-100">Akademik <i class="bx bx-chevron-right arrow ms-auto"></i></span>
                        </a>
                        <div class="collapse nav_submenu ps-4" id="submenuAkademik">
                            <a href="krs.php" class="nav_link">• Kartu Rencana Studi</a>
                            <a href="khs.php" class="nav_link">• Kartu Hasil Studi</a>
                            <a href="jadwal-kuliah.php" class="nav_link">• Jadwal Kuliah</a>
                            <a href="#" class="nav_link active">• Materi Kuliah</a>
                        </div>
                    </div>
                    <a href="#" class="nav_link">
                        <i class="bx bxs-megaphone"></i>
                        <span class="nav_name">Pengumuman</span>
                    </a>
                </div>
            </div>
            <a href="../../../logout.php" class="nav_link">
                <i class='bx bx-log-out nav_icon'></i>
                <span class="nav_name">Logout</span>
            </a>
        </nav>
    </div>
    <main>
            <!-- Alert Messages -->
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Materi Perkuliahan</h2>
                    <p class="text-muted">Akses materi kuliah dari mata kuliah yang Anda ambil</p>
                </div>
                <div class="text-end">
                    <small class="text-muted">Total: <?php echo $total_materi_available; ?> materi</small>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-opacity-10 text-success me-5" style="border: 1px solid #00cc661a;">
                                    <i class="bx bxs-book-open"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Total Materi</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $total_materi_available; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card fade-in" style="animation-delay: 0.1s">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-opacity-10 text-success me-5" style="border: 1px solid #00cc661a;">
                                    <i class="bx bxs-graduation"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Mata Kuliah</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $total_matkul_with_materi; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card fade-in" style="animation-delay: 0.2s">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon  bg-opacity-10 text-success me-5" style="border: 1px solid #00cc661a;">
                                    <i class="bx bx-alarm"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Terbaru</h6>
                                    <p class="mb-0 fw-bold">
                                        <?php echo $recent_upload ? date('d M Y', strtotime($recent_upload['tanggal_upload'])) : 'Belum ada'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="card search-card mb-4 fade-in" style="animation-delay: 0.3s">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label fw-semibold">Cari Materi</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Judul materi atau deskripsi">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="matkul" class="form-label fw-semibold">Mata Kuliah</label>
                                <select class="form-select" id="matkul" name="matkul">
                                    <option value="">Semua Mata Kuliah</option>
                                    <?php while ($matkul = $matkul_mahasiswa_result->fetch_assoc()): ?>
                                    <option value="<?php echo $matkul['kode_matkul']; ?>" 
                                        <?php echo ($filter_matkul == $matkul['kode_matkul']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="jenis" class="form-label fw-semibold">Jenis Materi</label>
                                <select class="form-select" id="jenis" name="jenis">
                                    <option value="">Semua Jenis</option>
                                    <option value="Slide" <?php echo ($filter_jenis == 'Slide') ? 'selected' : ''; ?>>Slide</option>
                                    <option value="Dokumen" <?php echo ($filter_jenis == 'Dokumen') ? 'selected' : ''; ?>>Dokumen</option>
                                    <option value="Video" <?php echo ($filter_jenis == 'Video') ? 'selected' : ''; ?>>Video</option>
                                    <option value="Lainnya" <?php echo ($filter_jenis == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="pertemuan" class="form-label fw-semibold">Pertemuan</label>
                                <select class="form-select" id="pertemuan" name="pertemuan">
                                    <option value="">Semua</option>
                                    <?php for($i = 1; $i <= 16; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($filter_pertemuan == $i) ? 'selected' : ''; ?>>
                                        Pertemuan <?php echo $i; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Materials List -->
            <div class="row fade-in" style="animation-delay: 0.4s">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($materi = $result->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card materi-card h-100">
                            <div class="card-header materi-header py-3">
                                <h5 class="mb-0 text-truncate"><?php echo htmlspecialchars($materi['judul']); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="materi-meta">
                                    <i class="fas fa-book text-primary"></i>
                                    <span><?php echo htmlspecialchars($materi['nama_matkul']); ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-user-tie text-secondary"></i>
                                    <span><?php echo htmlspecialchars($materi['nama_dosen']); ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-calendar-alt text-info"></i>
                                    <span><?php echo date('d M Y', strtotime($materi['tanggal_upload'])); ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-layer-group text-warning"></i>
                                    <span>Pertemuan <?php echo $materi['pertemuan_ke']; ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-tag text-success"></i>
                                    <span class="badge bg-light text-dark"><?php echo $materi['jenis_materi']; ?></span>
                                </div>
                                
                                <p class="mt-3 mb-4"><?php echo nl2br(htmlspecialchars($materi['deskripsi'])); ?></p>
                                
                                <?php if (!empty($materi['file_path'])): ?>
                                <div class="file-download d-flex align-items-center">
                                    <div class="file-icon">
                                        <i class="<?php echo getFileIcon($materi['file_name']); ?>"></i>
                                    </div>
                                    <div class="file-info flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($materi['file_name']); ?></h6>
                                        <small class="text-muted"><?php echo formatFileSize($materi['file_size']); ?></small>
                                    </div>
                                    <div class="file-action">
                                        <a href="materi.php?download=1&id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bx bxs-file-blank bx-lg text-muted mb-3"></i>
                                <h4 class="text-muted">Tidak ada materi ditemukan</h4>
                                <p class="text-muted">Coba gunakan kata kunci atau filter yang berbeda</p>
                                <a href="materi.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-sync-alt me-2"></i>Reset Pencarian
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4 fade-in" style="animation-delay: 0.5s">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&matkul=<?php echo urlencode($filter_matkul); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&pertemuan=<?php echo urlencode($filter_pertemuan); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&matkul=<?php echo urlencode($filter_matkul); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&pertemuan=<?php echo urlencode($filter_pertemuan); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&matkul=<?php echo urlencode($filter_matkul); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&pertemuan=<?php echo urlencode($filter_pertemuan); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
    </main>
    
    <script src="../../../js/script.js"></script>
    <script>
        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

<?php
// Tutup koneksi database
$stmt->close();
$mahasiswa_stmt->close();
$conn->close();
?>