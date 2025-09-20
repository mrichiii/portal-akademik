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

// Add student process
if (isset($_POST['tambah_mahasiswa'])) {
    $nim = clean_input($_POST['nim']);
    $nama = clean_input($_POST['nama']);
    $prodi = clean_input($_POST['prodi']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nim) || empty($nama) || empty($prodi)) {
        $_SESSION['error_message'] = "NIM, Nama dan Program Studi harus diisi";
        header("Location: kelola-mahasiswa.php");
        exit();
    }

    // Check if NIM already exists
    $check_sql = "SELECT nim FROM mahasiswa WHERE nim = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $nim);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error_message'] = "NIM sudah terdaftar dalam sistem";
        $check_stmt->close();
        header("Location: kelola-mahasiswa.php");
        exit();
    }
    $check_stmt->close();

    // Check if user_id is valid and has 'mahasiswa' role if provided
    if ($user_id !== null) {
        $check_user_sql = "SELECT id FROM users WHERE id = ? AND role = 'mahasiswa'";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("i", $user_id);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "ID User tidak valid atau bukan role mahasiswa";
            $check_user_stmt->close();
            header("Location: kelola-mahasiswa.php");
            exit();
        }
        $check_user_stmt->close();
        
        // Check if user_id is already linked to another student
        $check_link_sql = "SELECT id FROM mahasiswa WHERE user_id = ?";
        $check_link_stmt = $conn->prepare($check_link_sql);
        $check_link_stmt->bind_param("i", $user_id);
        $check_link_stmt->execute();
        $check_link_stmt->store_result();
        
        if ($check_link_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "User ID sudah terhubung dengan mahasiswa lain";
            $check_link_stmt->close();
            header("Location: kelola-mahasiswa.php");
            exit();
        }
        $check_link_stmt->close();
    }

    // Verify if prodi exists
    $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
    $check_prodi_stmt = $conn->prepare($check_prodi_sql);
    $check_prodi_stmt->bind_param("s", $prodi);
    $check_prodi_stmt->execute();
    $check_prodi_stmt->store_result();
    
    if ($check_prodi_stmt->num_rows == 0) {
        $_SESSION['error_message'] = "Program Studi tidak ditemukan";
        $check_prodi_stmt->close();
        header("Location: kelola-mahasiswa.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        if ($user_id === null) {
            $sql = "INSERT INTO mahasiswa (nim, nama, prodi, alamat, nohp, email) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $nim, $nama, $prodi, $alamat, $nohp, $email);
        } else {
            $sql = "INSERT INTO mahasiswa (nim, nama, prodi, alamat, nohp, email, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $nim, $nama, $prodi, $alamat, $nohp, $email, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mahasiswa baru berhasil ditambahkan";
            $conn->commit();
        } else {
            throw new Exception("Gagal menambahkan mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-mahasiswa.php");
    exit();
}

// Edit student process
if (isset($_POST['edit_mahasiswa'])) {
    $id = (int)clean_input($_POST['id']);
    $nim = clean_input($_POST['nim']);
    $old_nim = clean_input($_POST['old_nim']);
    $nama = clean_input($_POST['nama']);
    $prodi = clean_input($_POST['prodi']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nim) || empty($nama) || empty($prodi)) {
        $_SESSION['error_message'] = "NIM, Nama dan Program Studi harus diisi";
        header("Location: kelola-mahasiswa.php");
        exit();
    }

    // Check if NIM is being changed to an existing one
    if ($old_nim != $nim) {
        $check_sql = "SELECT nim FROM mahasiswa WHERE nim = ? AND nim != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $nim, $old_nim);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "NIM sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: kelola-mahasiswa.php");
            exit();
        }
        $check_stmt->close();
    }

    // Check if user_id is valid and has 'mahasiswa' role if provided
    if ($user_id !== null) {
        $check_user_sql = "SELECT id FROM users WHERE id = ? AND role = 'mahasiswa'";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("i", $user_id);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "ID User tidak valid atau bukan role mahasiswa";
            $check_user_stmt->close();
            header("Location: kelola-mahasiswa.php");
            exit();
        }
        $check_user_stmt->close();
        
        // Check if user_id is being changed to one linked to another student
        $check_link_sql = "SELECT id FROM mahasiswa WHERE user_id = ? AND nim != ?";
        $check_link_stmt = $conn->prepare($check_link_sql);
        $check_link_stmt->bind_param("is", $user_id, $old_nim);
        $check_link_stmt->execute();
        $check_link_stmt->store_result();
        
        if ($check_link_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "User ID sudah terhubung dengan mahasiswa lain";
            $check_link_stmt->close();
            header("Location: kelola-mahasiswa.php");
            exit();
        }
        $check_link_stmt->close();
    }

    // Verify if prodi exists
    $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
    $check_prodi_stmt = $conn->prepare($check_prodi_sql);
    $check_prodi_stmt->bind_param("s", $prodi);
    $check_prodi_stmt->execute();
    $check_prodi_stmt->store_result();
    
    if ($check_prodi_stmt->num_rows == 0) {
        $_SESSION['error_message'] = "Program Studi tidak ditemukan";
        $check_prodi_stmt->close();
        header("Location: kelola-mahasiswa.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "UPDATE mahasiswa SET 
                nim = ?, 
                nama = ?, 
                prodi = ?, 
                alamat = ?, 
                nohp = ?, 
                email = ?,
                user_id = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssii", $nim, $nama, $prodi, $alamat, $nohp, $email, $user_id, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data mahasiswa berhasil diperbarui";
            $conn->commit();
        } else {
            throw new Exception("Gagal memperbarui data mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-mahasiswa.php");
    exit();
}

// Delete student process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if student has any related records
        $check_krs_sql = "SELECT COUNT(*) FROM krs WHERE mahasiswa_id = ?";
        $check_krs_stmt = $conn->prepare($check_krs_sql);
        $check_krs_stmt->bind_param("i", $id);
        $check_krs_stmt->execute();
        $check_krs_stmt->bind_result($krs_count);
        $check_krs_stmt->fetch();
        $check_krs_stmt->close();
        
        if ($krs_count > 0) {
            throw new Exception("Mahasiswa tidak dapat dihapus karena memiliki data KRS");
        }
        
        $check_nilai_sql = "SELECT COUNT(*) FROM data_nilai WHERE mahasiswa_id = ?";
        $check_nilai_stmt = $conn->prepare($check_nilai_sql);
        $check_nilai_stmt->bind_param("i", $id);
        $check_nilai_stmt->execute();
        $check_nilai_stmt->bind_result($nilai_count);
        $check_nilai_stmt->fetch();
        $check_nilai_stmt->close();
        
        if ($nilai_count > 0) {
            throw new Exception("Mahasiswa tidak dapat dihapus karena memiliki data nilai");
        }
        
        // Get student details before deletion
        $get_mhs_sql = "SELECT nama FROM mahasiswa WHERE id = ?";
        $get_mhs_stmt = $conn->prepare($get_mhs_sql);
        $get_mhs_stmt->bind_param("i", $id);
        $get_mhs_stmt->execute();
        $get_mhs_stmt->bind_result($nama_mhs);
        $get_mhs_stmt->fetch();
        $get_mhs_stmt->close();

        $sql = "DELETE FROM mahasiswa WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mahasiswa '$nama_mhs' berhasil dihapus";
            $conn->commit();
        } else {
            throw new Exception("Gagal menghapus mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-mahasiswa.php");
    exit();
}

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Get users with 'mahasiswa' role for dropdown (only those not already linked)
$users_sql = "SELECT u.id, u.username 
              FROM users u
              LEFT JOIN mahasiswa m ON u.id = m.user_id
              WHERE u.role = 'mahasiswa' AND m.user_id IS NULL
              ORDER BY u.username ASC";
$users_result = $conn->query($users_sql);

// Get student data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " WHERE m.nim LIKE ? OR m.nama LIKE ? ";
    $search_param = "%$search%";
}

// Count total records
if (empty($search)) {
    $count_sql = "SELECT COUNT(*) FROM mahasiswa";
    $total_rows = $conn->query($count_sql)->fetch_row()[0];
} else {
    $count_sql = "SELECT COUNT(*) FROM mahasiswa WHERE nim LIKE ? OR nama LIKE ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ss", $search_param, $search_param);
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
}

$total_pages = ceil($total_rows / $per_page);

// Get paginated data with program studi information
if (empty($search)) {
    $sql = "SELECT m.*, ps.nama_prodi 
            FROM mahasiswa m 
            LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offset, $per_page);
} else {
    $sql = "SELECT m.*, ps.nama_prodi 
            FROM mahasiswa m 
            LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
            WHERE m.nim LIKE ? OR m.nama LIKE ?
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $search_param, $search_param, $offset, $per_page);
}

$stmt->execute();
$result = $stmt->get_result();

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');

// Store session messages and clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Kelola Mahasiswa</title>
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
                  <a href="kelola-mahasiswa.php" class="nav_link active"
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
          <h1>Kelola Mahasiswa</h1>
          <button class="btn btn-success" onclick="showAddModal()" style="background-color: #000000;">
            <i class="bx bx-plus"></i> Tambah Mahasiswa
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
              <div class="input-group" >
                <input type="text" class="form-control" name="search" placeholder="Cari NIM atau nama mahasiswa..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit">
                  <i class="bx bx-search me-1"></i> Cari
                </button>
              </div>
            </div>
            <?php if (!empty($search)): ?>
            <div class="col-auto">
              <a href="kelola-mahasiswa.php" class="btn btn-success">
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
                  <th>NIM</th>
                  <th>Nama Mahasiswa</th>
                  <th>Program Studi</th>
                  <th>No. HP</th>
                  <th>Email</th>
                  <th>User ID</th>
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
                  <td><?php echo htmlspecialchars($row['nim']); ?></td>
                  <td><?php echo htmlspecialchars($row['nama']); ?></td>
                  <td><?php echo htmlspecialchars($row['nama_prodi'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['nohp'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['user_id'] ?: '-'); ?></td>
                  <td class="action-buttons">
                    <a href="#" class="edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                      <i class="bx bxs-edit"></i>
                    </a>
                    <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus mahasiswa \'<?php echo htmlspecialchars($row['nama']); ?>\'?');">
                      <i class="bx bxs-trash"></i>
                    </a>
                  </td>
                </tr>
                <?php 
                    }
                } else {
                ?>
                <tr>
                  <td colspan="8" style="text-align: center;">Tidak ada data Mahasiswa</td>
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

      <!-- Modal Tambah Mahasiswa -->
      <div id="addModal" class="modal">
        <div class="modal-content">
          <h2>Tambah Mahasiswa Baru</h2>
          
          <form method="POST" action="">
            <!-- NIM -->
            <div class="form-group">
              <label for="nim">NIM</label>
              <input type="text" id="nim" name="nim" required>
            </div>

            <!-- Nama -->
            <div class="form-group">
              <label for="nama">Nama Mahasiswa</label>
              <input type="text" id="nama" name="nama" required>
            </div>

            <!-- Program Studi -->
             <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="prodi">Program Studi</label>
                  <select id="prodi" name="prodi" class="form-select" required>
                    <option value="">-- Pilih Program Studi --</option>
                    <?php if ($prodi_result && $prodi_result->num_rows > 0): ?>
                      <?php while ($prodi_row = $prodi_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($prodi_row['kode_prodi']); ?>">
                          <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                        </option>
                      <?php endwhile; ?>
                    <?php endif; ?>
                  </select>
                </div>
              </div>

            <!-- User ID -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="user_id">Akun User (Opsional)</label>
                  <select id="user_id" name="user_id" class="form-select">
                    <option value="">-- Pilih Akun User --</option>
                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                      <?php while ($user_row = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($user_row['id']); ?>">
                          <?php echo htmlspecialchars($user_row['username']); ?>
                        </option>
                      <?php endwhile; ?>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- No. HP -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="nohp">No. HP</label>
                  <input type="text" id="nohp" name="nohp">
                </div>
              </div>  

            <!-- Email -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="email">Email</label>
                  <input type="email" id="email" name="email">
                </div>
              </div>
            </div>

            <!-- Alamat -->
            <div class="form-group">
              <label for="alamat">Alamat</label>
              <textarea id="alamat" name="alamat" rows="3" class="form-control"></textarea>
            </div>

            <!-- Tombol Aksi -->
            <div class="form-group d-flex justify-content-end gap-2 mt-3">
              <button type="button" onclick="closeAddModal()" class="btn btn-danger">
                Batal
              </button>
              <button type="submit" name="tambah_mahasiswa" class="btn btn-success">
                Tambah Mahasiswa
              </button>
            </div>
          </form>
        </div>
      </div>
    
      <!-- Modal Edit Mahasiswa -->
      <div id="editModal" class="modal">
        <div class="modal-content">
          <h2>Edit Data Mahasiswa</h2>
          <form method="POST" action="">
            <input type="hidden" id="edit_id" name="id">
            <input type="hidden" id="edit_old_nim" name="old_nim">
            
            <!-- NIM -->
            <div class="form-group">
              <label for="edit_nim">NIM</label>
              <input type="text" id="edit_nim" name="nim" required>
            </div>

            <!-- Nama -->
            <div class="form-group">
              <label for="edit_nama">Nama Mahasiswa</label>
              <input type="text" id="edit_nama" name="nama" required>
            </div>

            <!-- Program Studi -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_prodi">Program Studi</label>
                  <select id="edit_prodi" name="prodi" class="form-select" required>
                    <option value="">-- Pilih Program Studi --</option>
                    <?php 
                    $prodi_result->data_seek(0);
                    if ($prodi_result && $prodi_result->num_rows > 0): ?>
                      <?php while ($prodi_row = $prodi_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($prodi_row['kode_prodi']); ?>">
                          <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                        </option>
                      <?php endwhile; ?>
                    <?php endif; ?>
                  </select>
                </div>
              </div>

            <!-- User ID -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_user_id">Akun User (Opsional)</label>
                  <select id="edit_user_id" name="user_id" class="form-select">
                    <option value="">-- Pilih Akun User --</option>
                    <option value="0">Hapus koneksi akun</option>
                    <?php 
                    $users_result->data_seek(0);
                    if ($users_result && $users_result->num_rows > 0): ?>
                      <?php while ($user_row = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($user_row['id']); ?>">
                          <?php echo htmlspecialchars($user_row['username']); ?>
                        </option>
                      <?php endwhile; ?>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- No. HP -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_nohp">No. HP</label>
                  <input type="text" id="edit_nohp" name="nohp">
                </div>
              </div>

            <!-- Email -->
              <div class="col-md-6">
                <div class="form-group">
                  <label for="edit_email">Email</label>
                  <input type="email" id="edit_email" name="email">
                </div>
              </div>
            </div>

            <!-- Alamat -->
            <div class="form-group">
              <label for="edit_alamat">Alamat</label>
              <textarea id="edit_alamat" name="alamat" rows="3" class="form-control"></textarea>
            </div>

            <!-- Tombol Aksi -->
            <div class="form-group d-flex justify-content-end gap-2 mt-3">
              <button type="button" onclick="closeEditModal()" class="btn btn-danger">
                Batal
              </button>
              <button type="submit" name="edit_mahasiswa" class="btn btn-success">
                Simpan Perubahan
              </button>
            </div>
          </form>
        </div>
      </div>

      <script src="../../../js/script.js"></script>
      <script>
        // Modal functions
        function showAddModal() {
          document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
          document.getElementById('addModal').style.display = 'none';
        }

        function showEditModal(data) {
          // Populate form fields with data
          document.getElementById('edit_id').value = data.id;
          document.getElementById('edit_old_nim').value = data.nim;
          document.getElementById('edit_nim').value = data.nim;
          document.getElementById('edit_nama').value = data.nama;
          
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
          
          // Set selected option for user_id
          const userIdSelect = document.getElementById('edit_user_id');
          if (data.user_id) {
            // Add current user_id as an option if not already in the list
            let found = false;
            for (let i = 0; i < userIdSelect.options.length; i++) {
              if (userIdSelect.options[i].value == data.user_id) {
                userIdSelect.selectedIndex = i;
                found = true;
                break;
              }
            }
            // If user_id not found in options, add it temporarily
            if (!found && data.user_id) {
              const option = document.createElement('option');
              option.value = data.user_id;
              option.text = 'Current User (ID: ' + data.user_id + ')';
              option.selected = true;
              userIdSelect.add(option);
            }
          }
          
          document.getElementById('edit_nohp').value = data.nohp || '';
          document.getElementById('edit_email').value = data.email || '';
                    document.getElementById('edit_alamat').value = data.alamat || '';
          
          // Show modal
          document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
          document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
          if (event.target.className === 'modal') {
            event.target.style.display = 'none';
          }
        }
        
        // Hide alerts after some time
        setTimeout(function() {
          var alerts = document.getElementsByClassName('alert');
          for(var i = 0; i < alerts.length; i++) {
            alerts[i].style.display = 'none';
          }
        }, 5000);
      </script>
    </body>
</html>
<?php
// Close database connection
$conn->close();
?>