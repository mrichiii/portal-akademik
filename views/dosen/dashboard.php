<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/config.php';

// Set timezone ke Waktu Indonesia Barat (Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Verifikasi koneksi database
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Ambil data dosen dengan foto profil
$dosen_sql = "SELECT d.*, ps.nama_prodi, u.username, d.foto_profil 
              FROM dosen d 
              LEFT JOIN program_studi ps ON d.prodi = ps.kode_prodi 
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.user_id = ?";
$dosen_stmt = $conn->prepare($dosen_sql);
if (!$dosen_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$dosen_stmt->bind_param("i", $user_id);
$dosen_stmt->execute();
$dosen_result = $dosen_stmt->get_result();
$dosen_data = $dosen_result->fetch_assoc();

if (!$dosen_data) {
    $_SESSION['error_message'] = "Data dosen tidak ditemukan!";
    header("Location: ../../login.php");
    exit();
}

// Statistik Dashboard
// 1. Jumlah Mata Kuliah yang Diajar
$matkul_sql = "SELECT COUNT(DISTINCT j.id) as total_matkul 
               FROM jadwal_kuliah j 
               WHERE j.dosen_nip = ?";
$matkul_stmt = $conn->prepare($matkul_sql);
if (!$matkul_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$matkul_stmt->bind_param("s", $dosen_data['nip']);
$matkul_stmt->execute();
$total_matkul = $matkul_stmt->get_result()->fetch_assoc()['total_matkul'];

// 2. Jumlah Kelas yang Diajar
$kelas_sql = "SELECT COUNT(*) as total_kelas
              FROM jadwal_kuliah j 
              WHERE j.dosen_nip = ?";
$kelas_stmt = $conn->prepare($kelas_sql);
if (!$kelas_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$kelas_stmt->bind_param("s", $dosen_data['nip']);
$kelas_stmt->execute();
$total_kelas = $kelas_stmt->get_result()->fetch_assoc()['total_kelas'];

// 3. Jumlah Mahasiswa yang Dinilai
$mahasiswa_sql = "SELECT COUNT(DISTINCT dn.mahasiswa_id) as total_mahasiswa
                  FROM data_nilai dn
                  JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                  WHERE j.dosen_nip = ?";
$mahasiswa_stmt = $conn->prepare($mahasiswa_sql);
if (!$mahasiswa_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$mahasiswa_stmt->bind_param("s", $dosen_data['nip']);
$mahasiswa_stmt->execute();
$total_mahasiswa = $mahasiswa_stmt->get_result()->fetch_assoc()['total_mahasiswa'];

// Jadwal Mengajar Hari Ini
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

$jadwal_hari_ini_sql = "SELECT j.*, mk.matakuliah, mk.sks, mk.kelas
                        FROM jadwal_kuliah j
                        JOIN matkul mk ON j.matkul_id = mk.id
                        WHERE j.dosen_nip = ? AND j.hari = ?
                        ORDER BY j.waktu_mulai ASC";
$jadwal_stmt = $conn->prepare($jadwal_hari_ini_sql);
if (!$jadwal_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$jadwal_stmt->bind_param("ss", $dosen_data['nip'], $hari_indonesia[$hari_ini]);
$jadwal_stmt->execute();
$jadwal_hari_ini = $jadwal_stmt->get_result();

// Jadwal Mengajar Lengkap (Semua Hari)
$jadwal_lengkap_sql = "SELECT j.*, mk.matakuliah, mk.sks , mk.kelas
                       FROM jadwal_kuliah j
                       JOIN matkul mk ON j.matkul_id = mk.id
                       WHERE j.dosen_nip = ?
                       ORDER BY 
                       CASE j.hari 
                           WHEN 'Senin' THEN 1
                           WHEN 'Selasa' THEN 2
                           WHEN 'Rabu' THEN 3
                           WHEN 'Kamis' THEN 4
                           WHEN 'Jumat' THEN 5
                           WHEN 'Sabtu' THEN 6
                           WHEN 'Minggu' THEN 7
                       END,
                       j.waktu_mulai ASC";
$jadwal_lengkap_stmt = $conn->prepare($jadwal_lengkap_sql);
if (!$jadwal_lengkap_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$jadwal_lengkap_stmt->bind_param("s", $dosen_data['nip']);
$jadwal_lengkap_stmt->execute();
$jadwal_lengkap = $jadwal_lengkap_stmt->get_result();

// Nilai Terbaru yang Diinput
$nilai_terbaru_sql = "SELECT dn.*, m.nim, m.nama as nama_mahasiswa, mk.matakuliah
                      FROM data_nilai dn
                      JOIN mahasiswa m ON dn.mahasiswa_id = m.id
                      JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                      JOIN matkul mk ON j.matkul_id = mk.id
                      WHERE j.dosen_nip = ?
                      ORDER BY dn.created_at DESC
                      LIMIT 5";
$nilai_stmt = $conn->prepare($nilai_terbaru_sql);
if (!$nilai_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$nilai_stmt->bind_param("s", $dosen_data['nip']);
$nilai_stmt->execute();
$nilai_terbaru = $nilai_stmt->get_result();

// Distribusi Grade
$grade_sql = "SELECT dn.grade, COUNT(*) as jumlah
              FROM data_nilai dn
              JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
              WHERE j.dosen_nip = ?
              GROUP BY dn.grade
              ORDER BY dn.grade ASC";
$grade_stmt = $conn->prepare($grade_sql);
if (!$grade_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$grade_stmt->bind_param("s", $dosen_data['nip']);
$grade_stmt->execute();
$grade_distribution = $grade_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Dashboard Dosen - Portal Akademik</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../css/style.css" />
    <link rel="stylesheet" href="../../css/table.css" />
    <link rel="website icon" type="png" href="../../img/logouin.png" />

    
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
            <a href="#" class="nav_link active">
              <i class="bx bx-home"></i>
              <span class="nav_name">Dashboard</span>
            </a>

            <!-- Menu Dosen -->
            <a href="pages/daftar-mahasiswa.php" class="nav_link">
              <i class="bx bxs-user-detail"></i>
              <span class="nav_name">Daftar Mahasiswa</span>
            </a>
            <a href="pages/input-nilai.php" class="nav_link">
              <i class="bx bxs-book-content"></i>
              <span class="nav_name">Input Nilai</span>
            </a>
            <a href="pages/jadwal-mengajar.php" class="nav_link">
              <i class="bx bxs-calendar"></i>
              <span class="nav_name">Jadwal Mengajar</span>
            </a>
            <a href="pages/profil-dosen.php" class="nav_link">
              <i class="bx bxs-user"></i>
              <span class="nav_name">Profil Dosen</span>
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
      <!-- Welcome Section -->
      <div class="card welcome-card mb-4 fade-in">
          <div class="card-body">
              <div class="row align-items-center">
                  <div class="col-md-8">
                      <div class="d-flex align-items-center">
                          <div class="flex-grow-1">
                              <h3 class="mb-2">Selamat Datang, <span class="text-warning"><?php echo htmlspecialchars($dosen_data['nama']); ?></span>!</h3>
                              <p class="mb-2"><i class="bx bx-id-card me-2"></i>NIP: <?php echo htmlspecialchars($dosen_data['nip']); ?></p>
                              <p class="mb-1"><i class="bx bx-bookmark me-2"></i><?php echo htmlspecialchars($dosen_data['nama_prodi'] ?? 'Belum memiliki program studi'); ?></p>
                              <p class="mb-0"><i class="bx bx-time me-2"></i><?php echo $hari_indonesia[$hari_ini]; ?>, <?php echo date('d F Y'); ?></p>
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

      <!-- Statistics Cards -->
      <div class="row mb-4">
          <div class="col-lg-3 col-md-6 mb-3">
              <div class="card stats-card fade-in">
                  <div class="card-body">
                      <div class="d-flex justify-content-between">
                          <div>
                              <h6 class="card-title">Mata Kuliah</h6>
                              <h2 class="mb-0"><?php echo $total_matkul; ?></h2>
                              <small>Total yang diajar</small>
                          </div>
                          <div class="stats-icon">
                              <i class="bx bx-book"></i>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          
          <div class="col-lg-3 col-md-6 mb-3">
              <div class="card stats-card fade-in">
                  <div class="card-body">
                      <div class="d-flex justify-content-between">
                          <div>
                              <h6 class="card-title">Kelas</h6>
                              <h2 class="mb-0"><?php echo $total_kelas; ?></h2>
                              <small>Total kelas aktif</small>
                          </div>
                          <div class="stats-icon">
                              <i class="bx bx-group"></i>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          
          <div class="col-lg-3 col-md-6 mb-3">
              <div class="card stats-card fade-in">
                  <div class="card-body">
                      <div class="d-flex justify-content-between">
                          <div>
                              <h6 class="card-title">Mahasiswa</h6>
                              <h2 class="mb-0"><?php echo $total_mahasiswa; ?></h2>
                              <small>Yang sudah dinilai</small>
                          </div>
                          <div class="stats-icon">
                              <i class="bx bx-user-plus"></i>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          
          <div class="col-lg-3 col-md-6 mb-3">
              <div class="card stats-card fade-in">
                  <div class="card-body">
                      <div class="d-flex justify-content-between">
                          <div>
                              <h6 class="card-title">Waktu</h6>
                              <h2 class="mb-0" id="currentTime"><?php echo date('H:i'); ?></h2>  <small>WIB </small>
                          </div>
                          <div class="stats-icon">
                              <i class="bx bx-time"></i>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <div class="row">
          <!-- Jadwal Hari Ini -->
          <div class="col-lg-6 mb-4">
              <div class="card fade-in">
                  <div class="card-header text-white" style="background-color: #ffffff1a;">
                      <h5 class="mb-0">
                          <i class="bx bx-calendar-event me-2"></i>
                          Jadwal Mengajar Hari Ini
                      </h5>
                  </div>
                  <div class="card-body">
                      <?php if ($jadwal_hari_ini->num_rows > 0): ?>
                          <?php while ($jadwal = $jadwal_hari_ini->fetch_assoc()): ?>
                          <div class="schedule-item">
                              <div class="d-flex justify-content-between align-items-start">
                                  <div>
                                      <h6 class="mb-1"><?php echo htmlspecialchars($jadwal['matakuliah']); ?></h6>
                                      <p class="mb-1">
                                          <span class="schedule-time">
                                              <?php echo $jadwal['waktu_mulai']; ?> - <?php echo $jadwal['waktu_selesai']; ?>
                                          </span>
                                      </p>
                                      <small class="text-muted">
                                          <i class="bx bx-map me-1"></i>
                                          Ruang <?php echo htmlspecialchars($jadwal['ruangan']); ?>
                                      </small>
                                  </div>
                                  <span class="badge" style="background-color: #ffffff1a;"><?php echo $jadwal['sks']; ?> SKS</span>
                              </div>
                          </div>
                          <?php endwhile; ?>
                      <?php else: ?>
                          <div class="text-center py-4">
                              <i class="bx bx-calendar-x fa-3x text-muted mb-3"></i>
                              <p class="text-muted">Tidak ada jadwal mengajar hari ini</p>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </div>

          <!-- Nilai Terbaru -->
          <div class="col-lg-6 mb-4">
              <div class="card fade-in">
                  <div class="card-header text-white" style="background-color: #ffffff1a;">
                      <h5 class="mb-0">
                          <i class="bx bx-star me-2"></i>
                          Nilai Terbaru Diinput
                      </h5>
                  </div>
                  <div class="card-body">
                      <?php if ($nilai_terbaru->num_rows > 0): ?>
                          <div class="table-responsive">
                              <table class="table-custom">
                                  <thead class="table-dark">
                                      <tr>
                                          <th>Mahasiswa</th>
                                          <th>Mata Kuliah</th>
                                          <th>Nilai</th>
                                          <th>Grade</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php while ($nilai = $nilai_terbaru->fetch_assoc()): ?>
                                      <tr>
                                          <td>
                                              <small><?php echo htmlspecialchars($nilai['nama_mahasiswa']); ?></small><br>
                                              <small class="text-muted"><?php echo htmlspecialchars($nilai['nim']); ?></small>
                                          </td>
                                          <td><small><?php echo htmlspecialchars($nilai['matakuliah']); ?></small></td>
                                          <td><?php echo number_format($nilai['nilai_akhir'], 2); ?></td>
                                          <td><span class="grade-badge grade-<?php echo $nilai['grade']; ?>"><?php echo $nilai['grade']; ?></span></td>
                                      </tr>
                                      <?php endwhile; ?>
                                  </tbody>
                              </table>
                          </div>
                      <?php else: ?>
                          <div class="text-center py-4">
                              <i class="bx bx-clipboard fa-3x text-muted mb-3"></i>
                              <p class="text-muted">Belum ada nilai yang diinput</p>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
      </div>

      <!-- Jadwal Mengajar Lengkap -->
      <div class="row mb-4">
          <div class="col-lg-12">
              <div class="card fade-in">
                      <h5 class="mb-0">
                          <i class="bx bx-calendar-week me-2"></i>
                          Jadwal Mengajar Lengkap
                      </h5>
                      <br>
                  <div class="card-body" style="color: #ffffff;">
                      <?php if ($jadwal_lengkap->num_rows > 0): ?>
                          <div class="table-responsive">
                              <table class="table-custom">
                                  <thead class="table-dark">
                                      <tr>
                                          <th width="10%">Hari</th>
                                          <th width="15%">Waktu</th>
                                          <th width="30%">Mata Kuliah</th>
                                          <th width="10%">SKS</th>
                                          <th width="15%">Ruangan</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php 
                                      $current_day = '';
                                      while ($jadwal = $jadwal_lengkap->fetch_assoc()): 
                                          $is_today = ($jadwal['hari'] == $hari_indonesia[$hari_ini]);
                                      ?>
                                      <tr class="<?php echo $is_today ? 'current-day' : ''; ?>">
                                          <td class="schedule-day <?php echo $is_today ? 'current-day' : ''; ?>">
                                              <?php 
                                              if ($current_day != $jadwal['hari']) {
                                                  echo htmlspecialchars($jadwal['hari']);
                                                  $current_day = $jadwal['hari'];
                                              }
                                              ?>
                                              <?php if ($is_today): ?>
                                                  <i class="bx bx-right-arrow-alt text-primary ms-1"></i>
                                              <?php endif; ?>
                                          </td>
                                          <td class="time-slot">
                                              <?php echo $jadwal['waktu_mulai']; ?> - <?php echo $jadwal['waktu_selesai']; ?>
                                          </td>
                                          <td class="subject-name">
                                              <?php echo htmlspecialchars($jadwal['matakuliah']); ?>
                                              <br>
                                              <small class="text-muted"><?php echo htmlspecialchars($jadwal['kelas']); ?></small>
                                          </td>
                                          <td>
                                              <span class="sks-badge"><?php echo $jadwal['sks']; ?> SKS</span>
                                          </td>
                                          <td class="room-info">
                                              <i class="bx bx-map me-1"></i>
                                              <?php echo htmlspecialchars($jadwal['ruangan']); ?>
                                          </td>
                                      </tr>
                                      <?php endwhile; ?>
                                  </tbody>
                              </table>
                          </div>
                      <?php else: ?>
                          <div class="text-center py-5">
                              <i class="bx bx-calendar-x fa-4x text-muted mb-3"></i>
                              <h5 class="text-muted">Tidak ada jadwal mengajar</h5>
                              <p class="text-muted">Belum ada jadwal yang ditetapkan untuk Anda</p>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
      </div>
    </main>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Script -->
    <script src="../../js/script.js"></script>
    <script>
        // Update waktu real-time
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour12: false,
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Update setiap detik
        setInterval(updateClock, 1000);

        // Grade Distribution Chart
        <?php if ($grade_distribution->num_rows > 0): ?>
        <?php 
        $grade_distribution->data_seek(0); // Reset pointer
        $labels = [];
        $data = [];
        $colors = [
            'A' => '#28a745',
            'A-' => '#5cb85c', 
            'B+' => '#5bc0de',
            'B' => '#5bc0de',
            'B-' => '#5bc0de',
            'C+' => '#ffc107',
            'C' => '#ffc107',
            'D' => '#fd7e14',
            'E' => '#dc3545'
        ];
        $chartColors = [];
        
        while ($grade = $grade_distribution->fetch_assoc()) {
            $labels[] = 'Grade ' . $grade['grade'];
            $data[] = $grade['jumlah'];
            $chartColors[] = $colors[$grade['grade']] ?? '#6c757d';
        }
        ?>
        
        const ctx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: <?php echo json_encode($chartColors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Animasi fade-in untuk semua elemen dengan class fade-in
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = 1;
                }, 100 * index);
            });
        });
    </script>
  </body>
</html>
<?php
// Close database connection
$conn->close();
?>