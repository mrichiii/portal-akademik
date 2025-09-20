<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Anda harus login terlebih dahulu";
    header("Location: ../../login.php");
    exit();
}

// Verify user role
if ($_SESSION['role'] !== 'dosen') {
    $_SESSION['error_message'] = "Hanya dosen yang bisa mengakses halaman ini";
    header("Location: ../../login.php");
    exit();
}

require_once '../../../includes/config.php';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Verify database connection
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Get current user data
$current_user_id = $_SESSION['user_id'];
$current_dosen_nip = null;
$current_dosen_nama = null;

// Get user data
$user_sql = "SELECT id, username, role, nama FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);

if ($user_stmt === false) {
    die("Error preparing user statement: " . $conn->error);
}

$user_stmt->bind_param("i", $current_user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['error_message'] = "Data user tidak ditemukan";
    header("Location: ../../login.php");
    exit();
}

$current_user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Get dosen data
$dosen_sql = "SELECT nip, nama, user_id FROM dosen WHERE user_id = ? OR nip = ?";
$dosen_stmt = $conn->prepare($dosen_sql);

if ($dosen_stmt === false) {
    die("Error preparing dosen statement: " . $conn->error);
}

$dosen_stmt->bind_param("is", $current_user_id, $current_user_data['username']);
$dosen_stmt->execute();
$dosen_result = $dosen_stmt->get_result();

if ($dosen_result->num_rows === 0) {
    $_SESSION['error_message'] = "Data dosen tidak ditemukan untuk user ini";
    header("Location: ../../login.php");
    exit();
}

$dosen_data = $dosen_result->fetch_assoc();
$current_dosen_nip = $dosen_data['nip'];
$current_dosen_nama = $dosen_data['nama'];
$dosen_stmt->close();

// Update user_id in dosen table if not set
if ($dosen_data['user_id'] !== $current_user_id) {
    $update_sql = "UPDATE dosen SET user_id = ? WHERE nip = ?";
    $update_stmt = $conn->prepare($update_sql);
    
    if ($update_stmt !== false) {
        $update_stmt->bind_param("is", $current_user_id, $current_dosen_nip);
        $update_stmt->execute();
        $update_stmt->close();
    }
}

// Academic period
$tahun_ajaran_aktif = '2024/2025';
$semester_aktif = 'Genap';

// Get courses taught by this lecturer
$mk_sql = "SELECT j.id as jadwal_id, mk.id as matkul_id, mk.matakuliah, j.hari, j.waktu_mulai, j.waktu_selesai, mk.kelas 
           FROM jadwal_kuliah j
           JOIN matkul mk ON j.matkul_id = mk.id
           WHERE j.dosen_nip = ? 
           AND j.tahun_ajaran = ?
           AND j.semester = ?
           ORDER BY mk.matakuliah ASC";
           
$mk_stmt = $conn->prepare($mk_sql);
if ($mk_stmt === false) {
    die("Error preparing mata kuliah statement: " . $conn->error);
}

$mk_stmt->bind_param("sss", $current_dosen_nip, $tahun_ajaran_aktif, $semester_aktif);
$mk_stmt->execute();
$mata_kuliah_result = $mk_stmt->get_result();
$total_matkul_diampu = $mata_kuliah_result->num_rows;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Get filter values
$filter_jadwal = isset($_GET['jadwal_id']) ? intval($_GET['jadwal_id']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Verify filter_jadwal belongs to this lecturer
if (!empty($filter_jadwal)) {
    $verify_sql = "SELECT id FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    
    if ($verify_stmt === false) {
        die("Error preparing verification statement: " . $conn->error);
    }
    
    $verify_stmt->bind_param("is", $filter_jadwal, $current_dosen_nip);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $filter_jadwal = ''; // Reset filter if not valid
    }
    
    $verify_stmt->close();
}

// Build base query for counting
$count_sql = "SELECT COUNT(DISTINCT m.id) 
              FROM mahasiswa m
              JOIN krs k ON m.id = k.mahasiswa_id
              JOIN jadwal_kuliah j ON k.jadwal_id = j.id
              WHERE j.dosen_nip = ? 
              AND k.tahun_ajaran = ?
              AND k.semester = ?
              AND k.status = 'aktif'";

$count_params = [$current_dosen_nip, $tahun_ajaran_aktif, $semester_aktif];
$count_types = "sss";

// Add filter conditions to count query
if (!empty($filter_jadwal)) {
    $count_sql .= " AND k.jadwal_id = ?";
    $count_params[] = $filter_jadwal;
    $count_types .= "i";
}

if (!empty($search)) {
    $count_sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR m.email LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_types .= "sss";
}

// Execute count query
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    die("Error preparing count statement: " . $conn->error);
}

$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_rows / $per_page);

// Build main query
$sql = "SELECT DISTINCT m.id, m.nim, m.nama, m.email, m.nohp as telepon, m.alamat, 
               GROUP_CONCAT(DISTINCT CONCAT(mk.matakuliah, ' (', mk.kelas, ')') SEPARATOR ', ') as mata_kuliah_diambil,
               COUNT(DISTINCT k.jadwal_id) as jumlah_matkul
        FROM mahasiswa m
        JOIN krs k ON m.id = k.mahasiswa_id
        JOIN jadwal_kuliah j ON k.jadwal_id = j.id
        JOIN matkul mk ON j.matkul_id = mk.id
        WHERE j.dosen_nip = ? 
        AND k.tahun_ajaran = ?
        AND k.semester = ?
        AND k.status = 'aktif'";

$params = [$current_dosen_nip, $tahun_ajaran_aktif, $semester_aktif];
$types = "sss";

// Add filter conditions
if (!empty($filter_jadwal)) {
    $sql .= " AND k.jadwal_id = ?";
    $params[] = $filter_jadwal;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR m.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

// Add grouping, ordering and pagination
$sql .= " GROUP BY m.id, m.nim, m.nama, m.email, m.nohp, m.alamat
          ORDER BY m.nama ASC 
          LIMIT ?, ?";

$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing main statement: " . $conn->error);
}

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');


$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// HTML and display code remains the same as your original
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Daftar Mahasiswa - Portal Akademik</title>
    
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
            <a href="#" class="nav_link active">
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

      <div class="row">
          <h4 class="mb-0">Daftar Mahasiswa - <?php echo htmlspecialchars($current_dosen_nama); ?></h4>
          <br>
          <br>
          <!-- Jadwal Hari Ini -->
          <div class="col-lg-6 mb-4">
              <div class="card fade-in">
                  <div class="card-body">
                    <div class="card-body text-center">
                        <i class="bx bxs-graduation fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $total_rows; ?></h4>
                        <small>Total Mahasiswa</small>
                    </div>
                  </div>
              </div>
          </div>

          <!-- Nilai Terbaru -->
          <div class="col-lg-6 mb-4">
              <div class="card fade-in">
                  <div class="card-body">
                    <div class="card-body text-center">
                      <i class="bx bxs-book fa-2x mb-2"></i>
                      <h4 class="mb-0"><?php echo $total_matkul_diampu; ?></h4>
                      <small>Mata Kuliah Diampu</small>
                    </div>
                  </div>
              </div>
          </div>
      </div>

      <!-- Filter and Search -->
      <div class="card mb-4 fade-in">
          <div class="card-body">
              <form method="GET" class="row g-3">
                  <div class="col-md-4">
                      <label class="form-label">Filter Mata Kuliah</label>
                      <select class="form-select" name="jadwal_id">
                          <option value="">Semua Mata Kuliah</option>
                          <?php if ($mata_kuliah_result): ?>
                              <?php $mata_kuliah_result->data_seek(0); ?>
                              <?php while ($mk = $mata_kuliah_result->fetch_assoc()): ?>
                                  <option value="<?php echo $mk['jadwal_id']; ?>" <?php echo (isset($_GET['jadwal_id']) && $_GET['jadwal_id'] == $mk['jadwal_id']) ? 'selected' : ''; ?>>
                                      <?php echo htmlspecialchars($mk['matakuliah'] . ' - ' . $mk['kelas'] . ' (' . $mk['hari'] . ' ' . $mk['waktu_mulai'] . '-' . $mk['waktu_selesai'] . ')'); ?>
                                  </option>
                              <?php endwhile; ?>
                          <?php endif; ?>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label">Cari Mahasiswa</label>
                      <input type="text" class="form-control" name="search" placeholder="Cari berdasarkan NIM, nama, atau email..." value="<?php echo htmlspecialchars($search); ?>">
                  </div>
                  <div class="col-md-2 d-flex align-items-end">
                      <button type="submit" class="btn btn-success w-100">
                          <i class="bx bx-search me-2"></i>Cari
                      </button>
                  </div>
              </form>
          </div>
      </div>

      <!-- Students List -->
      <div class="row">
          <div class="col-lg-12 mb-4">
              <div class="card fade-in">
                  <div class="card-body" style="color: white;">
                      <?php if ($result && $result->num_rows > 0): ?>
                          <div class="row">
                              <?php while ($row = $result->fetch_assoc()): ?>
                                  <div class="col-lg-6 col-xl-4 mb-4">
                                      <div class="card student-card h-100">
                                          <div class="card-body">
                                              <div class="d-flex align-items-center mb-3">
                                                  <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                      <i class="bx bxs-graduation"></i>
                                                  </div>
                                                  <div>
                                                      <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($row['nama']); ?></h6>
                                                      <small class="text-muted"><?php echo htmlspecialchars($row['nim']); ?></small>
                                                  </div>
                                              </div>
                                              
                                              <div class="student-info p-3 rounded mb-3">
                                                  <div class="row text-sm">
                                                      <div class="col-12 mb-2">
                                                          <i class="bx bxs-envelope text-muted me-2"></i>
                                                          <small><?php echo htmlspecialchars($row['email'] ?? 'Tidak tersedia'); ?></small>
                                                      </div>
                                                      <div class="col-12 mb-2">
                                                          <i class="bx bxs-phone text-muted me-2"></i>
                                                          <small><?php echo htmlspecialchars($row['telepon'] ?? 'Tidak tersedia'); ?></small>
                                                      </div>
                                                      <div class="col-12">
                                                          <i class="bx bx-map text-muted me-2"></i>
                                                          <small><?php echo htmlspecialchars(substr($row['alamat'] ?? 'Tidak tersedia', 0, 50)); ?><?php echo strlen($row['alamat'] ?? '') > 50 ? '...' : ''; ?></small>
                                                      </div>
                                                  </div>
                                              </div>
                                              
                                              <div class="mb-3">
                                                  <div class="d-flex justify-content-between align-items-center mb-2">
                                                      <small class="text-muted fw-bold">Mata Kuliah Diambil:</small>
                                                      <span class="badge bg-success"><?php echo $row['jumlah_matkul']; ?> MK</span>
                                                  </div>
                                                  <div class="text-sm">
                                                      <?php
                                                      $mata_kuliah_list = explode(', ', $row['mata_kuliah_diambil']);
                                                      foreach ($mata_kuliah_list as $mk) {
                                                          echo '<span class="badge subject-badge me-1 mb-1">' . htmlspecialchars($mk) . '</span>';
                                                      }
                                                      ?>
                                                  </div>
                                              </div>
                                              
                                              <div class="d-flex gap-2">
                                                  <a href="input-nilai.php?search=<?php echo urlencode($row['nim']);?>" class="btn btn-sm btn-success flex-fill">
                                                      <i class="bx bxs-edit me-1"></i>Input Nilai
                                                  </a>
                                              </div>
                                          </div>
                                      </div>
                                  </div>

                                  <!-- Detail Modal for each student -->
                                  <div class="modal fade" id="detailModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                      <div class="modal-dialog modal-lg">
                                          <div class="modal-content">
                                              <div class="modal-header bg-success text-white">
                                                  <h5 class="modal-title">Detail Mahasiswa</h5>
                                                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                              </div>
                                              <div class="modal-body">
                                                  <div class="row">
                                                      <div class="col-md-4 text-center mb-3">
                                                          <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                                              <i class="bx bxs-graduation fa-3x"></i>
                                                          </div>
                                                          <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($row['nama']); ?></h5>
                                                          <p class="text-muted mb-0"><?php echo htmlspecialchars($row['nim']); ?></p>
                                                      </div>
                                                      <div class="col-md-8">
                                                          <div class="row">
                                                              <div class="col-md-6 mb-3">
                                                                  <label class="form-label">Email</label>
                                                                  <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['email']); ?>" readonly>
                                                              </div>
                                                              <div class="col-md-6 mb-3">
                                                                  <label class="form-label">Telepon</label>
                                                                  <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['telepon']); ?>" readonly>
                                                              </div>
                                                              <div class="col-12 mb-3">
                                                                  <label class="form-label">Alamat</label>
                                                                  <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($row['alamat']); ?></textarea>
                                                              </div>
                                                              <div class="col-12">
                                                                  <label class="form-label">Mata Kuliah yang Diambil</label>
                                                                  <div class="p-2 bg-light rounded">
                                                                      <?php
                                                                      $mata_kuliah_list = explode(', ', $row['mata_kuliah_diambil']);
                                                                      foreach ($mata_kuliah_list as $mk) {
                                                                          echo '<span class="badge bg-success me-1 mb-1">' . htmlspecialchars($mk) . '</span>';
                                                                      }
                                                                      ?>
                                                                  </div>
                                                              </div>
                                                          </div>
                                                      </div>
                                                  </div>
                                              </div>
                                              <div class="modal-footer">
                                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                  <a href="input-nilai.php?search=<?php echo urlencode($row['nim']); ?>" class="btn btn-success">
                                                      <i class="bx bxs-edit me-1"></i>Input Nilai
                                                  </a>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              <?php endwhile; ?>
                          </div>

                          <!-- Pagination -->
                          <?php if ($total_pages > 1): ?>
                          <nav aria-label="Page navigation">
                              <ul class="pagination justify-content-center mt-4">
                                  <?php if ($page > 1): ?>
                                      <li class="page-item">
                                          <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&jadwal_id=<?php echo $filter_jadwal; ?>" aria-label="Previous">
                                              <span aria-hidden="true">&laquo;</span>
                                          </a>
                                      </li>
                                  <?php endif; ?>
                                  
                                  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                      <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                          <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&jadwal_id=<?php echo $filter_jadwal; ?>"><?php echo $i; ?></a>
                                      </li>
                                  <?php endfor; ?>
                                  
                                  <?php if ($page < $total_pages): ?>
                                      <li class="page-item">
                                          <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&jadwal_id=<?php echo $filter_jadwal; ?>" aria-label="Next">
                                              <span aria-hidden="true">&raquo;</span>
                                          </a>
                                      </li>
                                  <?php endif; ?>
                              </ul>
                          </nav>
                          <?php endif; ?>
                      <?php else: ?>
                          <div class="text-center py-5" >
                              <i class="fas fa-user-graduate fa-4x text-muted mb-4"></i>
                              <h5>Tidak ada data mahasiswa</h5>
                              <p class="text-muted">Tidak ada mahasiswa yang mengambil mata kuliah Anda</p>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
      </div>
    </main>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Custom Script -->
    <script src="../../../js/script.js"></script>
    <script>
        $(document).ready(function() {
            // Auto close alert after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Student card hover effect
            $('.student-card').hover(
                function() {
                    $(this).addClass('shadow-sm');
                },
                function() {
                    $(this).removeClass('shadow-sm');
                }
            );
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>