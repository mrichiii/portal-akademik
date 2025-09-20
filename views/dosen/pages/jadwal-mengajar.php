<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../../includes/config.php';

// Set timezone ke Waktu Indonesia Barat (Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Verifikasi koneksi database
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Get current user data
$user_id = $_SESSION['user_id'];

// Verify the user is a lecturer and get their NIP
$verify_dosen_sql = "SELECT d.nip FROM dosen d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE u.id = ? AND u.role = 'dosen'";
$verify_dosen_stmt = $conn->prepare($verify_dosen_sql);
$verify_dosen_stmt->bind_param("i", $user_id);
$verify_dosen_stmt->execute();
$verify_dosen_result = $verify_dosen_stmt->get_result();
$dosen_data = $verify_dosen_result->fetch_assoc();
$verify_dosen_stmt->close();

if (!$dosen_data) {
    $_SESSION['error_message'] = "Anda tidak memiliki akses sebagai dosen";
    header("Location: ../dashboard-dosen.php");
    exit();
}

$dosen_nip = $dosen_data['nip'];

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Fungsi untuk memverifikasi apakah dosen mengampu jadwal tertentu
function verifyDosenJadwal($conn, $nip, $jadwal_id) {
    $sql = "SELECT id FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt !== false) {
        $stmt->bind_param("is", $jadwal_id, $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    return false;
}

// Operasi CRUD
// 1. Tambah Jadwal
if (isset($_POST['tambah_jadwal'])) {
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $matkul_id = clean_input($_POST['matkul_id']);
    $ruangan = clean_input($_POST['ruangan']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($matkul_id) || 
        empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                  WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                  AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                       (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                       (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ssssssssss", $dosen_nip, $hari, $semester, $tahun_ajaran, 
                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                      $waktu_mulai, $waktu_selesai);
    $stmt->execute();
    $stmt->bind_result($conflict);
    $stmt->fetch();
    $stmt->close();
    
    if ($conflict > 0) {
        $_SESSION['error_message'] = "Anda sudah memiliki jadwal mengajar pada hari dan waktu yang sama";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                 (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                 (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $stmt = $conn->prepare($check_ruangan_sql);
        $stmt->bind_param("ssssssssss", $ruangan, $hari, $semester, $tahun_ajaran, 
                          $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                          $waktu_mulai, $waktu_selesai);
        $stmt->execute();
        $stmt->bind_result($ruangan_conflict);
        $stmt->fetch();
        $stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwal-mengajar.php");
            exit();
        }
    }

    // Insert data
    $sql = "INSERT INTO jadwal_kuliah (hari, waktu_mulai, waktu_selesai, matkul_id, ruangan, dosen_nip, semester, tahun_ajaran) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $hari, $waktu_mulai, $waktu_selesai, $matkul_id, $ruangan, $dosen_nip, $semester, $tahun_ajaran);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Jadwal mengajar berhasil ditambahkan";
    } else {
        $_SESSION['error_message'] = "Gagal menambahkan jadwal mengajar: " . $stmt->error;
    }
    $stmt->close();
    header("Location: jadwal-mengajar.php");
    exit();
}

// 2. Edit Jadwal
if (isset($_POST['edit_jadwal'])) {
    $id = intval($_POST['id']);
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $matkul_id = clean_input($_POST['matkul_id']);
    $ruangan = clean_input($_POST['ruangan']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($matkul_id) || 
        empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Verifikasi kepemilikan jadwal
    if (!verifyDosenJadwal($conn, $dosen_nip, $id)) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses untuk mengedit jadwal ini";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                  WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                  AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                   (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                   (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ssssissssss", $dosen_nip, $hari, $semester, $tahun_ajaran, $id,
                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                      $waktu_mulai, $waktu_selesai);
    $stmt->execute();
    $stmt->bind_result($conflict);
    $stmt->fetch();
    $stmt->close();
    
    if ($conflict > 0) {
        $_SESSION['error_message'] = "Anda sudah memiliki jadwal mengajar pada hari dan waktu yang sama";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                             (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                             (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $stmt = $conn->prepare($check_ruangan_sql);
        $stmt->bind_param("ssssissssss", $ruangan, $hari, $semester, $tahun_ajaran, $id,
                          $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                          $waktu_mulai, $waktu_selesai);
        $stmt->execute();
        $stmt->bind_result($ruangan_conflict);
        $stmt->fetch();
        $stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwal-mengajar.php");
            exit();
        }
    }

    // Update data
    $sql = "UPDATE jadwal_kuliah SET 
            hari = ?, 
            waktu_mulai = ?, 
            waktu_selesai = ?, 
            matkul_id = ?, 
            ruangan = ?, 
            semester = ?, 
            tahun_ajaran = ? 
            WHERE id = ? AND dosen_nip = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $hari, $waktu_mulai, $waktu_selesai, $matkul_id, $ruangan, $semester, $tahun_ajaran, $id, $dosen_nip);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Jadwal mengajar berhasil diperbarui";
    } else {
        $_SESSION['error_message'] = "Gagal memperbarui jadwal mengajar: " . $stmt->error;
    }
    $stmt->close();
    header("Location: jadwal-mengajar.php");
    exit();
}

// 3. Hapus Jadwal
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // Verifikasi kepemilikan jadwal
    if (!verifyDosenJadwal($conn, $dosen_nip, $id)) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses untuk menghapus jadwal ini";
        header("Location: jadwal-mengajar.php");
        exit();
    }
    
    // Delete data
    $sql = "DELETE FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $id, $dosen_nip);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Jadwal mengajar berhasil dihapus";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus jadwal mengajar: " . $stmt->error;
    }
    $stmt->close();
    header("Location: jadwal-mengajar.php");
    exit();
}

// Ambil data mata kuliah untuk dropdown
$matkul_sql = "SELECT id, matakuliah FROM matkul ORDER BY matakuliah ASC";
$matkul_result = $conn->query($matkul_sql);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Hitung total jadwal untuk dosen ini
$count_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE dosen_nip = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("s", $dosen_nip);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

// Ambil data jadwal untuk dosen ini dengan join ke tabel matkul
$sql = "SELECT jk.*, mk.matakuliah 
        FROM jadwal_kuliah jk
        JOIN matkul mk ON jk.matkul_id = mk.id
        WHERE jk.dosen_nip = ?
        ORDER BY 
        CASE jk.hari 
            WHEN 'Senin' THEN 1
            WHEN 'Selasa' THEN 2
            WHEN 'Rabu' THEN 3
            WHEN 'Kamis' THEN 4
            WHEN 'Jumat' THEN 5
            WHEN 'Sabtu' THEN 6
            WHEN 'Minggu' THEN 7
        END,
        jk.waktu_mulai ASC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $dosen_nip, $offset, $per_page);
$stmt->execute();
$result = $stmt->get_result();

// Hitung statistik
// 1. Total jam mengajar per minggu
$stats_sql = "SELECT SUM(TIME_TO_SEC(TIMEDIFF(waktu_selesai, waktu_mulai))/3600) as total_jam
              FROM jadwal_kuliah 
              WHERE dosen_nip = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $dosen_nip);
$stats_stmt->execute();
$stats_stmt->bind_result($total_jam);
$stats_stmt->fetch();
$stats_stmt->close();

// 2. Total mata kuliah yang diajar
$matkul_count_sql = "SELECT COUNT(DISTINCT matkul_id) as total_matkul
                     FROM jadwal_kuliah 
                     WHERE dosen_nip = ?";
$matkul_count_stmt = $conn->prepare($matkul_count_sql);
$matkul_count_stmt->bind_param("s", $dosen_nip);
$matkul_count_stmt->execute();
$matkul_count_stmt->bind_result($total_matkul);
$matkul_count_stmt->fetch();
$matkul_count_stmt->close();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Jadwal Mengajar - Portal Akademik</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
        }
        .table th {
            background-color: #343a40;
            color: white;
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.075);
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .btn-group {
            display: flex;
            gap: 5px;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .pagination {
            margin-top: 20px;
        }
        .page-item.active .page-link {
            background-color: #343a40;
            border-color: #343a40;
        }
        .page-link {
            color: #343a40;
        }
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.5;
        }
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.5s ease-in forwards;
        }
        @keyframes fadeIn {
            to { opacity: 1; }
        }
    </style>
</head>
<body id="body-pd">
    <header class="header" id="header">
        <div class="header_toggle">
            <i class="bx bx-menu" id="header-toggle"></i>
        </div>
    </header>

    <div class="l-navbar" id="nav-bar">
        <nav class="nav">
            <div>
                <a href="#" class="nav_logo">
                    <i class="bx bx-layer nav_logo-icon"></i>
                    <span class="nav_logo-name">UIN Sumatera Utara</span><br />
                    <span class="nav_logo-name">Dosen Portal</span>
                </a>
                <div class="nav_list">
                    <!-- Dashboard -->
                    <a href="../dashboard.php" class="nav_link">
                        <i class="bx bx-home"></i>
                        <span class="nav_name">Dashboard</span>
                    </a>

                    <!-- Menu Dosen -->
                    <a href="daftar-mahasiswa.php" class="nav_link">
                        <i class="bx bxs-user-detail"></i>
                        <span class="nav_name">Daftar Mahasiswa</span>
                    </a>
                    <a href="input-nilai.php" class="nav_link">
                        <i class="bx bxs-book-content"></i>
                        <span class="nav_name">Input Nilai</span>
                    </a>
                    <a href="jadwal-mengajar.php" class="nav_link active">
                        <i class="bx bxs-calendar"></i>
                        <span class="nav_name">Jadwal Mengajar</span>
                    </a>
                    <a href="profil-dosen.php" class="nav_link">
                        <i class="bx bxs-user"></i>
                        <span class="nav_name">Profil Dosen</span>
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
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show fade-in">
            <i class="bx bxs-check-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show fade-in">
            <i class="bx bxs-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h4 class="mb-3">Jadwal Mengajar</h4>
            </div>
            
            <!-- Total Jam Mengajar -->
            <div class="col-lg-6 mb-4">
                <div class="card fade-in">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Jam Mengajar</h6>
                                <h2 class="mb-0"><?php echo number_format($total_jam, 1); ?></h2>
                                <small>Jam per minggu</small>
                            </div>
                            <div class="stats-icon">
                                <i class="bx bx-time text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Mata Kuliah -->
            <div class="col-lg-6 mb-4">
                <div class="card fade-in">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Mata Kuliah</h6>
                                <h2 class="mb-0"><?php echo $total_matkul; ?></h2>
                                <small>Total yang diajar</small>
                            </div>
                            <div class="stats-icon">
                                <i class="bx bx-book text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jadwal Table -->
        <div class="card mb-4 fade-in">
            <div class="card-header text-white" style="background-color: #343a40;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bx bx-calendar me-2"></i>
                        Daftar Jadwal Mengajar
                    </h5>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahModal">
                        <i class="bx bxs-plus-circle me-2"></i>Tambah Jadwal
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Mata Kuliah</th>
                                    <th>Hari</th>
                                    <th>Waktu</th>
                                    <th>Ruangan</th>
                                    <th>Semester</th>
                                    <th>Tahun Ajaran</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = $offset + 1; ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="fade-in">
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['matakuliah']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['hari']); ?></td>
                                        <td><?php echo date('H:i', strtotime($row['waktu_mulai'])); ?> - <?php echo date('H:i', strtotime($row['waktu_selesai'])); ?></td>
                                        <td><?php echo !empty($row['ruangan']) ? htmlspecialchars($row['ruangan']) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($row['semester']); ?></td>
                                        <td><?php echo htmlspecialchars($row['tahun_ajaran']); ?></td>
                                        <td class="action-buttons">
                                                <a href="#" class="edit" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                                    <i class="bx bxs-edit"></i>
                                                </a>
                                                <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?')">
                                                    <i class="bx bxs-trash"></i>
                                                </a>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal for each row -->
                                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header text-white">
                                                    <h5 class="modal-title">Edit Jadwal Mengajar</h5>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Hari</label>
                                                            <select class="form-select" name="hari" required>
                                                                <option value="Senin" <?php echo ($row['hari'] == 'Senin') ? 'selected' : ''; ?>>Senin</option>
                                                                <option value="Selasa" <?php echo ($row['hari'] == 'Selasa') ? 'selected' : ''; ?>>Selasa</option>
                                                                <option value="Rabu" <?php echo ($row['hari'] == 'Rabu') ? 'selected' : ''; ?>>Rabu</option>
                                                                <option value="Kamis" <?php echo ($row['hari'] == 'Kamis') ? 'selected' : ''; ?>>Kamis</option>
                                                                <option value="Jumat" <?php echo ($row['hari'] == 'Jumat') ? 'selected' : ''; ?>>Jumat</option>
                                                                <option value="Sabtu" <?php echo ($row['hari'] == 'Sabtu') ? 'selected' : ''; ?>>Sabtu</option>
                                                            </select>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Waktu Mulai</label>
                                                                <input type="time" class="form-control" name="waktu_mulai" value="<?php echo date('H:i', strtotime($row['waktu_mulai'])); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Waktu Selesai</label>
                                                                <input type="time" class="form-control" name="waktu_selesai" value="<?php echo date('H:i', strtotime($row['waktu_selesai'])); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Mata Kuliah</label>
                                                            <select class="form-select" name="matkul_id" required>
                                                                <?php 
                                                                // Query ulang data matkul untuk dropdown edit
                                                                $matkul_edit_sql = "SELECT id, matakuliah FROM matkul ORDER BY matakuliah ASC";
                                                                $matkul_edit_result = $conn->query($matkul_edit_sql);
                                                                while ($matkul = $matkul_edit_result->fetch_assoc()): ?>
                                                                    <option value="<?php echo $matkul['id']; ?>" <?php echo ($row['matkul_id'] == $matkul['id']) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($matkul['matakuliah']); ?>
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Ruangan</label>
                                                            <input type="text" class="form-control" name="ruangan" value="<?php echo htmlspecialchars($row['ruangan']); ?>">
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Semester</label>
                                                                <select class="form-select" name="semester" required>
                                                                    <option value="Ganjil" <?php echo ($row['semester'] == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                                                    <option value="Genap" <?php echo ($row['semester'] == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Tahun Ajaran</label>
                                                                <input type="text" class="form-control" name="tahun_ajaran" value="<?php echo htmlspecialchars($row['tahun_ajaran']); ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit_jadwal" class="btn btn-primary">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bx bx-calendar-x fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada jadwal mengajar</h5>
                        <p class="text-muted">Anda belum memiliki jadwal mengajar</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                            <i class="bx bxs-plus-circle me-2"></i>Tambah Jadwal Pertama
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Tambah Jadwal Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title">Tambah Jadwal Mengajar</h5>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Hari</label>
                            <select class="form-select" name="hari" required>
                                <option value="">Pilih Hari</option>
                                <option value="Senin">Senin</option>
                                <option value="Selasa">Selasa</option>
                                <option value="Rabu">Rabu</option>
                                <option value="Kamis">Kamis</option>
                                <option value="Jumat">Jumat</option>
                                <option value="Sabtu">Sabtu</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Waktu Mulai</label>
                                <input type="time" class="form-control" name="waktu_mulai" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Waktu Selesai</label>
                                <input type="time" class="form-control" name="waktu_selesai" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mata Kuliah</label>
                            <select class="form-select" name="matkul_id" required>
                                <option value="">Pilih Mata Kuliah</option>
                                <?php $matkul_result->data_seek(0); ?>
                                <?php while ($matkul = $matkul_result->fetch_assoc()): ?>
<option value="<?php echo $matkul['id']; ?>">
<?php echo htmlspecialchars($matkul['matakuliah']); ?>
</option>
<?php endwhile; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Ruangan</label>
<input type="text" class="form-control" name="ruangan">
</div>
<div class="row mb-3">
<div class="col-md-6">
<label class="form-label">Semester</label>
<select class="form-select" name="semester" required>
<option value="Ganjil">Ganjil</option>
<option value="Genap">Genap</option>
</select>
</div>
<div class="col-md-6">
<label class="form-label">Tahun Ajaran</label>
<input type="text" class="form-control" name="tahun_ajaran" required>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
<button type="submit" name="tambah_jadwal" class="btn btn-primary">Simpan Jadwal</button>
</div>
</form>
</div>
</div>
</div>
<script src="../../../js/script.js"></script>
<script>
    // Animasi fade-in untuk konten
    document.addEventListener('DOMContentLoaded', function() {
        const fadeElements = document.querySelectorAll('.fade-in');
        fadeElements.forEach((element, index) => {
            setTimeout(() => {
                element.style.opacity = 1;
            }, index * 100);
        });
    });

    // Validasi waktu mulai dan selesai
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const waktuMulai = this.querySelector('input[name="waktu_mulai"]');
            const waktuSelesai = this.querySelector('input[name="waktu_selesai"]');
            
            if (waktuMulai && waktuSelesai) {
                if (waktuMulai.value >= waktuSelesai.value) {
                    alert('Waktu mulai harus lebih awal dari waktu selesai');
                    e.preventDefault();
                }
            }
        });
    });
</script>
</body> </html><?php // Tutup koneksi database $stmt->close(); $conn->close(); ?>