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

$user_id = $_SESSION['user_id'];

// Ambil data dosen berdasarkan user_id
$dosen_sql = "SELECT * FROM dosen WHERE user_id = ?";
$dosen_stmt = $conn->prepare($dosen_sql);
$dosen_stmt->bind_param("i", $user_id);
$dosen_stmt->execute();
$dosen_result = $dosen_stmt->get_result();
$dosen_data = $dosen_result->fetch_assoc();
$dosen_stmt->close();

if (!$dosen_data) {
    header("Location: ../../login.php");
    exit();
}

// Fungsi untuk memverifikasi apakah dosen mengampu jadwal tertentu
function verifyDosenJadwal($conn, $nip, $jadwal_id) {
    $sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Error preparing statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("is", $jadwal_id, $nip);
    if (!$stmt->execute()) {
        error_log("Error executing statement: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    return $count > 0;
}

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Operasi CRUD
// 1. Create - Tambah Data Nilai Baru
if (isset($_POST['tambah_nilai'])) {
    // Validasi input
    $mahasiswa_id = filter_input(INPUT_POST, 'mahasiswa_id', FILTER_VALIDATE_INT);
    $jadwal_id = filter_input(INPUT_POST, 'jadwal_id', FILTER_VALIDATE_INT);
    $nilai_tugas = filter_input(INPUT_POST, 'nilai_tugas', FILTER_VALIDATE_FLOAT);
    $nilai_uts = filter_input(INPUT_POST, 'nilai_uts', FILTER_VALIDATE_FLOAT);
    $nilai_uas = filter_input(INPUT_POST, 'nilai_uas', FILTER_VALIDATE_FLOAT);
    
    // Validasi dasar
    if (!$mahasiswa_id || !$jadwal_id || $nilai_tugas === false || 
        $nilai_uts === false || $nilai_uas === false) {
        $_SESSION['error_message'] = "Input tidak valid!";
        header("Location: input-nilai.php");
        exit();
    }
    
    // Validasi range nilai
    if ($nilai_tugas < 0 || $nilai_tugas > 100 || 
        $nilai_uts < 0 || $nilai_uts > 100 || 
        $nilai_uas < 0 || $nilai_uas > 100) {
        $_SESSION['error_message'] = "Nilai harus antara 0-100";
        header("Location: input-nilai.php");
        exit();
    }
    
    // Verifikasi dosen mengampu jadwal ini
    if (!verifyDosenJadwal($conn, $dosen_data['nip'], $jadwal_id)) {
        $_SESSION['error_message'] = "Anda tidak mengampu mata kuliah ini!";
        header("Location: input-nilai.php");
        exit();
    }
    
    // Verifikasi mahasiswa mengambil jadwal ini
    $check_krs = "SELECT id FROM krs WHERE mahasiswa_id = ? AND jadwal_id = ?";
    $stmt = $conn->prepare($check_krs);
    $stmt->bind_param("ii", $mahasiswa_id, $jadwal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['error_message'] = "Mahasiswa tidak terdaftar di mata kuliah ini!";
        $stmt->close();
        header("Location: input-nilai.php");
        exit();
    }
    $stmt->close();
    
    // Dapatkan matkul_id dari jadwal
    $get_matkul = "SELECT matkul_id FROM jadwal_kuliah WHERE id = ?";
    $stmt = $conn->prepare($get_matkul);
    $stmt->bind_param("i", $jadwal_id);
    $stmt->execute();
    $matkul_result = $stmt->get_result();
    $matkul_data = $matkul_result->fetch_assoc();
    $stmt->close();
    
    if (!$matkul_data) {
        $_SESSION['error_message'] = "Data mata kuliah tidak ditemukan!";
        header("Location: input-nilai.php");
        exit();
    }
    
    // Cek apakah nilai sudah ada
    $check_nilai = "SELECT id FROM data_nilai WHERE mahasiswa_id = ? AND jadwal_id = ?";
    $stmt = $conn->prepare($check_nilai);
    $stmt->bind_param("ii", $mahasiswa_id, $jadwal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error_message'] = "Nilai untuk mahasiswa ini sudah ada!";
        $stmt->close();
        header("Location: input-nilai.php");
        exit();
    }
    $stmt->close();
    
    // Insert data
    $insert_sql = "INSERT INTO data_nilai (mahasiswa_id, jadwal_id, matkul_id, nilai_tugas, nilai_uts, nilai_uas, created_by) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error menyiapkan query: " . $conn->error;
        header("Location: input-nilai.php");
        exit();
    }
    
    $stmt->bind_param("iiidddi", $mahasiswa_id, $jadwal_id, $matkul_data['matkul_id'], 
                     $nilai_tugas, $nilai_uts, $nilai_uas, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Nilai berhasil ditambahkan!";
    } else {
        $_SESSION['error_message'] = "Gagal menambahkan nilai: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: input-nilai.php");
    exit();
}

// 2. Update - Edit Data Nilai
if (isset($_POST['edit_nilai'])) {
    $id = intval($_POST['id']);
    $nilai_tugas = floatval($_POST['nilai_tugas']);
    $nilai_uts = floatval($_POST['nilai_uts']);
    $nilai_uas = floatval($_POST['nilai_uas']);
    
    // Verifikasi apakah nilai ini milik jadwal yang diampu oleh dosen ini
    $verify_sql = "SELECT j.id FROM data_nilai dn 
                   JOIN jadwal_kuliah j ON dn.jadwal_id = j.id 
                   WHERE dn.id = ? AND j.dosen_nip = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $id, $dosen_data['nip']);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses untuk mengubah nilai ini!";
        $verify_stmt->close();
        header("Location: input-nilai.php");
        exit();
    }
    $verify_stmt->close();
    
    // Validasi nilai
    if ($nilai_tugas < 0 || $nilai_tugas > 100 || $nilai_uts < 0 || $nilai_uts > 100 || $nilai_uas < 0 || $nilai_uas > 100) {
        $_SESSION['error_message'] = "Nilai harus berada di antara 0 dan 100";
    } else {
        // Update data
        $sql = "UPDATE data_nilai SET nilai_tugas = ?, nilai_uts = ?, nilai_uas = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dddi", $nilai_tugas, $nilai_uts, $nilai_uas, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data nilai berhasil diperbarui!";
        } else {
            $_SESSION['error_message'] = "Gagal memperbarui data nilai: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: input-nilai.php");
    exit();
}

// 3. Delete - Hapus Data Nilai
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // Verifikasi apakah nilai ini milik jadwal yang diampu oleh dosen ini
    $verify_sql = "SELECT j.id FROM data_nilai dn 
                   JOIN jadwal_kuliah j ON dn.jadwal_id = j.id 
                   WHERE dn.id = ? AND j.dosen_nip = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $id, $dosen_data['nip']);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses untuk menghapus nilai ini!";
        $verify_stmt->close();
        header("Location: input-nilai.php");
        exit();
    }
    $verify_stmt->close();
    
    // Delete data
    $sql = "DELETE FROM data_nilai WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Data nilai berhasil dihapus!";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus data nilai: " . $stmt->error;
    }
    $stmt->close();
    header("Location: input-nilai.php");
    exit();
}

// Ambil data mahasiswa yang mengambil mata kuliah dari dosen ini
$mahasiswa_list = [];
$mahasiswa_sql = "SELECT DISTINCT m.id, m.nim, m.nama 
                  FROM mahasiswa m
                  JOIN krs ON m.id = krs.mahasiswa_id
                  JOIN jadwal_kuliah j ON krs.jadwal_id = j.id
                  WHERE j.dosen_nip = ?
                  ORDER BY m.nama ASC";
$mahasiswa_stmt = $conn->prepare($mahasiswa_sql);
$mahasiswa_stmt->bind_param("s", $dosen_data['nip']);
$mahasiswa_stmt->execute();
$mahasiswa_result = $mahasiswa_stmt->get_result();

while ($row = $mahasiswa_result->fetch_assoc()) {
    $mahasiswa_list[] = $row;
}
$mahasiswa_stmt->close();

// Ambil jadwal yang diampu oleh dosen ini
$jadwal_sql = "SELECT j.id, mk.matakuliah, j.hari, j.waktu_mulai, j.waktu_selesai, mk.kelas
               FROM jadwal_kuliah j
               JOIN matkul mk ON j.matkul_id = mk.id
               WHERE j.dosen_nip = ?
               ORDER BY mk.matakuliah ASC";
$jadwal_stmt = $conn->prepare($jadwal_sql);
$jadwal_stmt->bind_param("s", $dosen_data['nip']);
$jadwal_stmt->execute();
$jadwal_result = $jadwal_stmt->get_result();

// Pagination data nilai
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fungsi pencarian
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " AND (m.nim LIKE ? OR m.nama LIKE ? OR mk.matakuliah LIKE ?) ";
    $search_param = "%$search%";
}

// Hitung total record
$count_sql = "SELECT COUNT(*) 
              FROM data_nilai dn
              JOIN mahasiswa m ON dn.mahasiswa_id = m.id
              JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
              JOIN matkul mk ON j.matkul_id = mk.id
              WHERE j.dosen_nip = ?" . $search_condition;
$count_stmt = $conn->prepare($count_sql);

if (!empty($search)) {
    $count_stmt->bind_param("ssss", $dosen_data['nip'], $search_param, $search_param, $search_param);
} else {
    $count_stmt->bind_param("s", $dosen_data['nip']);
}

$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

// Ambil data dengan join dan pagination
if (empty($search)) {
    $sql = "SELECT dn.*, 
                   m.nim, m.nama AS nama_mahasiswa, 
                   mk.matakuliah, mk.sks, 
                   j.hari, j.waktu_mulai, j.waktu_selesai,
                   ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) AS nilai_akhir,
                   CASE 
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 85 THEN 'A'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 80 THEN 'A-'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 75 THEN 'B+'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 70 THEN 'B'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 65 THEN 'B-'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 60 THEN 'C+'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 55 THEN 'C'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 40 THEN 'D'
                       ELSE 'E'
                   END AS grade
            FROM data_nilai dn
            JOIN mahasiswa m ON dn.mahasiswa_id = m.id
            JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
            JOIN matkul mk ON j.matkul_id = mk.id
            WHERE j.dosen_nip = ?
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $dosen_data['nip'], $offset, $per_page);
} else {
    $sql = "SELECT dn.*, 
                   m.nim, m.nama AS nama_mahasiswa, 
                   mk.matakuliah, mk.sks, 
                   j.hari, j.waktu_mulai, j.waktu_selesai,
                   ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) AS nilai_akhir,
                   CASE 
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 85 THEN 'A'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 80 THEN 'A-'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 75 THEN 'B+'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 70 THEN 'B'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 65 THEN 'B-'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 60 THEN 'C+'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 55 THEN 'C'
                       WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 50 THEN 'D'
                       ELSE 'E'
                   END AS grade
            FROM data_nilai dn
            JOIN mahasiswa m ON dn.mahasiswa_id = m.id
            JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
            JOIN matkul mk ON j.matkul_id = mk.id
            WHERE j.dosen_nip = ? AND (m.nim LIKE ? OR m.nama LIKE ? OR mk.matakuliah LIKE ?)
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $dosen_data['nip'], $search_param, $search_param, $search_param, $offset, $per_page);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Input Nilai - Portal Akademik</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
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
                    <a href="#" class="nav_link active">
                        <i class="bx bxs-book-content"></i>
                        <span class="nav_name">Input Nilai</span>
                    </a>
                    <a href="jadwal-mengajar.php" class="nav_link">
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
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show fade-in">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <!-- Main Content -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Daftar Nilai Mahasiswa</h4>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahModal">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Nilai
                    </button>
                </div>

                <!-- Search Form -->
                <div class="card mb-4 fade-in">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" placeholder="Cari berdasarkan NIM, nama mahasiswa, atau mata kuliah..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-search me-2"></i>Cari
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Data Nilai Table -->
                <div class="card fade-in">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Mahasiswa</th>
                                        <th>Mata Kuliah</th>
                                        <th>Tugas</th>
                                        <th>UTS</th>
                                        <th>UAS</th>
                                        <th>Nilai Akhir</th>
                                        <th>Grade</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php $no = $offset + 1; ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="fade-in">
                                                <td><?php echo $no++; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['nama_mahasiswa']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($row['nim']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['matakuliah']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($row['hari']); ?>, 
                                                        <?php echo $row['waktu_mulai']; ?>-<?php echo $row['waktu_selesai']; ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['nilai_tugas']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nilai_uts']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nilai_uas']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nilai_akhir']); ?></td>
                                                <td>
                                                    <span class="grade-badge grade-<?php echo $row['grade']; ?>">
                                                        <?php echo htmlspecialchars($row['grade']); ?>
                                                    </span>
                                                </td>
                                                <td class="action-buttons">
                                                        <a href="#" class="edit" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                                            <i class="bx bxs-edit"></i>
                                                        </a>
                                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus nilai ini?')">
                                                            <i class="bx bxs-trash"></i>
                                                        </a>
                                                </td>
                                            </tr>

                                            <!-- Edit Modal for each row -->
                                            <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title" id="editModalLabel<?php echo $row['id']; ?>">Edit Nilai</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" action="" onsubmit="return validateForm()">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mahasiswa</label>
                                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['nama_mahasiswa'] . ' (' . $row['nim'] . ')'); ?>" readonly>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mata Kuliah</label>
                                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['matakuliah']); ?>" readonly>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-4 mb-3">
                                                                        <label for="nilai_tugas" class="form-label">Nilai Tugas</label>
                                                                        <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_tugas" name="nilai_tugas" value="<?php echo htmlspecialchars($row['nilai_tugas']); ?>" required>
                                                                    </div>
                                                                    <div class="col-md-4 mb-3">
                                                                        <label for="nilai_uts" class="form-label">Nilai UTS</label>
                                                                        <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uts" name="nilai_uts" value="<?php echo htmlspecialchars($row['nilai_uts']); ?>" required>
                                                                    </div>
                                                                    <div class="col-md-4 mb-3">
                                                                        <label for="nilai_uas" class="form-label">Nilai UAS</label>
                                                                        <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uas" name="nilai_uas" value="<?php echo htmlspecialchars($row['nilai_uas']); ?>" required>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" name="edit_nilai" class="btn btn-success">Simpan Perubahan</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr class="fade-in">
                                            <td colspan="9" class="text-center text-muted py-4">Tidak ada data nilai yang ditemukan</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Tambah Nilai Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="tambahModalLabel">Tambah Nilai Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mahasiswa_id" class="form-label">Mahasiswa</label>
                                <select class="form-select" id="mahasiswa_id" name="mahasiswa_id" required>
                                    <option value="">Pilih Mahasiswa</option>
                                    <?php foreach ($mahasiswa_list as $mahasiswa): ?>
                                        <option value="<?php echo $mahasiswa['id']; ?>">
                                            <?php echo htmlspecialchars($mahasiswa['nama'] . ' (' . $mahasiswa['nim'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jadwal_id" class="form-label">Mata Kuliah</label>
                                <select class="form-select" id="jadwal_id" name="jadwal_id" required>
                                    <option value="">Pilih Mata Kuliah</option>
                                    <?php if ($jadwal_result): ?>
                                        <?php while ($jadwal = $jadwal_result->fetch_assoc()): ?>
                                            <option value="<?php echo $jadwal['id']; ?>">
                                                <?php echo htmlspecialchars($jadwal['matakuliah'] . ' - ' . $jadwal['kelas'] . ' (' . $jadwal['hari'] . ' ' . $jadwal['waktu_mulai'] . '-' . $jadwal['waktu_selesai'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                        <?php $jadwal_result->data_seek(0); // Reset pointer untuk digunakan kembali ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row" style="color: white;">
                            <div class="col-md-4 mb-3">
                                <label for="nilai_tugas" class="form-label">Nilai Tugas</label>
                                <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_tugas" name="nilai_tugas" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nilai_uts" class="form-label">Nilai UTS</label>
                                <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uts" name="nilai_uts" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nilai_uas" class="form-label">Nilai UAS</label>
                                <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uas" name="nilai_uas" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_nilai" class="btn btn-success">Simpan Nilai</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="../../../js/script.js"></script>
    <script>
function validateForm() {
    const nilaiInputs = document.querySelectorAll('input[type="number"]');
    let isValid = true;
    
    nilaiInputs.forEach(input => {
        const value = parseFloat(input.value);
        if (isNaN(value) || value < 0 || value > 100) {
            alert('Nilai harus antara 0 dan 100');
            input.focus();
            isValid = false;
        }
    });
    
    return isValid;
}
</script> 
</body> 
</html>

<?php // Tutup koneksi database $stmt->close(); $conn->close(); ?>