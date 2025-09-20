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
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

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

// Only show jadwal kuliah for courses the student is registered in
$where_clauses[] = "jk.id IN (SELECT k.jadwal_id FROM krs k WHERE k.mahasiswa_id = ? AND k.status = 'aktif')";
$params[] = $mahasiswa_data['id'];
$param_types .= 'i';

if (!empty($search)) {
    $where_clauses[] = "(mk.matakuliah LIKE ? OR d.nama LIKE ? OR jk.ruangan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_semester)) {
    $where_clauses[] = "jk.semester = ?";
    $params[] = $filter_semester;
    $param_types .= 's';
}

if (!empty($filter_tahun)) {
    $where_clauses[] = "jk.tahun_ajaran = ?";
    $params[] = $filter_tahun;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_clauses)) {
    $where_clause = "WHERE " . implode(' AND ', $where_clauses);
}

// Get unique tahun ajaran for filter dropdown
$tahun_sql = "SELECT DISTINCT jk.tahun_ajaran 
              FROM jadwal_kuliah jk
              JOIN krs k ON jk.id = k.jadwal_id
              WHERE k.mahasiswa_id = ?
              ORDER BY jk.tahun_ajaran DESC";
$tahun_stmt = $conn->prepare($tahun_sql);
$tahun_stmt->bind_param("i", $mahasiswa_data['id']);
$tahun_stmt->execute();
$tahun_result = $tahun_stmt->get_result();

// Get data with joins for detailed information
$sql = "SELECT jk.*, mk.matakuliah, mk.sks, d.nama as dosen
        FROM jadwal_kuliah jk
        LEFT JOIN matkul mk ON jk.matkul_id = mk.id
        LEFT JOIN dosen d ON jk.dosen_nip = d.nip
        $where_clause
        ORDER BY 
            CASE jk.hari 
                WHEN 'Senin' THEN 1
                WHEN 'Selasa' THEN 2
                WHEN 'Rabu' THEN 3
                WHEN 'Kamis' THEN 4
                WHEN 'Jumat' THEN 5
                WHEN 'Sabtu' THEN 6
                ELSE 7
            END, 
            jk.waktu_mulai ASC
        LIMIT ?, ?";

// Prepare statement with dynamic parameters
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

// Add parameters for pagination
$params[] = $offset;
$params[] = $per_page;
$param_types .= 'ii';

// Bind parameters if any
if (!empty($params)) {
    if (!$stmt->bind_param($param_types, ...$params)) {
        die("Error binding parameters: " . $stmt->error);
    }
}

if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();

// Update the count for pagination if filters are applied
$count_sql = "SELECT COUNT(*) 
              FROM jadwal_kuliah jk
              LEFT JOIN matkul mk ON jk.matkul_id = mk.id
              LEFT JOIN dosen d ON jk.dosen_nip = d.nip
              $where_clause";
    
$count_stmt = $conn->prepare($count_sql);

if (!$count_stmt) {
    die("Error preparing count statement: " . $conn->error);
}

// Reset param types to exclude pagination parameters
$count_param_types = substr($param_types, 0, -2);
    
// Remove pagination parameters (LIMIT parameters)
$count_params = $params;
array_pop($count_params);
array_pop($count_params);
    
if (!empty($count_params)) {
    if (!$count_stmt->bind_param($count_param_types, ...$count_params)) {
        die("Error binding count parameters: " . $count_stmt->error);
    }
}
    
if (!$count_stmt->execute()) {
    die("Error executing count statement: " . $count_stmt->error);
}

$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Kuliah - Portal Akademik</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="/richie/css/style.css" />
    <link rel="stylesheet" href="/richie/css/table.css" />
    <link rel="website icon" type="png" href="/richie/img/logouin.png" />
    <style>
        .nav_submenu a {
            padding-left: 20px !important;
        }
        .info-box {
            background-color:rgb(0, 0, 0);
            border: 1px solid #00cc6666;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-box h5 {
            color:rgb(255, 255, 255);
            margin-bottom: 15px;
        }
        .info-box p {
            margin-bottom: 5px;
        }
        .badge-semester {
            background-color: #00cc6666;
            color: white;
        }
        .badge-tahun {
            background-color: #ffffff1a;
            color: white;
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
                    <span class="nav_logo-name">UIN Sumatera Utara</span><br />
                    <span class="nav_logo-name">Mahasiswa Portal</span>
                </a>
                <div class="nav_list">
                    <a href="/richie/views/mahasiswa/dashboard.php" class="nav_link">
                        <i class="bx bx-home"></i>
                        <span class="nav_name">Dashboard</span>
                    </a>
                    <a href="biodata.php" class="nav_link">
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
                            <a href="jadwal-kuliah.php" class="nav_link active">• Jadwal Kuliah</a>
                        </div>
                    </div>
                    <a href="#" class="nav_link">
                        <i class="bx bxs-megaphone"></i>
                        <span class="nav_name">Pengumuman</span>
                    </a>
                </div>
            </div>
            <a href="/richie/logout.php" class="nav_link">
                <i class='bx bx-log-out nav_icon'></i>
                <span class="nav_name">Logout</span>
            </a>
        </nav>
    </div>

    <main>
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Jadwal Kuliah</h4>
                </div>
                <div class="card-body">
                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bx bxs-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bx bxs-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); endif; ?>

                    <!-- Jadwal Kuliah Table -->
                    <div class="table-data">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Hari</th>
                                    <th>Waktu</th>
                                    <th>Mata Kuliah</th>
                                    <th>Dosen</th>
                                    <th>Ruangan</th>
                                    <th>Semester/TA</th>
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
                                            <strong><?php echo htmlspecialchars($row['matakuliah']); ?></strong><br>
                                            <small class="text-muted"><?php echo $row['sks']; ?> SKS</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['dosen']); ?></td>
                                        <td><?php echo htmlspecialchars($row['ruangan']); ?></td>
                                        <td>
                                            <span class="badge badge-semester">
                                                <?php echo htmlspecialchars($row['semester']); ?>
                                            </span>
                                            <span class="badge badge-tahun">
                                                <?php echo htmlspecialchars($row['tahun_ajaran']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bx bxs-calendar-times bxs-2x mb-3"></i>
                                                <p class="mb-0">Tidak ada data jadwal kuliah ditemukan</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <!-- Print Button -->
                    <div class="text-end mt-4">
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="bx bx-printer me-1"></i> Cetak Jadwal
                        </button>
                    </div>
                </div>
            </div>
    </main>

    <script src="/richie/js/script.js"></script>
    <script>
        // Highlight active nav item
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav_link');
            navLinks.forEach(link => {
                if (link.classList.contains('active')) {
                    link.closest('.accordion-item').querySelector('.arrow').classList.add('bx-rotate-90');
                }
            });
        });
    </script>
</body>
</html>
<?php 
// Close database connections
if (isset($stmt)) $stmt->close(); 
if (isset($tahun_stmt)) $tahun_stmt->close(); 
if (isset($mahasiswa_stmt)) $mahasiswa_stmt->close(); 
$conn->close(); 
?>