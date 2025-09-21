<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/config.php';

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

// Ambil data mahasiswa termasuk foto profil
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

// Hitung IPK
$ipk_sql = "SELECT AVG(
                CASE 
                    WHEN dn.grade = 'A' THEN 4.0
                    WHEN dn.grade = 'A-' THEN 3.7
                    WHEN dn.grade = 'B+' THEN 3.3
                    WHEN dn.grade = 'B' THEN 3.0
                    WHEN dn.grade = 'B-' THEN 2.7
                    WHEN dn.grade = 'C+' THEN 2.3
                    WHEN dn.grade = 'C' THEN 2.0
                    WHEN dn.grade = 'D' THEN 1.0
                    ELSE 0.0
                END
            ) as ipk
            FROM data_nilai dn
            WHERE dn.mahasiswa_id = ?";
$ipk_stmt = $conn->prepare($ipk_sql);
$ipk_stmt->bind_param("i", $mahasiswa_data['id']);
$ipk_stmt->execute();
$ipk = $ipk_stmt->get_result()->fetch_assoc()['ipk'] ?? 0;

// Hitung total SKS yang sudah diambil
$sks_sql = "SELECT SUM(mk.sks) as total_sks
            FROM krs k
            JOIN jadwal_kuliah j ON k.jadwal_id = j.id
            JOIN matkul mk ON j.matkul_id = mk.id
            WHERE k.mahasiswa_id = ? AND k.status = 'aktif'";
$sks_stmt = $conn->prepare($sks_sql);
$sks_stmt->bind_param("i", $mahasiswa_data['id']);
$sks_stmt->execute();
$total_sks = $sks_stmt->get_result()->fetch_assoc()['total_sks'] ?? 0;

// Jadwal Kuliah Hari Ini
$hari_ini = date('l'); // Nama hari dalam bahasa Inggris
$hari_indonesia = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

$jadwal_hari_ini_sql = "SELECT j.*, mk.matakuliah, mk.sks, d.nama as nama_dosen
                        FROM jadwal_kuliah j
                        JOIN matkul mk ON j.matkul_id = mk.id
                        JOIN dosen d ON j.dosen_nip = d.nip
                        JOIN krs k ON j.id = k.jadwal_id
                        WHERE k.mahasiswa_id = ? AND j.hari = ? AND k.status = 'aktif'
                        ORDER BY j.waktu_mulai ASC";
$jadwal_stmt = $conn->prepare($jadwal_hari_ini_sql);
$jadwal_stmt->bind_param("is", $mahasiswa_data['id'], $hari_indonesia[$hari_ini]);
$jadwal_stmt->execute();
$jadwal_hari_ini = $jadwal_stmt->get_result();

// Query untuk nilai terbaru - pastikan sesuai struktur tabel
$nilai_terbaru_sql = "SELECT dn.*, mk.matakuliah, mk.sks, d.nama as nama_dosen
                      FROM data_nilai dn
                      JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                      JOIN matkul mk ON j.matkul_id = mk.id
                      JOIN dosen d ON j.dosen_nip = d.nip
                      WHERE dn.mahasiswa_id = ?
                      ORDER BY dn.created_at DESC
                      LIMIT 3";

$nilai_terbaru_stmt = $conn->prepare($nilai_terbaru_sql);
if (!$nilai_terbaru_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$nilai_terbaru_stmt->bind_param("i", $mahasiswa_data['id']);
$nilai_terbaru_stmt->execute();
$nilai_terbaru = $nilai_terbaru_stmt->get_result();

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../css/style.css" />
    <link rel="stylesheet" href="../../css/table.css" />
    <link rel="website icon" type="png" href="../../img/logouin.png" />
    <title>Dashboard Mahasiswa/I</title>
    <style>
        .profile-img {
            width: 150px;
            height: 200px;
            object-fit: cover;
            border-radius: none;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .grade-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
        }
        .grade-A { background-color: #28a745; }
        .grade-B { background-color: #17a2b8; }
        .grade-C { background-color: #ffc107; }
        .grade-D { background-color: #fd7e14; }
        .grade-E { background-color: #dc3545; }
        .schedule-item {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        .schedule-time {
            font-weight: bold;
            color: #0d6efd;
        }
        .nav_submenu a {
            padding-left: 20px !important;
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
            <a href="#" class="nav_link active">
              <i class="bx bx-home"></i>
              <span class="nav_name">Dashboard</span>
            </a>
            <a href="pages/biodata.php" class="nav_link">
              <i class="bx bxs-user"></i>
              <span class="nav_name">Biodata</span>
            </a>
            <div class="accordion-item bg-transparent border-0">
              <a class="nav_link collapsed" data-bs-toggle="collapse" href="#submenuAkademik" role="button" aria-expanded="false" aria-controls="submenuAkademik">
                <i class="bx bxs-graduation"></i>
                <span class="nav_name d-flex align-items-center w-100">Akademik <i class="bx bx-chevron-right arrow ms-auto"></i></span>
              </a>
              <div class="collapse nav_submenu ps-4" id="submenuAkademik">
                <a href="pages/krs.php" class="nav_link">• Kartu Rencana Studi</a>
                <a href="pages/khs.php" class="nav_link">• Kartu Hasil Studi</a>
                <a href="pages/jadwal-kuliah.php" class="nav_link">• Jadwal Kuliah</a>
              </div>
            </div>
            <a href="#" class="nav_link">
              <i class="bx bxs-megaphone"></i>
              <span class="nav_name">Pengumuman</span>
            </a>
          </div>
        </div>
        <a href="../../logout.php" class="nav_link">
          <i class='bx bx-log-out nav_icon'></i>
          <span class="nav_name">Logout</span>
        </a>
      </nav>
    </div>

    <main>
      <div class="main-content">
        <h2>Dashboard</h2>
      </div>

        <div class="card welcome-card mb-4 fade-in">
          <div class="card-body">
              <div class="row align-items-center">
                  <div class="col-md-8">
                      <div class="d-flex align-items-center">
                          <div class="flex-grow-1">
                              <h3 class="mb-2">Selamat Datang, <span class="text-warning"><?php echo htmlspecialchars($mahasiswa_data['nama']); ?></span>!</h3>
                              <p class="mb-2"><i class="bx bx-id-card me-2"></i>NIM: <?php echo htmlspecialchars($mahasiswa_data['nim']); ?></p>
                              <p class="mb-1"><i class="bx bx-bookmark me-2"></i><?php echo htmlspecialchars($mahasiswa_data['nama_prodi'] ?? 'Belum memiliki program studi'); ?></p>
                              <p class="mb-0"><i class="bx bx-time me-2"></i><?php echo $hari_indonesia[$hari_ini]; ?>, <?php echo date('d F Y'); ?></p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <!-- Statistics Cards -->
      <div class="row mb-4">
          <div class="col-lg-6 col-md-6 mb-3">
              <div class="card stats-card fade-in">
                  <div class="card-body">
                      <div class="d-flex justify-content-between">
                          <div>
                              <h6 class="card-title">IPK Terkini</h6>
                              <h2 class="mb-0"><?= number_format($ipk, 2); ?></h2>
                              <small>Indeks Prestasi Kumulatif</small>
                          </div>
                          <div class="stats-icon">
                              <i class="bx bx-star"></i>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          
          <div class="col-lg-6 col-md-6 mb-3">
              <div class="card stats-card fade-in">
                  <div class="card-body">
                      <div class="d-flex justify-content-between">
                          <div>
                              <h6 class="card-title">SKS Tempuh</h6>
                              <h2 class="mb-0"><?= $total_sks; ?></h2>
                              <small>Total SKS yang ditempuh</small>
                          </div>
                          <div class="stats-icon">
                              <i class="bx bx-data"></i>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <!-- Welcome Card -->
      <div class="row mb-4">
          <div class="col-lg-12">
              <div class="card fade-in">
                  <div class="card-header text-white" style="background-color: #ffffff1a;">
                      <h5 class="mb-0">
                          <i class="bx bx-info-circle me-2"></i>
                          Informasi Portal Akademik
                      </h5>
                  </div>
                  <div class="card-body">
                      <p class="card-text">Selamat Datang di Portal Akademik. Portal Akademik adalah sistem yang memungkinkan para civitas akademika Universitas Islam Negeri Sumatera Utara untuk menerima informasi dengan lebih cepat melalui Internet. Sistem ini diharapkan dapat memberi kemudahan setiap civitas akademika untuk melakukan aktivitas-aktivitas akademik dan proses belajar mengajar. Selamat menggunakan fasilitas ini.</p>
                  </div>
              </div>
          </div>
      </div>

      <!-- Nilai Terbaru -->
      <div class="row">
          <div class="col-lg-12 mb-4">
              <div class="card fade-in">
                  <div class="card-header text-white" style="background-color: #ffffff1a;">
                      <h5 class="mb-0">
                          <i class="bx bx-star me-2"></i>
                          Nilai Terbaru
                      </h5>
                  </div>
                  <div class="card-body">
                      <?php if ($nilai_terbaru->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead class="table-dark">
                                      <tr>
                                          <th>Mata Kuliah</th>
                                          <th>Dosen</th>
                                          <th>Nilai</th>
                                          <th>Grade</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php while ($nilai = $nilai_terbaru->fetch_assoc()): ?>
                                      <tr>
                                          <td><?= htmlspecialchars($nilai['matakuliah']) ?></td>
                                          <td><?= htmlspecialchars($nilai['nama_dosen']) ?></td>
                                          <td><?= number_format($nilai['nilai_akhir'], 2) ?></td>
                                          <td><span class="grade-badge grade-<?= $nilai['grade'] ?>"><?= $nilai['grade'] ?></span></td>
                                      </tr>
                                      <?php endwhile; ?>
                                  </tbody>
                              </table>
                          </div>
                      <?php else: ?>
                          <div class="text-center py-4">
                              <i class="bx bx-clipboard fa-3x text-muted mb-3"></i>
                              <p class="text-muted">Belum ada nilai yang keluar</p>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
      </div>
    </main>
    <script src="../../js/script.js"></script>
  </body>
</html>