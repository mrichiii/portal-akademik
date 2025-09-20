<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../../../includes/config.php';
// Verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Input sanitization function
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Add jadwal kuliah process
if (isset($_POST['tambah_jadwal'])) {
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $matkul_id = (int)clean_input($_POST['matkul_id']);
    $ruangan = clean_input($_POST['ruangan']);
    $dosen_nip = clean_input($_POST['dosen_nip']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($matkul_id) || 
        empty($dosen_nip) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwal-kuliah.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwal-kuliah.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_dosen_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                      WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                      AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                           (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                           (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $check_dosen_stmt = $conn->prepare($check_dosen_sql);
    $check_dosen_stmt->bind_param("ssssssssss", $dosen_nip, $hari, $semester, $tahun_ajaran, 
                                $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                $waktu_mulai, $waktu_selesai);
    $check_dosen_stmt->execute();
    $check_dosen_stmt->bind_result($dosen_conflict);
    $check_dosen_stmt->fetch();
    $check_dosen_stmt->close();
    
    if ($dosen_conflict > 0) {
        $_SESSION['error_message'] = "Dosen sudah memiliki jadwal pada hari dan waktu yang sama";
        header("Location: jadwal-kuliah.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $check_ruangan_stmt = $conn->prepare($check_ruangan_sql);
        $check_ruangan_stmt->bind_param("ssssssssss", $ruangan, $hari, $semester, $tahun_ajaran, 
                                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                      $waktu_mulai, $waktu_selesai);
        $check_ruangan_stmt->execute();
        $check_ruangan_stmt->bind_result($ruangan_conflict);
        $check_ruangan_stmt->fetch();
        $check_ruangan_stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwal-kuliah.php");
            exit();
        }
    }

    try {
        $sql = "INSERT INTO jadwal_kuliah (hari, waktu_mulai, waktu_selesai, matkul_id, ruangan, dosen_nip, semester, tahun_ajaran) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissss", $hari, $waktu_mulai, $waktu_selesai, $matkul_id, $ruangan, $dosen_nip, $semester, $tahun_ajaran);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal kuliah berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan jadwal kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwal-kuliah.php");
    exit();
}

// Edit jadwal kuliah process
if (isset($_POST['edit_jadwal'])) {
    $id = (int)clean_input($_POST['id']);
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $matkul_id = (int)clean_input($_POST['matkul_id']);
    $ruangan = clean_input($_POST['ruangan']);
    $dosen_nip = clean_input($_POST['dosen_nip']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($matkul_id) || 
        empty($dosen_nip) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwal-kuliah.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwal-kuliah.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_dosen_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                      WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                      AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                    (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                    (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $check_dosen_stmt = $conn->prepare($check_dosen_sql);
    $check_dosen_stmt->bind_param("ssssissssss", $dosen_nip, $hari, $semester, $tahun_ajaran, $id,
                                $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                $waktu_mulai, $waktu_selesai);
    $check_dosen_stmt->execute();
    $check_dosen_stmt->bind_result($dosen_conflict);
    $check_dosen_stmt->fetch();
    $check_dosen_stmt->close();
    
    if ($dosen_conflict > 0) {
        $_SESSION['error_message'] = "Dosen sudah memiliki jadwal pada hari dan waktu yang sama";
        header("Location: jadwal-kuliah.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                         (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                         (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $check_ruangan_stmt = $conn->prepare($check_ruangan_sql);
        $check_ruangan_stmt->bind_param("ssssissssss", $ruangan, $hari, $semester, $tahun_ajaran, $id,
                                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                      $waktu_mulai, $waktu_selesai);
        $check_ruangan_stmt->execute();
        $check_ruangan_stmt->bind_result($ruangan_conflict);
        $check_ruangan_stmt->fetch();
        $check_ruangan_stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwal-kuliah.php");
            exit();
        }
    }

    try {
        $sql = "UPDATE jadwal_kuliah SET 
                hari = ?, 
                waktu_mulai = ?, 
                waktu_selesai = ?, 
                matkul_id = ?, 
                ruangan = ?, 
                dosen_nip = ?, 
                semester = ?, 
                tahun_ajaran = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissssi", $hari, $waktu_mulai, $waktu_selesai, $matkul_id, $ruangan, $dosen_nip, $semester, $tahun_ajaran, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal kuliah berhasil diperbarui";
        } else {
            throw new Exception("Gagal memperbarui jadwal kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwal-kuliah.php");
    exit();
}

// Delete jadwal kuliah process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    try {
        // Check if this schedule has any related records in KRS
        $check_krs_sql = "SELECT COUNT(*) FROM krs WHERE jadwal_id = ?";
        $check_krs_stmt = $conn->prepare($check_krs_sql);
        $check_krs_stmt->bind_param("i", $id);
        $check_krs_stmt->execute();
        $check_krs_stmt->bind_result($krs_count);
        $check_krs_stmt->fetch();
        $check_krs_stmt->close();
        
        if ($krs_count > 0) {
            throw new Exception("Jadwal tidak dapat dihapus karena sudah digunakan dalam KRS mahasiswa");
        }
        
        $sql = "DELETE FROM jadwal_kuliah WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal kuliah berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus jadwal kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwal-kuliah.php");
    exit();
}

// Get data for dropdowns
// Get mata kuliah data
$matkul_sql = "SELECT id, kelas, matakuliah FROM matkul ORDER BY matakuliah ASC";
$matkul_result = $conn->query($matkul_sql);

// Get dosen data
$dosen_sql = "SELECT nip, nama FROM dosen ORDER BY nama ASC";
$dosen_result = $conn->query($dosen_sql);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records for pagination
$count_sql = "SELECT COUNT(*) FROM jadwal_kuliah";
$total_rows = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

// Filter by semester and tahun_ajaran
$filter_semester = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';
$filter_tahun = isset($_GET['tahun_ajaran']) ? clean_input($_GET['tahun_ajaran']) : '';

// Build where clause for filters
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_clauses[] = "(m.matakuliah LIKE ? OR d.nama LIKE ? OR j.ruangan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_semester)) {
    $where_clauses[] = "j.semester = ?";
    $params[] = $filter_semester;
    $param_types .= 's';
}

if (!empty($filter_tahun)) {
    $where_clauses[] = "j.tahun_ajaran = ?";
    $params[] = $filter_tahun;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_clauses)) {
    $where_clause = "WHERE " . implode(' AND ', $where_clauses);
}

// Get unique tahun ajaran for filter dropdown
$tahun_sql = "SELECT DISTINCT tahun_ajaran FROM jadwal_kuliah ORDER BY tahun_ajaran DESC";
$tahun_result = $conn->query($tahun_sql);

// Get data with joins for detailed information
$sql = "SELECT j.*, m.kelas as kelas_matkul, m.matakuliah, d.nama as nama_dosen
        FROM jadwal_kuliah j
        LEFT JOIN matkul m ON j.matkul_id = m.id
        LEFT JOIN dosen d ON j.dosen_nip = d.nip
        $where_clause
        ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.waktu_mulai ASC
        LIMIT ?, ?";

// Prepare statement with dynamic parameters
$stmt = $conn->prepare($sql);

// Add parameters for pagination
$params[] = $offset;
$params[] = $per_page;
$param_types .= 'ii';

// Bind parameters if any
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Also update the count for pagination if filters are applied
if (!empty($where_clause)) {
    $count_sql = "SELECT COUNT(*) 
                  FROM jadwal_kuliah j
                  LEFT JOIN matkul m ON j.matkul_id = m.id
                  LEFT JOIN dosen d ON j.dosen_nip = d.nip
                  $where_clause";
    
    $count_stmt = $conn->prepare($count_sql);
    
    // Reset param types to exclude pagination parameters
    $count_param_types = substr($param_types, 0, -2);
    
    // Remove pagination parameters
    array_pop($params);
    array_pop($params);
    
    if (!empty($params)) {
        $count_stmt->bind_param($count_param_types, ...$params);
    }
    
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
    $total_pages = ceil($total_rows / $per_page);
}

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Jadwal Kuliah - Portal Akademik</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
    <style>
        .badge-custom {
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .badge-primary {
            background-color: #1a5632;
            color: white;
        }
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        .action-buttons a {
            margin-right: 5px;
        }
    </style>
</head>

<body id="body-pd">
    <header class="header" id="header">
      <div class="header_toggle">
        <i class="bx bx-menu" id="header-toggle"></i>
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
            <span class="nav_logo-name">UIN Sumatera Utara</span> <br />
            <span class="nav_logo-name">Admin Portal</span>
          </a>
          <div class="nav_list">
            <!-- Dashboard -->
            <a href="../dashboard.php" class="nav_link">
              <i class="bx bx-home"></i>
              <span class="nav_name">Dashboard</span>
            </a>

            <!-- Accordion Container -->
            <div class="accordion" id="sidebarAccordion">
              <!-- Kelola Data -->
              <div class="accordion-item bg-transparent border-0">
                <a
                  class="nav_link collapsed"
                  data-bs-toggle="collapse"
                  href="#submenuKelola"
                  role="button"
                  aria-expanded="false"
                  aria-controls="submenuKelola"
                >
                  <i class="bx bxs-data"></i>
                  <span class="nav_name d-flex align-items-center w-100"
                    >Kelola Data
                    <i class="bx bx-chevron-right arrow ms-auto"></i>
                  </span>
                </a>
                <div
                  class="collapse nav_submenu ps-4"
                  id="submenuKelola"
                  data-bs-parent="#sidebarAccordion"
                >
                  <a href="kelola-mahasiswa.php" class="nav_link"
                    >• Kelola Mahasiswa</a
                  >
                  <a href="kelola-dosen.php" class="nav_link"
                    >• Kelola Dosen</a
                  >
                  <a href="kelola-matakuliah.php" class="nav_link"
                    >• Kelola Matakuliah</a
                  >
                  <a href="jadwal-kuliah.php" class="nav_link active"
                    >• Jadwal Kuliah</a
                  >
                  <a href="data-nilai.php" class="nav_link"
                    >• Data Nilai</a
                  >
                </div>
              </div>

              <!-- Laporan -->
              <div class="accordion-item bg-transparent border-0">
                <a
                  class="nav_link collapsed"
                  data-bs-toggle="collapse"
                  href="#submenuLaporan"
                  role="button"
                  aria-expanded="false"
                  aria-controls="submenuLaporan"
                >
                  <i class="bx bxs-report"></i>
                  <span class="nav_name d-flex align-items-center w-100"
                    >Laporan
                    <i class="bx bx-chevron-right arrow ms-auto"></i>
                  </span>
                </a>
                <div
                  class="collapse nav_submenu ps-4"
                  id="submenuLaporan"
                  data-bs-parent="#sidebarAccordion"
                >
                  <a href="manajemen-user.php" class="nav_link">• Manajemen User</a>
                  <a href="database.php" class="nav_link">• Database</a>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div
          style="display: flex; flex-direction: column; justify-content: center"
        >
        <a href="../../../logout.php" class="nav_link"> <i class='bx bx-log-out nav_icon'></i> <span class="nav_name">LogOut</span> </a>
        </div>
      </nav>
    </div>

    <!-- konten utama -->
    <main class="pt-4">
        <div class="table-responsive">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1>Jadwal Kuliah</h1>
          <button class="btn btn-success" onclick="showAddModal()" style="background-color: #000000;">
            <i class="bx bx-plus"></i> Tambah Jadwal
          </button>
        </div>
        
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
        
        <div class="search-body">
          <form method="GET" class="row g-3">
            <div class="col-md-12">
              <div class="input-group" style="width: 100%; background-color: #000000;">
                <input type="text" class="form-control" name="search" placeholder="Cari mata kuliah, dosen, ruangan..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit">
                  <i class="bx bx-search me-1"></i> Cari
                </button>
              </div>
            </div>
            <?php if (!empty($search) || !empty($filter_semester) || !empty($filter_tahun)): ?>
            <div class="col-12 mt-2">
              <a href="jadwal-kuliah.php" class="btn btn-sm btn-success">
                <i class="bx bx-reset me-1"></i> Reset
              </a>
            </div>
            <?php endif; ?>
          </form>
        </div>
        <br>

        <div class="table-data">
          <div class="users">
            <div class="head">
            </div>
            <table class="table-custom">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Hari</th>
                  <th>Waktu</th>
                  <th>Mata Kuliah</th>
                  <th>Dosen</th>
                  <th>Ruangan</th>
                  <th>Semester/TA</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result->num_rows > 0): ?>
                  <?php $no = $offset + 1; ?>
                  <?php while($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($row['hari']); ?></td>
                    <td>
                      <?php 
                        $waktu_mulai = date("H:i", strtotime($row['waktu_mulai']));
                        $waktu_selesai = date("H:i", strtotime($row['waktu_selesai']));
                        echo $waktu_mulai . ' - ' . $waktu_selesai; 
                      ?>
                    </td>
                    <td>
                      <div class="fw-bold"><?php echo htmlspecialchars($row['kelas_matkul']); ?></div>
                      <?php echo htmlspecialchars($row['matakuliah']); ?>
                    </td>
                    <td>
                      <div class="fw-bold"><?php echo htmlspecialchars($row['dosen_nip']); ?></div>
                      <?php echo htmlspecialchars($row['nama_dosen']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['ruangan'] ?: '-'); ?></td>
                    <td>
                      <span class="badge badge-primary badge-custom">
                        <?php echo htmlspecialchars($row['semester']); ?>
                      </span>
                      <span class="badge badge-secondary badge-custom">
                        <?php echo htmlspecialchars($row['tahun_ajaran']); ?>
                      </span>
                    </td>
                    <td class="action-buttons">
                      <a href="#" class="edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                        <i class="bx bxs-edit"></i>
                      </a>
                      <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?');">
                        <i class="bx bxs-trash"></i>
                      </a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" style="text-align: center;">
                      <div class="text-muted">
                        <i class="bx bx-calendar-x bx-lg mb-2"></i>
                        <p class="mb-0">Tidak ada data jadwal kuliah ditemukan</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
            
            <!-- Pagination -->
              <?php if ($total_pages > 1): ?>
              <div class="pagination-container mt-3">
                  <nav aria-label="Page navigation">
                      <ul class="pagination justify-content-center">
                          <?php if ($page > 1): ?>
                          <li class="page-item">
                              <a class="page-link" href="?page=<?php echo ($page-1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                  Previous
                              </a>
                          </li>
                          <?php endif; ?>
                          
                          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                          <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                              <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" style="color: <?php echo ($i == $page) ? '#ffffff' : '#ffffff'; ?>; background-color: <?php echo ($i == $page) ? '#00cc6666' : '#000000'; ?>; border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                  <?php echo $i; ?>
                              </a>
                          </li>
                          <?php endfor; ?>
                          
                          <?php if ($page < $total_pages): ?>
                          <li class="page-item">
                              <a class="page-link" href="?page=<?php echo ($page+1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                  Next
                              </a>
                          </li>
                          <?php endif; ?>
                      </ul>
                  </nav>
              </div>
              <?php endif; ?>
          </div>
        </div>
        </div>
      </main>

      <!-- Modal Tambah Jadwal -->
      <div id="addModal" class="modal">
        <div class="modal-content">
          <h2>Tambah Jadwal Kuliah Baru</h2>
          
          <form method="POST" action="">
            <!-- Hari -->
            <div class="form-group">
              <label for="hari">Hari</label>
              <select id="hari" name="hari" required>
                <option value="">-- Pilih Hari --</option>
                <option value="Senin">Senin</option>
                <option value="Selasa">Selasa</option>
                <option value="Rabu">Rabu</option>
                <option value="Kamis">Kamis</option>
                <option value="Jumat">Jumat</option>
                <option value="Sabtu">Sabtu</option>
              </select>
            </div>

            <!-- Waktu Mulai dan Selesai -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="waktu_mulai">Waktu Mulai</label>
                  <input type="time" id="waktu_mulai" name="waktu_mulai" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="waktu_selesai">Waktu Selesai</label>
                  <input type="time" id="waktu_selesai" name="waktu_selesai" required>
                </div>
              </div>
            </div>

            <!-- Mata Kuliah -->
            <div class="form-group">
              <label for="matkul_id">Mata Kuliah</label>
              <select id="matkul_id" name="matkul_id" required>
                <option value="">-- Pilih Mata Kuliah --</option>
                <?php if ($matkul_result && $matkul_result->num_rows > 0): ?>
                  <?php while ($matkul_row = $matkul_result->fetch_assoc()): ?>
                    <option value="<?php echo $matkul_row['id']; ?>">
                      <?php echo htmlspecialchars($matkul_row['kelas']); ?> - <?php echo htmlspecialchars($matkul_row['matakuliah']); ?>
                    </option>
                  <?php endwhile; ?>
                <?php else: ?>
                  <option disabled>Tidak ada mata kuliah tersedia</option>
                <?php endif; ?>
              </select>
            </div>

            <!-- Dosen Pengajar -->
            <div class="form-group">
              <label for="dosen_nip">Dosen Pengajar</label>
              <select id="dosen_nip" name="dosen_nip" required>
                <option value="">-- Pilih Dosen --</option>
                <?php if ($dosen_result && $dosen_result->num_rows > 0): ?>
                  <?php while ($dosen_row = $dosen_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($dosen_row['nip']); ?>">
                      <?php echo htmlspecialchars($dosen_row['nip']); ?> - <?php echo htmlspecialchars($dosen_row['nama']); ?>
                    </option>
                  <?php endwhile; ?>
                <?php else: ?>
                  <option disabled>Tidak ada dosen tersedia</option>
                <?php endif; ?>
              </select>
            </div>

            <!-- Ruangan -->
            <div class="form-group">
              <label for="ruangan">Ruangan</label>
              <input type="text" id="ruangan" name="ruangan" placeholder="Contoh: FST-101" required>
            </div>

            <!-- Semester dan Tahun Ajaran -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="semester">Semester</label>
                  <select id="semester" name="semester" required>
                    <option value="">-- Pilih Semester --</option>
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="tahun_ajaran">Tahun Ajaran</label>
                  <input type="text" id="tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2023/2024" required>
                </div>
              </div>
            </div>

            <div class="form-group">
              <button type="submit" name="tambah_jadwal" class="btn btn-primary">Simpan</button>
              <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Batal</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Edit Jadwal -->
      <div id="editModal" class="modal">
        <div class="modal-content">
          <h2>Edit Jadwal Kuliah</h2>
          
          <form method="POST" action="">
            <input type="hidden" id="edit_id" name="id">
            
            <!-- Hari -->
            <div class="form-group">
              <label for="edit_hari">Hari</label>
              <select id="edit_hari" name="hari" required>
                <option value="">-- Pilih Hari --</option>
                <option value="Senin">Senin</option>
                <option value="Selasa">Selasa</option>
                <option value="Rabu">Rabu</option>
                <option value="Kamis">Kamis</option>
                <option value="Jumat">Jumat</option>
                <option value="Sabtu">Sabtu</option>
              </select>
            </div>

            <!-- Waktu Mulai dan Selesai -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_waktu_mulai">Waktu Mulai</label>
                  <input type="time" id="edit_waktu_mulai" name="waktu_mulai" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_waktu_selesai">Waktu Selesai</label>
                  <input type="time" id="edit_waktu_selesai" name="waktu_selesai" required>
                </div>
              </div>
            </div>

            <!-- Mata Kuliah -->
            <div class="form-group">
              <label for="edit_matkul_id">Mata Kuliah</label>
              <select id="edit_matkul_id" name="matkul_id" required>
                <option value="">-- Pilih Mata Kuliah --</option>
                <?php 
                  // Reset pointer to beginning for reuse
                  $matkul_result->data_seek(0); 
                  while ($matkul_row = $matkul_result->fetch_assoc()): ?>
                  <option value="<?php echo $matkul_row['id']; ?>">
                    <?php echo htmlspecialchars($matkul_row['kelas']); ?> - <?php echo htmlspecialchars($matkul_row['matakuliah']); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <!-- Dosen Pengajar -->
            <div class="form-group">
              <label for="edit_dosen_nip">Dosen Pengajar</label>
              <select id="edit_dosen_nip" name="dosen_nip" required>
                <option value="">-- Pilih Dosen --</option>
                <?php 
                  // Reset pointer to beginning for reuse
                  $dosen_result->data_seek(0); 
                  while ($dosen_row = $dosen_result->fetch_assoc()): ?>
                  <option value="<?php echo htmlspecialchars($dosen_row['nip']); ?>">
                    <?php echo htmlspecialchars($dosen_row['nip']); ?> - <?php echo htmlspecialchars($dosen_row['nama']); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <!-- Ruangan -->
            <div class="form-group">
              <label for="edit_ruangan">Ruangan</label>
              <input type="text" id="edit_ruangan" name="ruangan" placeholder="Contoh: A101">
            </div>

            <!-- Semester dan Tahun Ajaran -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_semester">Semester</label>
                  <select id="edit_semester" name="semester" required>
                    <option value="">-- Pilih Semester --</option>
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_tahun_ajaran">Tahun Ajaran</label>
                  <input type="text" id="edit_tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2023/2024" required>
                </div>
              </div>
            </div>

            <div class="form-group">
              <button type="submit" name="edit_jadwal" class="btn btn-primary">Simpan Perubahan</button>
              <button type="button" class="btn btn-secondary" onclick="hideModal('editModal')">Batal</button>
            </div>
          </form>
        </div>
      </div>

      <script>
        document.addEventListener("DOMContentLoaded", function(event) {
          const showNavbar = (toggleId, navId, bodyId, headerId) => {
            const toggle = document.getElementById(toggleId),
              nav = document.getElementById(navId),
              bodypd = document.getElementById(bodyId),
              headerpd = document.getElementById(headerId);

            // Validate that all variables exist
            if (toggle && nav && bodypd && headerpd) {
              toggle.addEventListener("click", () => {
                // show navbar
                nav.classList.toggle("show");
                // change icon
                toggle.classList.toggle("bx-x");
                // add padding to body
                bodypd.classList.toggle("body-pd");
                // add padding to header
                headerpd.classList.toggle("body-pd");
              });
            }
          };

          showNavbar("header-toggle", "nav-bar", "body-pd", "header");

          /*===== LINK ACTIVE =====*/
          const linkColor = document.querySelectorAll(".nav_link");

          function colorLink() {
            if (linkColor) {
              linkColor.forEach((l) => l.classList.remove("active"));
              this.classList.add("active");
            }
          }
          linkColor.forEach((l) => l.addEventListener("click", colorLink));
        });

        // Modal functions
        function showAddModal() {
          document.getElementById('addModal').style.display = 'block';
        }

        function showEditModal(data) {
          document.getElementById('edit_id').value = data.id;
          document.getElementById('edit_hari').value = data.hari;
          document.getElementById('edit_waktu_mulai').value = data.waktu_mulai.substring(0, 5);
          document.getElementById('edit_waktu_selesai').value = data.waktu_selesai.substring(0, 5);
          document.getElementById('edit_matkul_id').value = data.matkul_id;
          document.getElementById('edit_dosen_nip').value = data.dosen_nip;
          document.getElementById('edit_ruangan').value = data.ruangan;
          document.getElementById('edit_semester').value = data.semester;
          document.getElementById('edit_tahun_ajaran').value = data.tahun_ajaran;
          
          document.getElementById('editModal').style.display = 'block';
        }

        function hideModal(modalId) {
          document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
          if (event.target.className === 'modal') {
            event.target.style.display = 'none';
          }
        }
      </script>
    </body>
</html>
<?php
// Close database connection
$conn->close();
?>