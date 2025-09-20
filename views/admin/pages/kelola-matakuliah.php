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

// Add mata kuliah process
if (isset($_POST['tambah_matkul'])) {
    $kode = clean_input($_POST['kode']);
    $matakuliah = clean_input($_POST['matakuliah']);
    $dosen = clean_input($_POST['dosen']);
    $wp = clean_input($_POST['wp']);
    $sks = (int)clean_input($_POST['sks']);
    $semester = (int)clean_input($_POST['semester']);
    $prodi = clean_input($_POST['prodi']);

    // Validasi input
    if (empty($kode) || empty($matakuliah) || empty($sks) || empty($semester) || empty($prodi)) {
        $_SESSION['error_message'] = "Field wajib harus diisi";
        header("Location: kelola-matakuliah.php");
        exit();
    }

    // Additional validation
    if ($sks < 1 || $sks > 10) {
        $_SESSION['error_message'] = "Nilai SKS harus antara 1 sampai 10";
        header("Location: kelola-matakuliah.php");
        exit();
    }

    if ($semester < 1 || $semester > 14) {
        $_SESSION['error_message'] = "Semester harus antara 1 sampai 14";
        header("Location: kelola-matakuliah.php");
        exit();
    }

    // Check if kode already exists
    $check_sql = "SELECT kode FROM matkul WHERE kode = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $kode);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error_message'] = "Kode mata kuliah sudah terdaftar";
        $check_stmt->close();
        header("Location: kelola-matakuliah.php");
        exit();
    }
    $check_stmt->close();

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "INSERT INTO matkul (kode, matakuliah, dosen, wp, sks, semester, prodi) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiis", $kode, $matakuliah, $dosen, $wp, $sks, $semester, $prodi);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mata kuliah baru berhasil ditambahkan";
            $conn->commit();
        } else {
            throw new Exception("Gagal menambahkan mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-matakuliah.php");
    exit();
}

// Edit mata kuliah process
if (isset($_POST['edit_matkul'])) {
    $id = clean_input($_POST['id']);
    $kode = clean_input($_POST['kode']);
    $matakuliah = clean_input($_POST['matakuliah']);
    $dosen = clean_input($_POST['dosen']);
    $kelas = clean_input($_POST['kelas']);
    $wp = clean_input($_POST['wp']);
    $sks = (int)clean_input($_POST['sks']);
    $semester = (int)clean_input($_POST['semester']);
    $prodi = clean_input($_POST['prodi']);

    // Validasi input
    if (empty($kode) || empty($matakuliah) || empty($sks) || empty($semester) || empty($prodi)) {
        $_SESSION['error_message'] = "Field wajib harus diisi";
        header("Location: kelola-matakuliah.php");
        exit();
    }

    // Additional validation
    if ($sks < 1 || $sks > 10) {
        $_SESSION['error_message'] = "Nilai SKS harus antara 1 sampai 10";
        header("Location: kelola-matakuliah.php");
        exit();
    }

    if ($semester < 1 || $semester > 14) {
        $_SESSION['error_message'] = "Semester harus antara 1 sampai 14";
        header("Location: kelola-matakuliah.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if this course has associated data in other tables
        $check_jadwal_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE matkul_id = ?";
        $check_jadwal_stmt = $conn->prepare($check_jadwal_sql);
        $check_jadwal_stmt->bind_param("i", $id);
        $check_jadwal_stmt->execute();
        $check_jadwal_stmt->bind_result($jadwal_count);
        $check_jadwal_stmt->fetch();
        $check_jadwal_stmt->close();
        
        if ($jadwal_count > 0) {
            throw new Exception("Mata kuliah tidak dapat diubah karena sudah digunakan dalam jadwal");
        }
        
        $sql = "UPDATE matkul SET 
                kode = ?,
                matakuliah = ?,
                dosen = ?,
                kelas = ?,
                wp = ?,
                sks = ?,
                semester = ?,
                prodi = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiisi", $kode, $matakuliah, $dosen, $kelas, $wp, $sks, $semester, $prodi, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data mata kuliah berhasil diperbarui";
            $conn->commit();
        } else {
            throw new Exception("Gagal memperbarui data mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-matakuliah.php");
    exit();
}

// Delete mata kuliah process
if (isset($_GET['delete_id'])) {
    $id = clean_input($_GET['delete_id']);
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if mata kuliah has any related records
        $check_jadwal_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE matkul_id = ?";
        $check_jadwal_stmt = $conn->prepare($check_jadwal_sql);
        $check_jadwal_stmt->bind_param("i", $id);
        $check_jadwal_stmt->execute();
        $check_jadwal_stmt->bind_result($jadwal_count);
        $check_jadwal_stmt->fetch();
        $check_jadwal_stmt->close();
        
        if ($jadwal_count > 0) {
            throw new Exception("Mata kuliah tidak dapat dihapus karena digunakan dalam jadwal");
        }
        
        // Get mata kuliah details before deletion
        $get_matkul_sql = "SELECT matakuliah FROM matkul WHERE id = ?";
        $get_matkul_stmt = $conn->prepare($get_matkul_sql);
        $get_matkul_stmt->bind_param("i", $id);
        $get_matkul_stmt->execute();
        $get_matkul_stmt->bind_result($nama_matkul);
        $get_matkul_stmt->fetch();
        $get_matkul_stmt->close();

        $sql = "DELETE FROM matkul WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mata kuliah berhasil dihapus";
            $conn->commit();
        } else {
            throw new Exception("Gagal menghapus mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-matakuliah.php");
    exit();
}

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Get nama dosen for dropdown
$dosen_sql = "SELECT nama FROM dosen ORDER BY nama ASC";
$dosen_result = $conn->query($dosen_sql);


// Get mata kuliah data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) FROM matkul";
$total_rows = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " WHERE kode LIKE ? OR matakuliah LIKE ? ";
    $search_param = "%$search%";
}

// Get paginated data with program studi information
if (empty($search)) {
    $sql = "SELECT m.*, ps.nama_prodi, d.nama AS nama_dosen 
            FROM matkul m 
            LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
            LEFT JOIN dosen d ON TRIM(m.dosen) = TRIM(d.nama)
            ORDER BY m.matakuliah ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("ii", $offset, $per_page);
} else {
    $sql = "SELECT m.*, ps.nama_prodi, d.nama AS nama_dosen 
            FROM matkul m 
            LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
            LEFT JOIN dosen d ON TRIM(m.dosen) = TRIM(d.nama)
            WHERE m.kode LIKE ? OR m.matakuliah LIKE ?
            ORDER BY m.matakuliah ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("ssii", $search_param, $search_param, $offset, $per_page);
    
    // Also update the count for pagination
    $count_sql = "SELECT COUNT(*) FROM matkul WHERE kode LIKE ? OR matakuliah LIKE ?";
    $count_stmt = $conn->prepare($count_sql);
    if (!$count_stmt) {
        die("Error preparing count statement: " . $conn->error);
    }
    $count_stmt->bind_param("ss", $search_param, $search_param);
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


$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Kelola Matakuliah</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
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
                  <a href="kelola-matakuliah.php" class="nav_link active"
                    >• Kelola Matakuliah</a
                  >
                  <a href="jadwal-kuliah.php" class="nav_link"
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
          <h1>Kelola Matakuliah</h1>
          <button class="btn btn-success" onclick="showAddModal()" style="background-color: #000000;">
            <i class="bx bx-plus"></i> Tambah Mata Kuliah
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
                <input type="text" class="form-control" name="search" placeholder="Cari kode atau nama mata kuliah..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-success" type="submit">
                  <i class="bx bx-search me-1"></i> Cari
                </button>
              </div>
            </div>
            <?php if (!empty($search)): ?>
            <div class="col-auto">
              <a href="kelola-matakuliah.php" class="btn btn-success">
                <i class="bx bx-times me-1"></i> Reset
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
                  <th>Kode</th>
                  <th>Mata Kuliah</th>
                  <th>Nama Dosen</th>
                  <th>Kelas</th>
                  <th>W/P</th>
                  <th>SKS</th>
                  <th>Semester</th>
                  <th>Program Studi</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                if ($result && $result->num_rows > 0) {
                    $no = $offset + 1; // Start numbering from current offset
                    while($row = $result->fetch_assoc()) { 
                ?>
                <tr>
                  <td><?php echo $no++; ?></td>
                  <td><?php echo htmlspecialchars($row['kode']); ?></td>
                  <td><?php echo htmlspecialchars($row['matakuliah'] ? $row['matakuliah'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['nama_dosen'] ? $row['nama_dosen'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['kelas'] ? $row['kelas'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['wp'] ? $row['wp'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['sks'] ? $row['sks'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['semester']); ?></td>
                  <td><?php echo htmlspecialchars($row['nama_prodi'] ?: '-'); ?></td>
                  <td class="action-buttons">
                    <a href="#" class="edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                      <i class="bx bxs-edit"></i>
                    </a>
                    <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus mata kuliah ini?');">
                      <i class="bx bxs-trash"></i>
                    </a>
                  </td>
                </tr>
                <?php 
                    }
                } else {
                ?>
                <tr>
                  <td colspan="8" style="text-align: center;">Tidak ada data Matakuliah</td>
                </tr>
                <?php } ?>
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

      <!-- Modal Tambah Matakuliah -->
      <div id="addModal" class="modal">
        <div class="modal-content">
          <h2>Tambah Mata Kuliah Baru</h2>
          
          <form method="POST" action="">
            <!-- Kode Mata Kuliah -->
            <div class="form-group">
              <label for="kode">Kode</label>
              <input type="text" id="kode" name="kode" required>
            </div>

            <!-- Nama Mata Kuliah -->
            <div class="form-group">
              <label for="matakuliah">Mata Kuliah</label>
              <input type="text" id="matakuliah" name="matakuliah" required>
            </div>

            <!-- Dosen Pengampu -->
            <div class="form-group">
              <label for="dosen">Dosen Pengampu</label>
              <select id="dosen" name="dosen" class="form-select" required>
                <option value="">-- Pilih Dosen Pengampu --</option>
                <?php if ($dosen_result && $dosen_result->num_rows > 0): ?>
                  <?php while ($dosen_row = $dosen_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($dosen_row['nama']); ?>">
                      <?php echo htmlspecialchars($dosen_row['nama']); ?>
                    </option>
                  <?php endwhile; ?>
                <?php else: ?>
                  <option disabled>Tidak ada dosen tersedia</option>
                <?php endif; ?>
              </select>
            </div>

            <!-- W/P -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="wp">W/P</label>
                  <select id="wp" name="wp" required>
                    <option value="">-- Pilih W/P --</option>
                    <option value="W">W</option>
                    <option value="P">P</option>
                  </select>
                </div>
              </div>

            <!-- SKS -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="sks">SKS</label>
                  <input type="number" id="sks" name="sks" min="1" max="12" required>
                </div>
              </div>
            </div>

            <!-- Semester -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="semester" class="form-label">Semester</label>
                  <select id="semester" class="form-select" name="semester" required>
                    <option value="">-- Pilih Semester --</option>
                    <?php for ($i = 1; $i <= 14; $i++): ?>
                      <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>

            <!-- Program Studi -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="prodi" class="form-label">Program Studi</label>
                  <select id="prodi" class="form-select" name="prodi" required>
                    <option value="">-- Pilih Program Studi --</option>
                    <?php while($prodi_row = $prodi_result->fetch_assoc()): ?>
                      <option value="<?php echo $prodi_row['kode_prodi']; ?>">
                        <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                      </option>
                    <?php endwhile; ?>
                    <?php $prodi_result->data_seek(0); ?>
                  </select>
                </div>
              </div>
            </div>  

            <!-- Tombol Aksi -->
            <div class="form-group d-flex justify-content-end gap-2 mt-3">
              <button type="button" onclick="closeAddModal()" class="btn btn-danger">
                Batal
              </button>
              <button type="submit" name="tambah_matkul" class="btn btn-success">
                Tambah User
              </button>
            </div>
          </form>
        </div>
      </div>

    
      <!-- Modal Edit Matakuliah -->
      <div id="editModal" class="modal">
        <div class="modal-content">
          <h2>Edit Data Matakuliah</h2>
          <form method="POST" action="">
            <input type="hidden" id="edit_id" name="id">
            
            <!-- Kode Mata Kuliah -->
            <div class="form-group">
              <label for="edit_kode">Kode</label>
              <input type="text" id="edit_kode" name="kode" required>
            </div>

            <!-- Nama Mata Kuliah -->
            <div class="form-group">
              <label for="edit_matakuliah">Mata Kuliah</label>
              <input type="text" id="edit_matakuliah" name="matakuliah" required>
            </div>

            <!-- Dosen Pengampu -->
            <div class="form-group">
              <label for="edit_dosen">Dosen Pengampu</label>
              <select id="edit_dosen" name="dosen" class="form-select">
                <option value="">-- Pilih Dosen Pengampu --</option>
                <?php 
                $dosen_result->data_seek(0);
                if ($dosen_result && $dosen_result->num_rows > 0): ?>
                  <?php while ($dosen_row = $dosen_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($dosen_row['nama']); ?>">
                      <?php echo htmlspecialchars($dosen_row['nama']); ?>
                    </option>
                  <?php endwhile; ?>
                <?php else: ?>
                  <option disabled>Tidak ada dosen tersedia</option>
                <?php endif; ?>
              </select>
            </div>

            <!-- Kelas -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_kelas">Kelas</label>
                  <input type="text" id="edit_kelas" name="kelas">
                </div>
              </div>

            <!-- W/P -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_wp">W/P</label>
                  <select id="edit_wp" name="wp">
                    <option value="">-- Pilih W/P --</option>
                    <option value="W">W (Wajib)</option>
                    <option value="P">P (Pilihan)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- SKS -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_sks">SKS</label>
                  <input type="number" id="edit_sks" name="sks" min="1" max="10" required>
                </div>
              </div>

            <!-- Semester -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_semester" class="form-label">Semester</label>
                  <select id="edit_semester" class="form-select" name="semester" required>
                    <option value="">-- Pilih Semester --</option>
                    <?php for ($i = 1; $i <= 14; $i++): ?>
                      <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Program Studi -->
            <div class="form-group">
              <label for="edit_prodi" class="form-label">Program Studi</label>
              <select id="edit_prodi" class="form-select" name="prodi" required>
                <option value="">-- Pilih Program Studi --</option>
                <?php 
                $prodi_result->data_seek(0);
                while($prodi_row = $prodi_result->fetch_assoc()): ?>
                  <option value="<?php echo $prodi_row['kode_prodi']; ?>">
                    <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <!-- Tombol Aksi -->
            <div class="form-group d-flex justify-content-end gap-2 mt-3">
              <button type="button" onclick="closeEditModal()" class="btn btn-danger">
                Batal
              </button>
              <button type="submit" name="edit_matkul" class="btn btn-success">
                Simpan Perubahan
              </button>
            </div>
          </form>
        </div>
      </div>

    <script src="../../../js/script.js"></script>
    <script>
      // JavaScript untuk Modal
      function showAddModal() {
        document.getElementById('addModal').style.display = 'block';
      }
      
      function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
      }
      
      function showEditModal(data) {
      // Populate form fields with data
      document.getElementById('edit_id').value = data.id;
      document.getElementById('edit_kode').value = data.kode;
      document.getElementById('edit_matakuliah').value = data.matakuliah;
      
      // Set selected option for dosen
      const dosenSelect = document.getElementById('edit_dosen');
      if (data.nama_dosen) {
          for (let i = 0; i < dosenSelect.options.length; i++) {
              if (dosenSelect.options[i].value === data.nama_dosen) {
                  dosenSelect.selectedIndex = i;
                  break;
              }
          }
      }
      
      document.getElementById('edit_kelas').value = data.kelas || '';
      
      // Set selected option for wp
      const wpSelect = document.getElementById('edit_wp');
      if (data.wp) {
        for (let i = 0; i < wpSelect.options.length; i++) {
          if (wpSelect.options[i].value === data.wp) {
            wpSelect.selectedIndex = i;
            break;
          }
        }
      }
      
      document.getElementById('edit_sks').value = data.sks || '';
      
      // Set selected option for semester
      const semesterSelect = document.getElementById('edit_semester');
      if (data.semester) {
        for (let i = 0; i < semesterSelect.options.length; i++) {
          if (parseInt(semesterSelect.options[i].value) === parseInt(data.semester)) {
            semesterSelect.selectedIndex = i;
            break;
          }
        }
      }
      
      // Set selected option for prodi
      const prodiSelect = document.getElementById('edit_prodi');
      if (data.prodi) {
        for (let i = 0; i < prodiSelect.options.length; i++) {
          if (prodiSelect.options[i].value === data.prodi) {
            prodiSelect.selectedIndex = i;
            break;
          }
        }
      }
      
      // Show modal
      document.getElementById('editModal').style.display = 'block';
    }
      
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
      }
      // Menutup modal jika user klik di luar modal
      window.onclick = function(event) {
        if (event.target == document.getElementById('addModal')) {
          closeAddModal();
        }
        if (event.target == document.getElementById('editModal')) {
          closeEditModal();
        }
      }
      
      // Hilangkan alert setelah beberapa detik
      setTimeout(function() {
        var alerts = document.getElementsByClassName('alert');
        for(var i = 0; i < alerts.length; i++) {
          alerts[i].style.display = 'none';
        }
      }, 3000);
      
    </script>
  </body>
</html>

<?php
// Tutup koneksi database
$conn->close();
?>