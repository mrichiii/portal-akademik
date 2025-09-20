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

// Pagination data nilai
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fungsi pencarian
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " AND (mk.matakuliah LIKE ? OR d.nama LIKE ?) ";
    $search_param = "%$search%";
}

// Filter semester dan tahun ajaran
$filter_semester = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';
$filter_tahun_ajaran = isset($_GET['tahun_ajaran']) ? clean_input($_GET['tahun_ajaran']) : '';

$filter_conditions = "dn.mahasiswa_id = ?";
$params = [$mahasiswa_data['id']];
$param_types = "i";

if (!empty($search)) {
    $filter_conditions .= $search_condition;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

if (!empty($filter_semester)) {
    $filter_conditions .= " AND dn.semester = ?";
    $params[] = $filter_semester;
    $param_types .= "s";
}

if (!empty($filter_tahun_ajaran)) {
    $filter_conditions .= " AND dn.tahun_ajaran = ?";
    $params[] = $filter_tahun_ajaran;
    $param_types .= "s";
}

// Hitung total record
$count_sql = "SELECT COUNT(*) 
              FROM data_nilai dn
              JOIN mahasiswa m ON dn.mahasiswa_id = m.id
              JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
              JOIN matkul mk ON j.matkul_id = mk.id
              JOIN dosen d ON j.dosen_nip = d.nip
              WHERE $filter_conditions";

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

// Ambil data dengan join dan pagination
$sql = "SELECT dn.*, 
               m.nim, m.nama AS nama_mahasiswa, 
               mk.kode, mk.matakuliah, mk.sks, 
               d.nama AS nama_dosen, 
               j.hari, j.waktu_mulai, j.waktu_selesai
        FROM data_nilai dn
        JOIN mahasiswa m ON dn.mahasiswa_id = m.id
        JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
        JOIN matkul mk ON j.matkul_id = mk.id
        JOIN dosen d ON j.dosen_nip = d.nip
        WHERE $filter_conditions
        ORDER BY dn.mahasiswa_id ASC 
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error prepare stmt: " . $conn->error);
}

// Tambahkan parameter untuk pagination
$params_pagination = $params;
$params_pagination[] = $offset;
$params_pagination[] = $per_page;
$param_types_pagination = $param_types . "ii";

$stmt->bind_param($param_types_pagination, ...$params_pagination);
$stmt->execute();
$result = $stmt->get_result();

// Hitung total SKS yang sudah diambil
$total_sks_query = "SELECT SUM(mk.sks) as total_sks
                   FROM data_nilai dn
                   JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                   JOIN matkul mk ON j.matkul_id = mk.id
                   WHERE dn.mahasiswa_id = ?";
if (!empty($filter_semester)) {
    $total_sks_query .= " AND dn.semester = ?";
}
if (!empty($filter_tahun_ajaran)) {
    $total_sks_query .= " AND dn.tahun_ajaran = ?";
}

$total_sks_stmt = $conn->prepare($total_sks_query);
if (!$total_sks_stmt) {
    die("Error prepare total_sks: " . $conn->error);
}

$total_sks_params = [$mahasiswa_data['id']];
$total_sks_param_types = "i";

if (!empty($filter_semester)) {
    $total_sks_params[] = $filter_semester;
    $total_sks_param_types .= "s";
}
if (!empty($filter_tahun_ajaran)) {
    $total_sks_params[] = $filter_tahun_ajaran;
    $total_sks_param_types .= "s";
}

$total_sks_stmt->bind_param($total_sks_param_types, ...$total_sks_params);
$total_sks_stmt->execute();
$total_sks_result = $total_sks_stmt->get_result();
$total_sks_data = $total_sks_result->fetch_assoc();
$total_sks = $total_sks_data['total_sks'] ?? 0;

// Hitung IP Semester
$ip_query = "SELECT AVG(CASE 
                        WHEN dn.nilai_akhir >= 85 THEN 4.0
                        WHEN dn.nilai_akhir >= 80 THEN 3.7
                        WHEN dn.nilai_akhir >= 75 THEN 3.3
                        WHEN dn.nilai_akhir >= 70 THEN 3.0
                        WHEN dn.nilai_akhir >= 65 THEN 2.7
                        WHEN dn.nilai_akhir >= 60 THEN 2.3
                        WHEN dn.nilai_akhir >= 55 THEN 2.0
                        WHEN dn.nilai_akhir >= 50 THEN 1.7
                        WHEN dn.nilai_akhir >= 45 THEN 1.3
                        WHEN dn.nilai_akhir >= 40 THEN 1.0
                        ELSE 0
                      END) as ip_semester
            FROM data_nilai dn
            WHERE dn.mahasiswa_id = ?";
if (!empty($filter_semester)) {
    $ip_query .= " AND dn.semester = ?";
}
if (!empty($filter_tahun_ajaran)) {
    $ip_query .= " AND dn.tahun_ajaran = ?";
}

$ip_stmt = $conn->prepare($ip_query);
if (!$ip_stmt) {
    die("Error prepare ip: " . $conn->error);
}

$ip_stmt->bind_param($total_sks_param_types, ...$total_sks_params);
$ip_stmt->execute();
$ip_result = $ip_stmt->get_result();
$ip_data = $ip_result->fetch_assoc();
$ip_semester = number_format($ip_data['ip_semester'] ?? 0, 2);

// Get unique tahun ajaran for filter dropdown
$tahun_sql = "SELECT DISTINCT jk.tahun_ajaran 
              FROM krs k
              JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id
              WHERE k.mahasiswa_id = ?
              ORDER BY jk.tahun_ajaran DESC";
              
$tahun_stmt = $conn->prepare($tahun_sql);
if (!$tahun_stmt) {
    die("Prepare failed: " . $conn->error);
}

$tahun_stmt->bind_param("i", $mahasiswa_data['id']);
$tahun_stmt->execute();
$tahun_result = $tahun_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KHS - Portal Akademik</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
    <style>
        .nav_submenu a {
            padding-left: 20px !important;
        }
        .grade-A {
            color: #28a745;
            font-weight: bold;
        }
        .grade-B {
            color: #5cb85c;
            font-weight: bold;
        }
        .grade-C {
            color: #ffc107;
            font-weight: bold;
        }
        .grade-D {
            color: #fd7e14;
            font-weight: bold;
        }
        .grade-E {
            color: #dc3545;
            font-weight: bold;
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
        .status-aktif {
            color: #28a745;
            font-weight: bold;
        }
        .status-batal {
            color: #dc3545;
            font-weight: bold;
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
                    <a href="../../../views/mahasiswa/dashboard.php" class="nav_link">
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
                            <a href="khs.php" class="nav_link active">• Kartu Hasil Studi</a>
                            <a href="jadwal-kuliah.php" class="nav_link">• Jadwal Kuliah</a>
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
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Kartu Hasil Studi</h4>
                </div>
                <div class="card-body">
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

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">NIM</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_data['nim']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Mahasiswa</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_data['nama']); ?>" readonly>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Program Studi</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_data['nama_prodi'] ?? 'Belum diset'); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total SKS</label>
                            <input type="text" class="form-control" value="<?php echo $total_sks; ?>" readonly>
                        </div>
                    </div>

                    <!-- KHS Table -->
                    <div class="table-data">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Mata Kuliah</th>
                                    <th>SKS</th>
                                    <th>Dosen</th>
                                    <th>Tugas</th>
                                    <th>UTS</th>
                                    <th>UAS</th>
                                    <th>Akhir</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['kode'] . ' - ' . $row['matakuliah']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo $row['hari']; ?> <?php echo date('H:i', strtotime($row['waktu_mulai'])); ?>-<?php echo date('H:i', strtotime($row['waktu_selesai'])); ?>
                                            </small>
                                        </td>
                                        <td><?php echo $row['sks']; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
                                        <td><?php echo number_format($row['nilai_tugas'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_uts'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_uas'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_akhir'], 2); ?></td>
                                        <td class="
                                            <?php if ($row['nilai_akhir'] >= 85) echo 'grade-A'; 
                                            elseif ($row['nilai_akhir'] >= 80) echo 'grade-B'; 
                                            elseif ($row['nilai_akhir'] >= 75) echo 'grade-B'; 
                                            elseif ($row['nilai_akhir'] >= 70) echo 'grade-C'; 
                                            elseif ($row['nilai_akhir'] >= 65) echo 'grade-C'; 
                                            elseif ($row['nilai_akhir'] >= 60) echo 'grade-D'; 
                                            elseif ($row['nilai_akhir'] >= 55) echo 'grade-D'; 
                                            else echo 'grade-E'; ?>">

                                            <?php if ($row['nilai_akhir'] >= 85) echo 'A'; 
                                            elseif ($row['nilai_akhir'] >= 80) echo 'A-'; 
                                            elseif ($row['nilai_akhir'] >= 75) echo 'B+'; 
                                            elseif ($row['nilai_akhir'] >= 70) echo 'B'; 
                                            elseif ($row['nilai_akhir'] >= 65) echo 'B-'; 
                                            elseif ($row['nilai_akhir'] >= 60) echo 'C+'; 
                                            elseif ($row['nilai_akhir'] >= 55) echo 'C'; 
                                            elseif ($row['nilai_akhir'] >= 50) echo 'C-'; 
                                            elseif ($row['nilai_akhir'] >= 45) echo 'D'; 
                                            else echo 'E'; ?>
                                        </td>
                                    </tr>
                                        <?php endwhile; ?>
                                        <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">Tidak ada data nilai yang ditemukan</td>
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
                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun_ajaran) ? '&tahun_ajaran='.urlencode($filter_tahun_ajaran) : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun_ajaran) ? '&tahun_ajaran='.urlencode($filter_tahun_ajaran) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun_ajaran) ? '&tahun_ajaran='.urlencode($filter_tahun_ajaran) : ''; ?>" aria-label="Next">
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
                            <i class="bx bxs-printer me-1"></i> Cetak KHS
                        </button>
                    </div>
            </div>
        </div>
</main>

    <script src="../../../js/script.js"></script>
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
// Close database connections if (isset($stmt)) $stmt->close(); 
// if (isset($total_sks_stmt)) $total_sks_stmt->close(); 
// if (isset($ip_stmt)) $ip_stmt->close(); 
// if (isset($tahun_stmt)) $tahun_stmt->close(); 
// if (isset($mahasiswa_stmt)) $mahasiswa_stmt->close(); $conn->close(); ?>