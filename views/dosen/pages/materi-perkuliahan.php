<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../../includes/config.php';

// Define clean_input function for input sanitization
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Set timezone to Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current user data
$user_id = $_SESSION['user_id'];

// Verify user is a lecturer and get their NIP
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
    $_SESSION['error_message'] = "You don't have access as a lecturer";
    header("Location: ../dashboard-dosen.php");
    exit();
}

$dosen_nip = $dosen_data['nip'];

// File upload directory setup
$upload_dir = '../../../uploads/materi/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Add course material process
if (isset($_POST['tambah_materi'])) {
    // Sanitize all inputs
    $judul = isset($_POST['judul']) ? clean_input($_POST['judul']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? clean_input($_POST['deskripsi']) : '';
    $kode = isset($_POST['kode']) ? clean_input($_POST['kode']) : '';
    $pertemuan_ke = isset($_POST['pertemuan_ke']) ? (int)clean_input($_POST['pertemuan_ke']) : 0;
    $jenis_materi = isset($_POST['jenis_materi']) ? clean_input($_POST['jenis_materi']) : '';
    $semester = isset($_POST['semester']) ? clean_input($_POST['semester']) : '';
    $tahun_ajaran = isset($_POST['tahun_ajaran']) ? clean_input($_POST['tahun_ajaran']) : '';

    // Validate required fields
    if (empty($judul) || empty($kode) || empty($pertemuan_ke) || 
        empty($jenis_materi) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "All required fields must be filled";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    // Verify course code belongs to this lecturer
    $verify_matkul_sql = "SELECT COUNT(*) as count FROM jadwal_kuliah jk
                         JOIN matkul mk ON jk.matkul_id = mk.id
                         WHERE jk.dosen_nip = ? AND mk.kode = ?";
    $verify_matkul_stmt = $conn->prepare($verify_matkul_sql);
    if (!$verify_matkul_stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    $verify_matkul_stmt->bind_param("ss", $dosen_nip, $kode);
    $verify_matkul_stmt->execute();
    $verify_matkul_result = $verify_matkul_stmt->get_result();
    $matkul_data = $verify_matkul_result->fetch_assoc();
    $verify_matkul_stmt->close();

    if ($matkul_data['count'] == 0) {
        $_SESSION['error_message'] = "You don't teach the selected course";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    $file_path = null;
    $file_name = null;
    $file_size = 0;

    // Handle file upload
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0) {
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $file_info = pathinfo($_FILES['file_materi']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['error_message'] = "File type not allowed. Use: " . implode(', ', $allowed_types);
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        if ($_FILES['file_materi']['size'] > $max_size) {
            $_SESSION['error_message'] = "File size too large. Maximum 10MB";
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        $file_name = $dosen_nip . '_' . $kode . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        $file_size = $_FILES['file_materi']['size'];
        
        if (!move_uploaded_file($_FILES['file_materi']['tmp_name'], $file_path)) {
            $_SESSION['error_message'] = "Failed to upload file";
            header("Location: materi-perkuliahan.php");
            exit();
        }
    }

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert into course_materials table
        $sql = "INSERT INTO materi_perkuliahan (judul, deskripsi, matkul_id, dosen_nip, pertemuan_ke, jenis_materi, file_path, file_name, file_size, semester, tahun_ajaran, tanggal_upload) 
                VALUES (?, ?, (SELECT id FROM matkul WHERE kode = ? LIMIT 1), ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssississs", $judul, $deskripsi, $kode, $dosen_nip, $pertemuan_ke, $jenis_materi, $file_path, $file_name, $file_size, $semester, $tahun_ajaran);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Course material added successfully";
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        $_SESSION['error_message'] = "Failed to add material: " . $e->getMessage();
    }
    header("Location: materi-perkuliahan.php");
    exit();
}

// Edit material process
if (isset($_POST['edit_materi'])) {
    $id = isset($_POST['id']) ? (int)clean_input($_POST['id']) : 0;
    $judul = isset($_POST['judul']) ? clean_input($_POST['judul']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? clean_input($_POST['deskripsi']) : '';
    $kode = isset($_POST['kode']) ? clean_input($_POST['kode']) : '';
    $pertemuan_ke = isset($_POST['pertemuan_ke']) ? (int)clean_input($_POST['pertemuan_ke']) : 0;
    $jenis_materi = isset($_POST['jenis_materi']) ? clean_input($_POST['jenis_materi']) : '';
    $semester = isset($_POST['semester']) ? clean_input($_POST['semester']) : '';
    $tahun_ajaran = isset($_POST['tahun_ajaran']) ? clean_input($_POST['tahun_ajaran']) : '';

    // Validate required fields
    if (empty($judul) || empty($kode) || empty($pertemuan_ke) || 
        empty($jenis_materi) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "All required fields must be filled";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    // Verify ownership
    $check_ownership_sql = "SELECT file_path FROM materi_perkuliahan WHERE id = ? AND dosen_nip = ?";
    $check_ownership_stmt = $conn->prepare($check_ownership_sql);
    $check_ownership_stmt->bind_param("is", $id, $dosen_nip);
    $check_ownership_stmt->execute();
    $ownership_result = $check_ownership_stmt->get_result();
    $old_materi = $ownership_result->fetch_assoc();
    $check_ownership_stmt->close();
    
    if (!$old_materi) {
        $_SESSION['error_message'] = "You don't have permission to edit this material";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    $file_path = $old_materi['file_path'];
    $file_name = null;
    $file_size = 0;

    // Handle new file upload if provided
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0) {
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $file_info = pathinfo($_FILES['file_materi']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['error_message'] = "File type not allowed. Use: " . implode(', ', $allowed_types);
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        if ($_FILES['file_materi']['size'] > $max_size) {
            $_SESSION['error_message'] = "File size too large. Maximum 10MB";
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        $new_file_name = $dosen_nip . '_' . $kode . '_' . time() . '.' . $file_ext;
        $new_file_path = $upload_dir . $new_file_name;
        $file_size = $_FILES['file_materi']['size'];
        
        if (move_uploaded_file($_FILES['file_materi']['tmp_name'], $new_file_path)) {
            // Delete old file if exists
            if ($file_path && file_exists($file_path)) {
                unlink($file_path);
            }
            $file_path = $new_file_path;
            $file_name = $new_file_name;
        } else {
            $_SESSION['error_message'] = "Failed to upload new file";
            header("Location: materi-perkuliahan.php");
            exit();
        }
    }

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "UPDATE materi_perkuliahan SET 
                judul = ?, 
                deskripsi = ?, 
                matkul_id = (SELECT id FROM matkul WHERE kode = ? LIMIT 1), 
                pertemuan_ke = ?, 
                jenis_materi = ?, 
                semester = ?, 
                tahun_ajaran = ?";
        
        $params = [$judul, $deskripsi, $kode, $pertemuan_ke, $jenis_materi, $semester, $tahun_ajaran];
        $param_types = "sssssss";
        
        if ($file_name) {
            $sql .= ", file_path = ?, file_name = ?, file_size = ?";
            $params[] = $file_path;
            $params[] = $file_name;
            $params[] = $file_size;
            $param_types .= "ssi";
        }
        
        $sql .= " WHERE id = ? AND dosen_nip = ?";
        $params[] = $id;
        $params[] = $dosen_nip;
        $param_types .= "is";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param($param_types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Course material updated successfully";
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to update material: " . $e->getMessage();
    }
    header("Location: materi-perkuliahan.php");
    exit();
}

// Delete material process
if (isset($_GET['delete_id'])) {
    $id = isset($_GET['delete_id']) ? (int)clean_input($_GET['delete_id']) : 0;
    
    // Verify ownership and get file path
    $check_ownership_sql = "SELECT file_path FROM materi_perkuliahan WHERE id = ? AND dosen_nip = ?";
    $check_ownership_stmt = $conn->prepare($check_ownership_sql);
    $check_ownership_stmt->bind_param("is", $id, $dosen_nip);
    $check_ownership_stmt->execute();
    $ownership_result = $check_ownership_stmt->get_result();
    $materi_data = $ownership_result->fetch_assoc();
    $check_ownership_stmt->close();
    
    if (!$materi_data) {
        $_SESSION['error_message'] = "You don't have permission to delete this material";
        header("Location: materi-perkuliahan.php");
        exit();
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "DELETE FROM materi_perkuliahan WHERE id = ? AND dosen_nip = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("is", $id, $dosen_nip);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Delete file if exists
        if ($materi_data['file_path'] && file_exists($materi_data['file_path'])) {
            unlink($materi_data['file_path']);
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Course material deleted successfully";
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to delete material: " . $e->getMessage();
    }
    header("Location: materi-perkuliahan.php");
    exit();
}

// Get course data for current lecturer
$matkul_sql = "SELECT DISTINCT mk.kode, mk.matakuliah 
               FROM jadwal_kuliah jk
               JOIN matkul mk ON jk.matkul_id = mk.id
               WHERE jk.dosen_nip = ?
               ORDER BY mk.matakuliah ASC";
$matkul_stmt = $conn->prepare($matkul_sql);
$matkul_stmt->bind_param("s", $dosen_nip);
$matkul_stmt->execute();
$matkul_result = $matkul_stmt->get_result();

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search and filter functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_matkul = isset($_GET['matkul']) ? clean_input($_GET['matkul']) : '';
$filter_jenis = isset($_GET['jenis']) ? clean_input($_GET['jenis']) : '';
$filter_semester = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';
$filter_tahun = isset($_GET['tahun_ajaran']) ? clean_input($_GET['tahun_ajaran']) : '';

// Build where clause for filters
$where_clauses = ["mp.dosen_nip = ?"]; // Always filter by current lecturer
$params = [$dosen_nip];
$param_types = 's';

if (!empty($search)) {
    $where_clauses[] = "(mp.judul LIKE ? OR mp.deskripsi LIKE ? OR mk.matakuliah LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_matkul)) {
    $where_clauses[] = "mk.kode = ?";
    $params[] = $filter_matkul;
    $param_types .= 's';
}

if (!empty($filter_jenis)) {
    $where_clauses[] = "mp.jenis_materi = ?";
    $params[] = $filter_jenis;
    $param_types .= 's';
}

if (!empty($filter_semester)) {
    $where_clauses[] = "mp.semester = ?";
    $params[] = $filter_semester;
    $param_types .= 's';
}

if (!empty($filter_tahun)) {
    $where_clauses[] = "mp.tahun_ajaran = ?";
    $params[] = $filter_tahun;
    $param_types .= 's';
}

$where_clause = "WHERE " . implode(' AND ', $where_clauses);

// Get unique academic years for filter dropdown
$tahun_sql = "SELECT DISTINCT tahun_ajaran FROM materi_perkuliahan WHERE dosen_nip = ? ORDER BY tahun_ajaran DESC";
$tahun_stmt = $conn->prepare($tahun_sql);
$tahun_stmt->bind_param("s", $dosen_nip);
$tahun_stmt->execute();
$tahun_result = $tahun_stmt->get_result();

// Count total records for pagination
$count_sql = "SELECT COUNT(*) 
              FROM materi_perkuliahan mp
              LEFT JOIN matkul mk ON mp.matkul_id = mk.id
              $where_clause";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

// Get data with joins for detailed information
$sql = "SELECT mp.*, mk.matakuliah
        FROM materi_perkuliahan mp
        LEFT JOIN matkul mk ON mp.matkul_id = mk.id
        $where_clause
        ORDER BY mp.tanggal_upload DESC, mp.pertemuan_ke ASC
        LIMIT ?, ?";

// Add parameters for pagination
$params[] = $offset;
$params[] = $per_page;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
// 1. Total materi uploaded
$stats_sql = "SELECT COUNT(*) as total_materi FROM materi_perkuliahan WHERE dosen_nip = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $dosen_nip);
$stats_stmt->execute();
$stats_stmt->bind_result($total_materi);
$stats_stmt->fetch();
$stats_stmt->close();

// 2. Total mata kuliah with materials
$matkul_with_materi_sql = "SELECT COUNT(DISTINCT matkul_id) as total_matkul FROM materi_perkuliahan WHERE dosen_nip = ?";
$matkul_with_materi_stmt = $conn->prepare($matkul_with_materi_sql);
$matkul_with_materi_stmt->bind_param("s", $dosen_nip);
$matkul_with_materi_stmt->execute();
$matkul_with_materi_stmt->bind_result($total_matkul_with_materi);
$matkul_with_materi_stmt->fetch();
$matkul_with_materi_stmt->close();

// 3. Most recent upload
$recent_upload_sql = "SELECT judul, tanggal_upload FROM materi_perkuliahan WHERE dosen_nip = ? ORDER BY tanggal_upload DESC LIMIT 1";
$recent_upload_stmt = $conn->prepare($recent_upload_sql);
$recent_upload_stmt->bind_param("s", $dosen_nip);
$recent_upload_stmt->execute();
$recent_result = $recent_upload_stmt->get_result();
$recent_upload = $recent_result->fetch_assoc();
$recent_upload_stmt->close();

// Function to format file size
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

// Function to get file icon
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':
            return 'bx fa-file-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'bx fa-file-word text-success';
        case 'ppt':
        case 'pptx':
            return 'bx fa-file-powerpoint text-warning';
        case 'xls':
        case 'xlsx':
            return 'bx fa-file-excel text-success';
        default:
            return 'bx fa-file text-danger';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Materi Perkuliahan - Portal Akademik</title>
    
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
            <a href="input-nilai.php" class="nav_link">
              <i class="bx bxs-book-content"></i>
              <span class="nav_name">Input Nilai</span>
            </a>
            <a href="jadwal-mengajar.php" class="nav_link">
              <i class="bx bxs-calendar"></i>
              <span class="nav_name">Jadwal Mengajar</span>
            </a>
            <a href="#" class="nav_link active">
              <i class="bx bxs-book"></i>
              <span class="nav_name">Materi Kuliah</span>
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

      <!-- Main Content -->
      <div class="row mb-4">
          <div class="col-md-12">
              <div class="d-flex justify-content-between align-items-center mb-3">
                  <h4 class="mb-0">Materi Perkuliahan</h4>
                  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahMateriModal">
                      <i class=" fas fa-plus-circle me-2"></i>Tambah Materi
                  </button>
              </div>

              <!-- Statistics Cards -->
              <div class="row mb-4">
                  <div class="col-md-4">
                      <div class="card stats-card fade-in">
                          <div class="card-body">
                              <div class="d-flex align-items-center">
                                  <div class="stats-icon me-3">
                                      <i class="bx bx-book"></i>
                                  </div>
                                  <div>
                                      <h6 class="mb-1">Total Materi</h6>
                                      <h3 class="mb-0"><?php echo $total_materi; ?></h3>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-4">
                      <div class="card stats-card fade-in">
                          <div class="card-body">
                              <div class="d-flex align-items-center">
                                  <div class="stats-icon me-3">
                                      <i class="bx bxs-graduation"></i>
                                  </div>
                                  <div>
                                      <h6 class="mb-1">Mata Kuliah</h6>
                                      <h3 class="mb-0"><?php echo $total_matkul_with_materi; ?></h3>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="col-md-4">
                      <div class="card stats-card fade-in">
                          <div class="card-body">
                              <div class="d-flex align-items-center">
                                  <div class="stats-icon me-3">
                                      <i class="bx bx-time"></i>
                                  </div>
                                  <div>
                                      <h6 class="mb-1">Terakhir Diunggah</h6>
                                      <h5 class="mb-0">
                                          <?php echo $recent_upload ? date('d M Y', strtotime($recent_upload['tanggal_upload'])) : '-'; ?>
                                      </h5>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>

              <!-- Filter Section -->
              <div class="card mb-4 fade-in">
                  <div class="card-body">
                      <form method="GET" action="">
                          <div class="row g-3">
                              <div class="col-md-3">
                                  <label for="search" class="form-label">Cari Materi</label>
                                  <input type="text" class="form-control" id="search" name="search" 
                                         value="<?php echo htmlspecialchars($search); ?>" placeholder="Judul/Deskripsi">
                              </div>
                              <div class="col-md-2">
                                  <label for="matkul" class="form-label">Mata Kuliah</label>
                                  <select class="form-select" id="matkul" name="matkul">
                                      <option value="">Semua</option>
                                      <?php while ($matkul = $matkul_result->fetch_assoc()): ?>
                                      <option value="<?php echo $matkul['kode']; ?>" 
                                          <?php echo ($filter_matkul == $matkul['kode']) ? 'selected' : ''; ?>>
                                          <?php echo htmlspecialchars($matkul['matakuliah']); ?>
                                      </option>
                                      <?php endwhile; ?>
                                  </select>
                              </div>
                              <div class="col-md-2">
                                  <label for="jenis" class="form-label">Jenis Materi</label>
                                  <select class="form-select" id="jenis" name="jenis">
                                      <option value="">Semua</option>
                                      <option value="Slide" <?php echo ($filter_jenis == 'Slide') ? 'selected' : ''; ?>>Slide</option>
                                      <option value="Dokumen" <?php echo ($filter_jenis == 'Dokumen') ? 'selected' : ''; ?>>Dokumen</option>
                                      <option value="Video" <?php echo ($filter_jenis == 'Video') ? 'selected' : ''; ?>>Video</option>
                                      <option value="Lainnya" <?php echo ($filter_jenis == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                  </select>
                              </div>
                              <div class="col-md-2">
                                  <label for="semester" class="form-label">Semester</label>
                                  <select class="form-select" id="semester" name="semester">
                                      <option value="">Semua</option>
                                      <option value="Ganjil" <?php echo ($filter_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                      <option value="Genap" <?php echo ($filter_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                                  </select>
                              </div>
                              <div class="col-md-2">
                                  <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                                  <select class="form-select" id="tahun_ajaran" name="tahun_ajaran">
                                      <option value="">Semua</option>
                                      <?php while ($tahun = $tahun_result->fetch_assoc()): ?>
                                      <option value="<?php echo $tahun['tahun_ajaran']; ?>"
                                          <?php echo ($filter_tahun == $tahun['tahun_ajaran']) ? 'selected' : ''; ?>>
                                          <?php echo htmlspecialchars($tahun['tahun_ajaran']); ?>
                                      </option>
                                      <?php endwhile; ?>
                                  </select>
                              </div>
                              <div class="col-md-1 d-flex align-items-end">
                                  <button type="submit" class="btn btn-success w-100">
                                      <i class="bx bx-filter"></i> Filter
                                  </button>
                              </div>
                          </div>
                      </form>
                  </div>
              </div>

              <!-- Materi Table -->
              <div class="card fade-in">
                  <div class="card-body">
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead class="table-dark">
                                  <tr>
                                      <th width="5%">No</th>
                                      <th width="25%">Judul Materi</th>
                                      <th width="15%">Mata Kuliah</th>
                                      <th width="10%">Pertemuan</th>
                                      <th width="10%">Jenis</th>
                                      <th width="15%">File</th>
                                      <th width="10%">Tanggal</th>
                                      <th width="10%">Aksi</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if ($result->num_rows > 0): ?>
                                      <?php $no = $offset + 1; ?>
                                      <?php while ($row = $result->fetch_assoc()): ?>
                                      <tr>
                                          <td><?php echo $no++; ?></td>
                                          <td>
                                              <strong><?php echo htmlspecialchars($row['judul']); ?></strong>
                                              <div class="materi-detail">
                                                  <small class="text-muted"><?php echo htmlspecialchars($row['deskripsi']); ?></small>
                                              </div>
                                          </td>
                                          <td>
                                              <?php echo htmlspecialchars($row['matakuliah']); ?>
                                              <div class="materi-detail">
                                                  <small><i class="bx bx-calendar-alt"></i> <?php echo $row['semester']; ?> <?php echo $row['tahun_ajaran']; ?></small>
                                              </div>
                                          </td>
                                          <td>
                                              <span class="badge bg-success rounded-pill">Pertemuan <?php echo $row['pertemuan_ke']; ?></span>
                                          </td>
                                          <td>
                                              <?php 
                                              $badge_class = '';
                                              switch($row['jenis_materi']) {
                                                  case 'Slide': $badge_class = 'bg-warning'; break;
                                                  case 'Dokumen': $badge_class = 'bg-info'; break;
                                                  case 'Video': $badge_class = 'bg-danger'; break;
                                                  default: $badge_class = 'bg-danger';
                                              }
                                              ?>
                                              <span class="badge <?php echo $badge_class; ?> rounded-pill"><?php echo $row['jenis_materi']; ?></span>
                                          </td>
                                          <td>
                                              <?php if ($row['file_path']): ?>
                                              <div class="file-info">
                                                  <i class="<?php echo getFileIcon($row['file_name']); ?> file-icon"></i>
                                                  <div>
                                                      <small class="d-block"><?php echo htmlspecialchars($row['file_name']); ?></small>
                                                      <small class="text-muted"><?php echo formatFileSize($row['file_size']); ?></small>
                                                  </div>
                                              </div>
                                              <?php else: ?>
                                                                                        <span class="text-muted">Tidak ada file</span>
                                          <?php endif; ?>
                                      </td>
                                      <td>
                                          <?php echo date('d M Y', strtotime($row['tanggal_upload'])); ?>
                                      </td>
                                      <td>
                                          <div class="d-flex">
                                              <button class="btn btn-sm btn-info me-1" 
                                                      data-bs-toggle="modal" 
                                                      data-bs-target="#editMateriModal" 
                                                      onclick="setEditModalData(
                                                          <?php echo $row['id']; ?>,
                                                          '<?php echo htmlspecialchars($row['judul'], ENT_QUOTES); ?>',
                                                          '<?php echo htmlspecialchars($row['deskripsi'], ENT_QUOTES); ?>',
                                                          '<?php echo htmlspecialchars($row['matakuliah']); ?>',
                                                          <?php echo $row['pertemuan_ke']; ?>,
                                                          '<?php echo $row['jenis_materi']; ?>',
                                                          '<?php echo $row['semester']; ?>',
                                                          '<?php echo $row['tahun_ajaran']; ?>'
                                                      )">
                                                  <i class="bx bxs-edit"></i>
                                              </button>
                                              <a href="materi-perkuliahan.php?delete_id=<?php echo $row['id']; ?>" 
                                                 class="btn btn-sm btn-danger" 
                                                 onclick="return confirm('Apakah Anda yakin ingin menghapus materi ini?')">
                                                  <i class="bx bx-trash-alt"></i>
                                              </a>
                                          </div>
                                      </td>
                                  </tr>
                                  <?php endwhile; ?>
                              <?php else: ?>
                                  <tr>
                                      <td colspan="8" class="text-center py-4">
                                        <div class="text-center py-5">
                                            <i class="bx bx-calendar-x fa-4x text-muted mb-3"></i>
                                          <p class="text-muted">Tidak ada data materi perkuliahan</p>
                                        </div>
                                      </td>
                                  </tr>
                              <?php endif; ?>
                          </tbody>
                      </table>
                  </div>

                  <!-- Pagination -->
                  <?php if ($total_pages > 1): ?>
                  <nav aria-label="Page navigation">
                      <ul class="pagination justify-content-center mt-4">
                          <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                              <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_matkul) ? '&matkul='.urlencode($filter_matkul) : ''; ?><?php echo !empty($filter_jenis) ? '&jenis='.urlencode($filter_jenis) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>">
                                  <i class="bx fa-angle-left"></i>
                              </a>
                          </li>
                          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                              <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                  <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_matkul) ? '&matkul='.urlencode($filter_matkul) : ''; ?><?php echo !empty($filter_jenis) ? '&jenis='.urlencode($filter_jenis) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>">
                                      <?php echo $i; ?>
                                  </a>
                              </li>
                          <?php endfor; ?>
                          <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                              <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_matkul) ? '&matkul='.urlencode($filter_matkul) : ''; ?><?php echo !empty($filter_jenis) ? '&jenis='.urlencode($filter_jenis) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>">
                                  <i class="bx fa-angle-right"></i>
                              </a>
                          </li>
                      </ul>
                  </nav>
                  <?php endif; ?>
              </div>
          </div>
      </div>
  </div>
</main>

<!-- Tambah Materi Modal -->
<div class="modal fade" id="tambahMateriModal" tabindex="-1" aria-labelledby="tambahMateriModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahMateriModalLabel">Tambah Materi Perkuliahan</h5>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="judul" class="form-label">Judul Materi</label>
                            <input type="text" class="form-control" id="judul" name="judul" required>
                        </div>
                        <div class="col-md-6">
                            <label for="kode" class="form-label">Mata Kuliah</label>
                            <select class="form-select" id="kode" name="kode" required>
                                <option value="">Pilih Mata Kuliah</option>
                                <?php 
                                // Reset pointer result set
                                $matkul_result->data_seek(0);
                                while ($matkul = $matkul_result->fetch_assoc()): ?>
                                <option value="<?php echo $matkul['kode']; ?>">
                                    <?php echo htmlspecialchars($matkul['matakuliah']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="deskripsi" class="form-label">Deskripsi Materi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label for="pertemuan_ke" class="form-label">Pertemuan Ke</label>
                            <input type="number" class="form-control" id="pertemuan_ke" name="pertemuan_ke" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label for="jenis_materi" class="form-label">Jenis Materi</label>
                            <select class="form-select" id="jenis_materi" name="jenis_materi" required>
                                <option value="">Pilih Jenis</option>
                                <option value="Slide">Slide</option>
                                <option value="Dokumen">Dokumen</option>
                                <option value="Video">Video</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Pilih Semester</option>
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                            <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2023/2024" required>
                        </div>
                        <div class="col-12">
                            <label for="file_materi" class="form-label">File Materi (Opsional)</label>
                            <input type="file" class="form-control" id="file_materi" name="file_materi">
                            <div class="form-text">Format yang didukung: PDF, DOC, PPT, XLS (Maks. 10MB)</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" name="tambah_materi">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Materi Modal -->
<div class="modal fade" id="editMateriModal" tabindex="-1" aria-labelledby="editMateriModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMateriModalLabel">Edit Materi Perkuliahan</h5>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_judul" class="form-label">Judul Materi</label>
                            <input type="text" class="form-control" id="edit_judul" name="judul" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_kode" class="form-label">Mata Kuliah</label>
                            <select class="form-select" id="edit_kode" name="kode" required>
                                <option value="">Pilih Mata Kuliah</option>
                                <?php 
                                // Reset pointer result set
                                $matkul_result->data_seek(0);
                                while ($matkul = $matkul_result->fetch_assoc()): ?>
                                <option value="<?php echo $matkul['kode']; ?>">
                                    <?php echo htmlspecialchars($matkul['matakuliah']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="edit_deskripsi" class="form-label">Deskripsi Materi</label>
                            <textarea class="form-control" id="edit_deskripsi" name="deskripsi" rows="3"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_pertemuan_ke" class="form-label">Pertemuan Ke</label>
                            <input type="number" class="form-control" id="edit_pertemuan_ke" name="pertemuan_ke" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_jenis_materi" class="form-label">Jenis Materi</label>
                            <select class="form-select" id="edit_jenis_materi" name="jenis_materi" required>
                                <option value="">Pilih Jenis</option>
                                <option value="Slide">Slide</option>
                                <option value="Dokumen">Dokumen</option>
                                <option value="Video">Video</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_semester" class="form-label">Semester</label>
                            <select class="form-select" id="edit_semester" name="semester" required>
                                <option value="">Pilih Semester</option>
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_tahun_ajaran" class="form-label">Tahun Ajaran</label>
                            <input type="text" class="form-control" id="edit_tahun_ajaran" name="tahun_ajaran" required>
                        </div>
                        <div class="col-12">
                            <label for="edit_file_materi" class="form-label">File Materi Baru (Opsional)</label>
                            <input type="file" class="form-control" id="edit_file_materi" name="file_materi">
                            <div class="form-text">Biarkan kosong jika tidak ingin mengubah file</div>
                            <div id="current_file" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" name="edit_materi">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../../../js/script.js"></script>
<script>
    // Function to set edit modal data
    function setEditModalData(id, judul, deskripsi, matkul, pertemuan, jenis, semester, tahun) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_judul').value = judul;
        document.getElementById('edit_deskripsi').value = deskripsi;
        document.getElementById('edit_kode').value = matkul;
        document.getElementById('edit_pertemuan_ke').value = pertemuan;
        document.getElementById('edit_jenis_materi').value = jenis;
        document.getElementById('edit_semester').value = semester;
        document.getElementById('edit_tahun_ajaran').value = tahun;
        
        // You can add AJAX here to get current file info if needed
        document.getElementById('current_file').innerHTML = '';
    }

    // Auto format tahun ajaran input
    document.getElementById('tahun_ajaran').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 4) {
            value = value.substring(0, 4) + '/' + value.substring(4, 8);
        }
        e.target.value = value;
    });

    document.getElementById('edit_tahun_ajaran').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 4) {
            value = value.substring(0, 4) + '/' + value.substring(4, 8);
        }
        e.target.value = value;
    });
</script>
</body> </html><?php // Close database connection $conn->close(); // Function to clean input data function clean_input($data) { $data = trim($data); $data = stripslashes($data); $data = htmlspecialchars($data); return $data; } ?>