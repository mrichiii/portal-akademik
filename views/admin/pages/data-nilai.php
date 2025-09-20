<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
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

// CRUD Operations

// 1. Create - Add New Grade
if (isset($_POST['tambah_nilai'])) {
    $mahasiswa_id = (int)clean_input($_POST['mahasiswa_id']);
    $jadwal_id = (int)clean_input($_POST['jadwal_id']);
    $nilai_tugas = floatval(clean_input($_POST['nilai_tugas']));
    $nilai_uts = floatval(clean_input($_POST['nilai_uts']));
    $nilai_uas = floatval(clean_input($_POST['nilai_uas']));
    $created_by = $_SESSION['user_id'];
    
    // Check if grade already exists
    $check_sql = "SELECT id FROM data_nilai WHERE mahasiswa_id = ? AND jadwal_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $mahasiswa_id, $jadwal_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Grade for this student and course already exists!";
    } else {
        // Validate grades
        if ($nilai_tugas < 0 || $nilai_tugas > 100 || $nilai_uts < 0 || $nilai_uts > 100 || $nilai_uas < 0 || $nilai_uas > 100) {
            $_SESSION['error_message'] = "Grades must be between 0 and 100";
        } else {
            // Insert data
            $sql = "INSERT INTO data_nilai (mahasiswa_id, jadwal_id, nilai_tugas, nilai_uts, nilai_uas, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidddi", $mahasiswa_id, $jadwal_id, $nilai_tugas, $nilai_uts, $nilai_uas, $created_by);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Grade added successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to add grade: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    $check_stmt->close();
    header("Location: data-nilai.php");
    exit();
}

// 2. Update - Edit Grade
if (isset($_POST['edit_nilai'])) {
    $id = (int)clean_input($_POST['id']);
    $nilai_tugas = floatval(clean_input($_POST['nilai_tugas']));
    $nilai_uts = floatval(clean_input($_POST['nilai_uts']));
    $nilai_uas = floatval(clean_input($_POST['nilai_uas']));
    
    // Validate grades
    if ($nilai_tugas < 0 || $nilai_tugas > 100 || $nilai_uts < 0 || $nilai_uts > 100 || $nilai_uas < 0 || $nilai_uas > 100) {
        $_SESSION['error_message'] = "Grades must be between 0 and 100";
    } else {
        // Update data
        $sql = "UPDATE data_nilai SET nilai_tugas = ?, nilai_uts = ?, nilai_uas = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dddi", $nilai_tugas, $nilai_uts, $nilai_uas, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Grade updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update grade: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: data-nilai.php");
    exit();
}

// 3. Delete - Remove Grade
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    // Delete data
    $sql = "DELETE FROM data_nilai WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Grade deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to delete grade: " . $stmt->error;
    }
    $stmt->close();
    header("Location: data-nilai.php");
    exit();
}

// Get data for dropdowns
$mahasiswa_sql = "SELECT id, nim, nama FROM mahasiswa ORDER BY nama ASC";
$mahasiswa_result = $conn->query($mahasiswa_sql);

$jadwal_sql = "SELECT j.id, m.kelas, m.matakuliah, d.nama AS nama_dosen, j.hari, j.waktu_mulai, j.waktu_selesai 
               FROM jadwal_kuliah j
               JOIN matkul m ON j.matkul_id = m.id
               JOIN dosen d ON j.dosen_nip = d.nip
               ORDER BY m.matakuliah ASC";
$jadwal_result = $conn->query($jadwal_sql);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) FROM data_nilai";
$total_rows = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " WHERE m.nim LIKE ? OR m.nama LIKE ? OR mk.matakuliah LIKE ? ";
    $search_param = "%$search%";
}

// Get data with joins and pagination
if (empty($search)) {
    $sql = "SELECT dn.*, 
                   m.nim, m.nama AS nama_mahasiswa, 
                   mk.kelas, mk.matakuliah, mk.sks, 
                   d.nama AS nama_dosen, 
                   j.hari, j.waktu_mulai, j.waktu_selesai
            FROM data_nilai dn
            JOIN mahasiswa m ON dn.mahasiswa_id = m.id
            JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
            JOIN matkul mk ON j.matkul_id = mk.id
            JOIN dosen d ON j.dosen_nip = d.nip
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offset, $per_page);
} else {
    $sql = "SELECT dn.*, 
                   m.nim, m.nama AS nama_mahasiswa, 
                   mk.kelas, mk.matakuliah, mk.sks, 
                   d.nama AS nama_dosen, 
                   j.hari, j.waktu_mulai, j.waktu_selesai
            FROM data_nilai dn
            JOIN mahasiswa m ON dn.mahasiswa_id = m.id
            JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
            JOIN matkul mk ON j.matkul_id = mk.id
            JOIN dosen d ON j.dosen_nip = d.nip
            WHERE m.nim LIKE ? OR m.nama LIKE ? OR mk.matakuliah LIKE ?
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $offset, $per_page);
    
    // Update count for pagination
    $count_sql = "SELECT COUNT(*) 
                  FROM data_nilai dn
                  JOIN mahasiswa m ON dn.mahasiswa_id = m.id
                  JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                  JOIN matkul mk ON j.matkul_id = mk.id
                  WHERE m.nim LIKE ? OR m.nama LIKE ? OR mk.matakuliah LIKE ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
    $total_pages = ceil($total_rows / $per_page);
}

$stmt->execute();
$result = $stmt->get_result();

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
    <title>Data Nilai - Portal Akademik</title>
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
        .grade-A { color: #28a745; font-weight: bold; }
        .grade-A- { color: #5cb85c; font-weight: bold; }
        .grade-B+ { color: #5bc0de; font-weight: bold; }
        .grade-B { color: #5bc0de; font-weight: bold; }
        .grade-B- { color: #5bc0de; font-weight: bold; }
        .grade-C+ { color: #ffc107; font-weight: bold; }
        .grade-C { color: #ffc107; font-weight: bold; }
        .grade-D { color: #fd7e14; font-weight: bold; }
        .grade-E { color: #dc3545; font-weight: bold; }
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
                  <a href="jadwal-kuliah.php" class="nav_link"
                    >• Jadwal Kuliah</a
                  >
                  <a href="data-nilai.php" class="nav_link active"
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
          <h1>Data Nilai Mahasiswa</h1>
          <button class="btn btn-success" onclick="showAddModal()" style="background-color: #000000;">
            <i class="bx bx-plus"></i> Tambah Nilai
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
                <input type="text" class="form-control" name="search" placeholder="Cari NIM, nama mahasiswa, atau mata kuliah..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit">
                  <i class="bx bx-search me-1"></i> Cari
                </button>
              </div>
            </div>
            <?php if (!empty($search)): ?>
            <div class="col-12 mt-2">
              <a href="data-nilai.php" class="btn btn-sm btn-success">
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
                  <th>NIM</th>
                  <th>Mahasiswa</th>
                  <th>Mata Kuliah</th>
                  <th>Dosen</th>
                  <th>Tugas</th>
                  <th>UTS</th>
                  <th>UAS</th>
                  <th>Akhir</th>
                  <th>Grade</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result->num_rows > 0): ?>
                  <?php $no = $offset + 1; ?>
                  <?php while ($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['nim']); ?></td>
                    <td><?php echo htmlspecialchars($row['nama_mahasiswa']); ?></td>
                    <td>
                      <div class="fw-bold"><?php echo htmlspecialchars($row['kelas']); ?></div>
                      <?php echo htmlspecialchars($row['matakuliah']); ?> (<?php echo $row['sks']; ?> SKS)
                    </td>
                    <td><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
                    <td><?php echo number_format($row['nilai_tugas'], 2); ?></td>
                    <td><?php echo number_format($row['nilai_uts'], 2); ?></td>
                    <td><?php echo number_format($row['nilai_uas'], 2); ?></td>
                    <td><?php echo number_format($row['nilai_akhir'], 2); ?></td>
                    <td class="grade-<?php echo $row['grade']; ?>"><?php echo $row['grade']; ?></td>
                    <td class="action-buttons">
                      <a href="#" class="edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                        <i class="bx bxs-edit"></i>
                      </a>
                      <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus nilai ini?');">
                        <i class="bx bxs-trash"></i>
                      </a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="11" style="text-align: center;">
                      <div class="text-muted">
                        <i class="bx bx-calendar-x bx-lg mb-2"></i>
                        <p class="mb-0">Tidak ada data nilai ditemukan</p>
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

      <!-- Modal Tambah Nilai -->
      <div id="addModal" class="modal">
        <div class="modal-content">
          <h2>Tambah Nilai Baru</h2>
          
          <form method="POST" action="">
            <!-- Mahasiswa -->
            <div class="form-group">
              <label for="mahasiswa_id">Mahasiswa</label>
              <select id="mahasiswa_id" name="mahasiswa_id" required>
                <option value="">-- Pilih Mahasiswa --</option>
                <?php if ($mahasiswa_result && $mahasiswa_result->num_rows > 0): ?>
                  <?php while ($mahasiswa_row = $mahasiswa_result->fetch_assoc()): ?>
                    <option value="<?php echo $mahasiswa_row['id']; ?>">
                      <?php echo htmlspecialchars($mahasiswa_row['nama']); ?> (<?php echo htmlspecialchars($mahasiswa_row['nim']); ?>)
                    </option>
                  <?php endwhile; ?>
                <?php else: ?>
                  <option disabled>Tidak ada mahasiswa tersedia</option>
                <?php endif; ?>
              </select>
            </div>
            
            <!-- Mata Kuliah -->
            <div class="form-group">
              <label for="jadwal_id">Mata Kuliah</label>
              <select id="jadwal_id" name="jadwal_id" required>
                <option value="">-- Pilih Mata Kuliah --</option>
                <?php if ($jadwal_result && $jadwal_result->num_rows > 0): ?>
                  <?php while ($jadwal_row = $jadwal_result->fetch_assoc()): ?>
                    <option value="<?php echo $jadwal_row['id']; ?>">
                      <?php echo htmlspecialchars($jadwal_row['kelas'] . ' - ' . $jadwal_row['matakuliah']); ?> - 
                      <?php echo htmlspecialchars($jadwal_row['nama_dosen']); ?>
                      (<?php echo $jadwal_row['hari']; ?> <?php echo $jadwal_row['waktu_mulai']; ?>-<?php echo $jadwal_row['waktu_selesai']; ?>)
                    </option>
                  <?php endwhile; ?>
                <?php else: ?>
                  <option disabled>Tidak ada jadwal kuliah tersedia</option>
                <?php endif; ?>
              </select>
            </div>
            
            <!-- Nilai -->
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="nilai_tugas">Nilai Tugas</label>
                  <input type="number" step="0.01" min="0" max="100" id="nilai_tugas" name="nilai_tugas" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="nilai_uts">Nilai UTS</label>
                  <input type="number" step="0.01" min="0" max="100" id="nilai_uts" name="nilai_uts" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="nilai_uas">Nilai UAS</label>
                  <input type="number" step="0.01" min="0" max="100" id="nilai_uas" name="nilai_uas" required>
                </div>
              </div>
            </div>
            
            <div class="alert alert-info">
              <i class="bx bx-info-circle me-2"></i>
              Nilai akhir dan grade akan dihitung otomatis berdasarkan formula:
              <ul class="mb-0 mt-2">
                <li>Nilai Akhir = (30% Tugas + 30% UTS + 40% UAS)</li>
                <li>Grade sesuai dengan standar penilaian universitas</li>
              </ul>
            </div>

            <div class="form-group">
              <button type="submit" name="tambah_nilai" class="btn btn-primary">Simpan</button>
              <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Batal</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Edit Nilai -->
      <div id="editModal" class="modal">
        <div class="modal-content">
          <h2>Edit Nilai</h2>
          
          <form method="POST" action="">
            <input type="hidden" id="edit_id" name="id">
            
            <!-- Mahasiswa -->
            <div class="form-group">
              <label>Mahasiswa</label>
              <input type="text" id="edit_mahasiswa" readonly>
            </div>
            
            <!-- Mata Kuliah -->
            <div class="form-group">
              <label>Mata Kuliah</label>
              <input type="text" id="edit_matakuliah" readonly>
            </div>
            
            <!-- Nilai -->
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="edit_nilai_tugas">Nilai Tugas</label>
                  <input type="number" step="0.01" min="0" max="100" id="edit_nilai_tugas" name="nilai_tugas" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="edit_nilai_uts">Nilai UTS</label>
                  <input type="number" step="0.01" min="0" max="100" id="edit_nilai_uts" name="nilai_uts" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="edit_nilai_uas">Nilai UAS</label>
                  <input type="number" step="0.01" min="0" max="100" id="edit_nilai_uas" name="nilai_uas" required>
                </div>
              </div>
            </div>
            
            <div class="alert alert-info">
              <i class="bx bx-info-circle me-2"></i>
              Nilai akhir dan grade akan dihitung otomatis berdasarkan formula:
              <ul class="mb-0 mt-2">
                <li>Nilai Akhir = (30% Tugas + 30% UTS + 40% UAS)</li>
                <li>Grade sesuai dengan standar penilaian universitas</li>
              </ul>
            </div>

            <div class="form-group">
              <button type="submit" name="edit_nilai" class="btn btn-primary">Simpan Perubahan</button>
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
          document.getElementById('edit_mahasiswa').value = data.nama_mahasiswa + ' (' + data.nim + ')';
          document.getElementById('edit_matakuliah').value = data.kelas + ' - ' + data.matakuliah;
          document.getElementById('edit_nilai_tugas').value = data.nilai_tugas;
          document.getElementById('edit_nilai_uts').value = data.nilai_uts;
          document.getElementById('edit_nilai_uas').value = data.nilai_uas;
          
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