<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$username = isset($_SESSION['username']) ? $_SESSION['username'] : "Admin";

include_once '../../includes/config.php';

// Function to safely execute queries and handle errors
function executeQuery($conn, $query, $description) {
    $result = $conn->query($query);
    if (!$result) {
        error_log("Database Error in $description: " . $conn->error);
        return 0; // Return 0 as default value on error
    }
    $data = $result->fetch_assoc();
    return $data['total'] ?? 0;
}

// Get counts with error handling
if (!isset($_SESSION['jumlah_mahasiswa'])) {
    $query = "SELECT COUNT(*) as total FROM mahasiswa";
    $_SESSION['jumlah_mahasiswa'] = executeQuery($conn, $query, "mahasiswa count");
}

if (!isset($_SESSION['jumlah_dosen'])) {
    $query = "SELECT COUNT(*) as total FROM dosen";
    $_SESSION['jumlah_dosen'] = executeQuery($conn, $query, "dosen count");
}

if (!isset($_SESSION['jumlah_matkul'])) {
    // Fixed: Changed from 'matkul' to 'mata_kuliah' to match database schema
    $query = "SELECT COUNT(*) as total FROM matkul";
    $_SESSION['jumlah_matkul'] = executeQuery($conn, $query, "mata kuliah count");
}

if (!isset($_SESSION['jadwal'])) {
    $query = "SELECT COUNT(*) as total FROM jadwal_kuliah";
    $_SESSION['jadwal'] = executeQuery($conn, $query, "jadwal count");
}

$jumlah_mahasiswa = $_SESSION['jumlah_mahasiswa'] ?? 0;
$jumlah_dosen = $_SESSION['jumlah_dosen'] ?? 0;
$jumlah_matkul = $_SESSION['jumlah_matkul'] ?? 0;
$jadwal = $_SESSION['jadwal'] ?? 0;

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');

// Get program studi data with proper error handling
$prodi_json = '[]'; // Default empty array
try {
    $query = "SELECT ps.nama_prodi as prodi, COUNT(m.id) as jumlah 
              FROM program_studi ps 
              LEFT JOIN mahasiswa m ON ps.kode_prodi = m.prodi 
              GROUP BY ps.kode_prodi, ps.nama_prodi 
              ORDER BY jumlah DESC";
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $prodi_data = [];
        while ($row = $result->fetch_assoc()) {
            $prodi_data[] = [
                'prodi' => $row['prodi'],
                'jumlah' => (int)$row['jumlah']
            ];
        }
        $prodi_json = json_encode($prodi_data);
    }
} catch (Exception $e) {
    error_log("Error getting program studi data: " . $e->getMessage());
    // Use sample data as fallback
    $prodi_json = json_encode([
        ['prodi' => 'Sistem Informasi', 'jumlah' => 125],
        ['prodi' => 'Ilmu Komputer', 'jumlah' => 98],
        ['prodi' => 'Biologi', 'jumlah' => 85]
    ]);
}
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0"
    />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css"
    />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css"
    />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <link rel="stylesheet" href="../../css/style.css" />
    <link rel="stylesheet" href="../../css/table.css" />
    <link rel="website icon" type="png" href="../../img/logouin.png" />


    <style>
        .calendar-day {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 0.5rem;
            margin: 0 2px;
            font-weight: bold;
            background-color:rgb(0, 0, 0);
        }
            
        .calendar-day.active {
            background-color:rgb(255, 255, 255);
            color: black;
        }
          @media (max-width: 768px) {
          /* Container hari */
          .d-flex.mb-3 {
              flex-wrap: nowrap;
              overflow-x: auto;
              padding-bottom: 8px;
              -webkit-overflow-scrolling: touch;
          }
          
          /* Item hari individual */
          .calendar-day {
              flex: 0 0 auto;
              width: 50px;
              padding: 6px 2px;
              font-size: 0.75rem;
              margin: 0 1px;
              border-radius: 4px;
          }
          
          /* Scrollbar styling (untuk browser yang mendukung) */
          .d-flex.mb-3::-webkit-scrollbar {
              height: 4px;
          }
          
          .d-flex.mb-3::-webkit-scrollbar-thumb {
              background-color: rgba(0, 204, 102, 0.5);
              border-radius: 2px;
          }
          
          /* Hari aktif */
          .calendar-day.active {
              font-weight: 600;
              box-shadow: 0 0 0 2px #00cc66;
          }
          
          /* Container jadwal */
          .schedule-container {
              margin-top: 0.5rem;
          }
          
          /* Item jadwal */
          .schedule-item {
              flex-direction: column;
              padding: 10px;
          }
          
          .time-box {
              width: 100%;
              margin-right: 0;
              margin-bottom: 8px;
              text-align: left;
              padding: 6px 8px;
          }
          
          .location-container {
              flex-direction: column;
              align-items: flex-start;
          }
          
          .detail-btn {
              margin-top: 8px;
              align-self: flex-end;
          }
      }
    </style>
    <title>Admin</title>
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
            <a href="#" class="nav_link active">
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
                  <a href="pages/kelola-mahasiswa.php" class="nav_link"
                    >• Kelola Mahasiswa</a
                  >
                  <a href="pages/kelola-dosen.php" class="nav_link"
                    >• Kelola Dosen</a
                  >
                  <a href="pages/kelola-matakuliah.php" class="nav_link"
                    >• Kelola Matakuliah</a
                  >
                  <a href="pages/jadwal-kuliah.php" class="nav_link"
                    >• Jadwal Kuliah</a
                  >
                  <a href="pages/data-nilai.php" class="nav_link"
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
                  <a href="pages/manajemen-user.php" class="nav_link">• Manajemen User</a>
                  <a href="pages/database.php" class="nav_link">• Database</a>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div
          style="display: flex; flex-direction: column; justify-content: center"
        >
        <a href="../../logout.php" class="nav_link"> <i class='bx bx-log-out nav_icon'></i> <span class="nav_name">LogOut</span> </a>
        </div>
      </nav>
    </div>
    
    <main>
      <!-- Konten Utama -->
      <div class="main-content">
        <h2>Selamat Datang, <?= htmlspecialchars($username); ?>!</h2>
      </div>
        <div id="todo">
          <div
            style="
              display: flex;
              align-items: center;
              justify-content: space-between;
            "
          >
            <span style="font-size: 1.5rem">Dashboard</span>
          </div>

          <!-- System Info Section -->
          <div class="card mt-4" style="border-radius: 0.5rem">
            <div class="card-body">
              <div
                style="
                  display: flex;
                  align-items: center;
                  justify-content: space-between;
                "
              >
                <h3>Informasi Sistem</h3>
              </div>
              <p class="card-text" style="font-size: 1rem">
                Selamat Datang di Panel Admin Portal Akademik. Dari sini Anda
                dapat mengelola semua aspek sistem akademik termasuk data
                mahasiswa, dosen, dan matakuliah. Panel admin ini dirancang
                untuk memudahkan pengelolaan data dan proses akademik di UIN
                Sumatera Utara.
              </p>
            </div>
          </div>

          <!-- Enhanced Dashboard Stats Cards -->
          <div class="row mt-4">
            <div class="col-md-3 mb-3">
              <div class="card stats-card" style="border-radius: 0.5rem">
                <div class="card-body">
                  <div style="display: flex; align-items: center; justify-content: space-between">
                    <div>
                      <h5 class="mb-0"><?= number_format($jumlah_mahasiswa) ?></h5>
                      <p class="text-muted mb-0">Mahasiswa</p>
                    </div>
                    <i class="bx bxs-user-detail fs-1 text-primary"></i>
                  </div>
                  <div class="mt-3">
                    <a href="pages/kelola-mahasiswa.php" class="text-primary text-decoration-none">
                      <small>Lihat Detail <i class="bx bx-right-arrow-alt ms-1"></i></small>
                    </a>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card stats-card" style="border-radius: 0.5rem">
                <div class="card-body">
                  <div style="display: flex; align-items: center; justify-content: space-between">
                    <div>
                      <h5 class="mb-0"><?= number_format($jumlah_dosen) ?></h5>
                      <p class="text-muted mb-0">Dosen</p>
                    </div>
                    <i class="bx bxs-user-voice fs-1 text-success"></i>
                  </div>
                  <div class="mt-3">
                    <a href="pages/kelola-dosen.php" class="text-success text-decoration-none">
                      <small>Lihat Detail <i class="bx bx-right-arrow-alt ms-1"></i></small>
                    </a>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card stats-card" style="border-radius: 0.5rem">
                <div class="card-body">
                  <div style="display: flex; align-items: center; justify-content: space-between">
                    <div>
                      <h5 class="mb-0"><?= number_format($jumlah_matkul) ?></h5>
                      <p class="text-muted mb-0">Mata Kuliah</p>
                    </div>
                    <i class="bx bxs-book fs-1 text-warning"></i>
                  </div>
                  <div class="mt-3">
                    <a href="pages/kelola-matakuliah.php" class="text-warning text-decoration-none">
                      <small>Lihat Detail <i class="bx bx-right-arrow-alt ms-1"></i></small>
                    </a>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card stats-card" style="border-radius: 0.5rem">
                <div class="card-body">
                  <div style="display: flex; align-items: center; justify-content: space-between">
                    <div>
                      <h5 class="mb-0"><?= number_format($jadwal) ?></h5>
                      <p class="text-muted mb-0">Jadwal Kuliah</p>
                    </div>
                    <i class="bx bx-calendar fs-1 text-info"></i>
                  </div>
                  <div class="mt-3">
                    <a href="pages/jadwal-kuliah.php" class="text-info text-decoration-none">
                      <small>Lihat Detail <i class="bx bx-right-arrow-alt ms-1"></i></small>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-4" style="border-radius: 0.5rem">
    <div class="card-body">
        <h5 class="mb-3">Jadwal Minggu Ini</h5>
        <div class="d-flex mb-3">
            <?php
            $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $today = date('w'); // 0 untuk Minggu, 1 untuk Senin, dst.
            
            for ($i = 0; $i < 7; $i++) {
                $active = ($i == $today) ? 'active' : '';
                echo "<div class='calendar-day $active'>{$days[$i]}</div>";
            }
            ?>
        </div>
        
        <div class="mt-4">
            <?php
            // Get actual schedule data from database with error handling
            $schedules = [];
            try {
                $today_name = date('l'); // Get current day name in English
                $day_mapping = [
                    'Monday' => 'Senin',
                    'Tuesday' => 'Selasa', 
                    'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis',
                    'Friday' => 'Jumat',
                    'Saturday' => 'Sabtu',
                    'Sunday' => 'Minggu'
                ];
                $hari_indonesia = $day_mapping[$today_name] ?? 'Senin';
                
                $query = "SELECT jk.waktu_mulai, jk.waktu_selesai, m.matakuliah, jk.ruangan, d.nama as nama_dosen 
                          FROM jadwal_kuliah jk 
                          JOIN matkul m ON jk.matkul_id = m.id 
                          JOIN dosen d ON jk.dosen_nip = d.nip
                          WHERE jk.hari = '$hari_indonesia' 
                          ORDER BY jk.waktu_mulai";
                
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $schedules[] = [
                            'time' => date('H:i', strtotime($row['waktu_mulai'])) . ' - ' . date('H:i', strtotime($row['waktu_selesai'])),
                            'course' => $row['matakuliah'],
                            'location' => $row['ruangan'],
                            'lecturer' => $row['nama_dosen']
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Error getting schedule data: " . $e->getMessage());
            }
            
            // Use sample data if no database data
            if (empty($schedules)) {
                $schedules = [
                    ['time' => '08:00 - 09:40', 'course' => 'Pemrograman Web', 'location' => 'Ruana 301', 'lecturer' => 'Afifudin, M.Kom'],
                    ['time' => '10:00 - 11:40', 'course' => 'Analisis dan Perancangan Sistem Informasi', 'location' => 'Lab Komputer 2', 'lecturer' => 'Muhammad Dedi Irawan, S.T M.Kom'],
                    ['time' => '13:00 - 14:40', 'course' => 'Pemrograman Berbasis Objek', 'location' => 'Ruana 305', 'lecturer' => 'Afifudin, M.Kom']
                ];
            }
            
            foreach ($schedules as $schedule) {
                echo "
                <div class='d-flex align-items-start mb-4 p-3' style='background-color:rgb(0, 0, 0); border: 2px solid #00cc6666; border-radius: 0.5rem;'>
                    <div class='bg-black p-2 rounded text-center' style='min-width: 100px'>
                        <small class='text-white d-block'>Waktu</small>
                        <span class='fw-bold text-white'>{$schedule['time']}</span>
                    </div>
                    <div class='ms-3 flex-grow-1'>
                        <h5 class='mb-1'>{$schedule['course']}</h5>
                        <small class='text-muted d-block'><i class='bx bx-user me-1'></i>{$schedule['lecturer']}</small>
                        <div class='d-flex justify-content-between align-items-center mt-2'>
                            <small class='text-muted'><i class='bx bx-map me-1'></i>{$schedule['location']}</small>
                            <button href='pages/jadwal-kuliah.php' class='btn btn-sm' style='background-color:rgb(0, 0, 0); border: 2px solid #00cc6666; color: white;'>Detail</button>
                        </div>
                    </div>
                </div>";
            }
            ?>
        </div>
        
        <div class="text-center mt-3">
            <a href="pages/jadwal-kuliah.php" class="btn" style="background-color:rgb(0, 0, 0); border: 2px solid #00cc6666; color: white;">Lihat Semua Jadwal</a>
        </div>
    </div>
</div>

          <!-- Footer -->
          <footer class="mt-5 mb-3">
            <div class="text-center">
              <p class="text-muted mb-0">© 2025 UIN Sumatera Utara | Portal Sistem Informasi Akademik</p>
              <small class="text-muted">By : Muhammad../.. Hadiansah</small>
            </div>
          </footer>
        </div>
    </main>
    
    <script src="../../js/script.js"></script>
    
    <!-- Custom Script for Charts -->
    <script>
        // Initialize Program Studi Chart
        const prodiData = <?php echo $prodi_json ?: '[]'; ?>;
        
        if (prodiData.length > 0) {
            const labels = prodiData.map(item => item.prodi);
            const values = prodiData.map(item => item.jumlah);
            
            const ctx = document.getElementById('prodiChart').getContext('2d');
            const prodiChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#0d6efd',
                            '#198754',
                            '#ffc107',
                            '#dc3545',
                            '#6f42c1'
                        ],
                        borderWidth:
                                                borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
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
                    },
                    cutout: '70%',
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });
        } else {
            // Handle case when no data is available
            document.getElementById('prodiChart').parentNode.innerHTML = 
                '<div class="alert alert-info text-center">Data distribusi program studi tidak tersedia</div>';
        }
    </script>
  </body>
</html>

<?php
// Close database connection
if (isset($conn) && $conn) {
    $conn->close();
}
?>