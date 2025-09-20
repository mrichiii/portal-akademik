<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../includes/config.php';

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

// Add lecturer process
if (isset($_POST['tambah_dosen'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: kelola-dosen.php");
        exit();
    }

    $nip = clean_input($_POST['nip']);
    $nama = clean_input($_POST['nama']);
    $pangkat = clean_input($_POST['pangkat']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $prodi = clean_input($_POST['prodi']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nip) || empty($nama)) {
        $_SESSION['error_message'] = "NIP dan Nama harus diisi";
        header("Location: kelola-dosen.php");
        exit();
    }

    // Check if NIP already exists
    $check_sql = "SELECT nip FROM dosen WHERE nip = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $nip);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error_message'] = "NIP sudah terdaftar dalam sistem";
        $check_stmt->close();
        header("Location: kelola-dosen.php");
        exit();
    }
    $check_stmt->close();

    // Check if user_id already exists if provided
    if ($user_id !== null) {
        $check_user_sql = "SELECT user_id FROM dosen WHERE user_id = ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("i", $user_id);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "ID User sudah terhubung dengan dosen lain";
            $check_user_stmt->close();
            header("Location: kelola-dosen.php");
            exit();
        }
        $check_user_stmt->close();
        
        // Verify if user_id exists in users table
        $check_valid_user_sql = "SELECT id FROM users WHERE id = ?";
        $check_valid_user_stmt = $conn->prepare($check_valid_user_sql);
        $check_valid_user_stmt->bind_param("i", $user_id);
        $check_valid_user_stmt->execute();
        $check_valid_user_stmt->store_result();
        
        if ($check_valid_user_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "ID User tidak ditemukan";
            $check_valid_user_stmt->close();
            header("Location: kelola-dosen.php");
            exit();
        }
        $check_valid_user_stmt->close();
    }

    // Verify if prodi exists in program_studi table if provided
    if (!empty($prodi)) {
        $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
        $check_prodi_stmt = $conn->prepare($check_prodi_sql);
        $check_prodi_stmt->bind_param("s", $prodi);
        $check_prodi_stmt->execute();
        $check_prodi_stmt->store_result();
        
        if ($check_prodi_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "Program Studi tidak ditemukan";
            $check_prodi_stmt->close();
            header("Location: kelola-dosen.php");
            exit();
        }
        $check_prodi_stmt->close();
    }

    try {
        if ($user_id === null) {
            $sql = "INSERT INTO dosen (nip, nama, pangkat, alamat, nohp, email, prodi) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $nip, $nama, $pangkat, $alamat, $nohp, $email, $prodi);
        } else {
            $sql = "INSERT INTO dosen (nip, nama, pangkat, alamat, nohp, email, prodi, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $nip, $nama, $pangkat, $alamat, $nohp, $email, $prodi, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Dosen baru berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-dosen.php");
    exit();
}

// Edit lecturer process
if (isset($_POST['edit_dosen'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: kelola-dosen.php");
        exit();
    }

    $old_nip = clean_input($_POST['old_nip']);
    $nip = clean_input($_POST['nip']);
    $nama = clean_input($_POST['nama']);
    $pangkat = clean_input($_POST['pangkat']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $prodi = clean_input($_POST['prodi']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nip) || empty($nama)) {
        $_SESSION['error_message'] = "NIP dan Nama harus diisi";
        header("Location: kelola-dosen.php");
        exit();
    }

    // Check if NIP is being changed to an existing one
    if ($old_nip != $nip) {
        $check_sql = "SELECT nip FROM dosen WHERE nip = ? AND nip != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $nip, $old_nip);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "NIP sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: kelola-dosen.php");
            exit();
        }
        $check_stmt->close();
    }

    // Check if user_id is being changed to an existing one
    if ($user_id !== null) {
        $check_user_sql = "SELECT nip FROM dosen WHERE user_id = ? AND nip != ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("is", $user_id, $old_nip);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "ID User sudah terhubung dengan dosen lain";
            $check_user_stmt->close();
            header("Location: kelola-dosen.php");
            exit();
        }
        $check_user_stmt->close();
        
        // Verify if user_id exists in users table
        $check_valid_user_sql = "SELECT id FROM users WHERE id = ?";
        $check_valid_user_stmt = $conn->prepare($check_valid_user_sql);
        $check_valid_user_stmt->bind_param("i", $user_id);
        $check_valid_user_stmt->execute();
        $check_valid_user_stmt->store_result();
        
        if ($check_valid_user_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "ID User tidak ditemukan";
            $check_valid_user_stmt->close();
            header("Location: kelola-dosen.php");
            exit();
        }
        $check_valid_user_stmt->close();
    }

    // Verify if prodi exists in program_studi table if provided
    if (!empty($prodi)) {
        $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
        $check_prodi_stmt = $conn->prepare($check_prodi_sql);
        $check_prodi_stmt->bind_param("s", $prodi);
        $check_prodi_stmt->execute();
        $check_prodi_stmt->store_result();
        
        if ($check_prodi_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "Program Studi tidak ditemukan";
            $check_prodi_stmt->close();
            header("Location: kelola-dosen.php");
            exit();
        }
        $check_prodi_stmt->close();
    }

    try {
        $sql = "UPDATE dosen SET 
                nip = ?, 
                nama = ?, 
                pangkat = ?, 
                alamat = ?, 
                nohp = ?, 
                email = ?,
                prodi = ?,
                user_id = ?
                WHERE nip = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssis", $nip, $nama, $pangkat, $alamat, $nohp, $email, $prodi, $user_id, $old_nip);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data dosen berhasil diperbarui";
        } else {
            throw new Exception("Gagal memperbarui data dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-dosen.php");
    exit();
}

// Delete lecturer process
if (isset($_GET['delete_nip'])) {
    // Verify CSRF token
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: kelola-dosen.php");
        exit();
    }

    $nip = clean_input($_GET['delete_nip']);
    
    try {
        // Check if lecturer is assigned to any courses
        $check_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE dosen_nip = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $nip);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($count > 0) {
            throw new Exception("Dosen tidak dapat dihapus karena masih mengampu mata kuliah");
        }

        $sql = "DELETE FROM dosen WHERE nip = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nip);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Dosen berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: kelola-dosen.php");
    exit();
}

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Get users for dropdown (only users that aren't already linked)
$users_sql = "SELECT id, username FROM users 
              WHERE id NOT IN (SELECT user_id FROM dosen WHERE user_id IS NOT NULL) 
              ORDER BY username ASC";
$users_result = $conn->query($users_sql);

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " WHERE d.nip LIKE ? OR d.nama LIKE ? ";
    $search_param = "%$search%";
}

// Get lecturer data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records
if (empty($search)) {
    $count_sql = "SELECT COUNT(*) FROM dosen";
    $total_rows = $conn->query($count_sql)->fetch_row()[0];
} else {
    $count_sql = "SELECT COUNT(*) FROM dosen WHERE nip LIKE ? OR nama LIKE ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ss", $search_param, $search_param);
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
}

$total_pages = ceil($total_rows / $per_page);

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');

// Store session messages and clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get paginated data with program studi information
if (empty($search)) {
    $sql = "SELECT d.*, ps.nama_prodi 
            FROM dosen d 
            LEFT JOIN program_studi ps ON d.prodi = ps.kode_prodi 
            ORDER BY d.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offset, $per_page);
} else {
    $sql = "SELECT d.*, ps.nama_prodi 
            FROM dosen d 
            LEFT JOIN program_studi ps ON d.prodi = ps.kode_prodi 
            WHERE d.nip LIKE ? OR d.nama LIKE ?
            ORDER BY d.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $search_param, $search_param, $offset, $per_page);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Kelola Dosen</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
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
                  <a href="kelola-dosen.php" class="nav_link active"
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

        <div class="table-responsive">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1>Kelola Dosen</h1>
          <button class="btn btn-success" onclick="showAddModal()" style="background-color: #000000;">
            <i class="bx bx-plus"></i> Tambah Dosen
          </button>
        </div>
        
        <div class="search-body">
          <form method="GET" class="row g-3">
            <div class="col-md-12">
              <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Cari NIP atau nama dosen..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit">
                  <i class="bx bx-search me-1"></i> Cari
                </button>
              </div>
            </div>
            <?php if (!empty($search)): ?>
            <div class="col-auto">
              <a href="kelola-dosen.php" class="btn btn-success">
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
                  <th>NIP</th>
                  <th>Nama Dosen</th>
                  <th>Program Studi</th>
                  <th>Pangkat</th>
                  <th>No. HP</th>
                  <th>Email</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $no = $offset + 1; while($row = $result->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($row['nip']); ?></td>
                      <td><?php echo htmlspecialchars($row['nama']); ?></td>
                      <td><?php echo htmlspecialchars($row['nama_prodi'] ?: '-'); ?></td>
                      <td><?php echo htmlspecialchars($row['pangkat'] ?: '-'); ?></td>
                      <td><?php echo htmlspecialchars($row['nohp'] ?: '-'); ?></td>
                      <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                      <td class="action-buttons">
                        <a href="#" class="edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                          <i class="bx bxs-edit"></i>
                        </a>
                        <a href="?delete_nip=<?php echo $row['nip']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus dosen \'<?php echo htmlspecialchars($row['nama']); ?>\'?');">
                          <i class="bx bxs-trash"></i>
                        </a>
                      </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                      <td colspan="9" class="text-center">Tidak ada data dosen</td>
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
    </main>

    <!-- Modal Tambah Dosen -->
    <div id="addModal" class="modal">
      <div class="modal-content">
        <h2>Tambah Dosen Baru</h2>
        
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          
          <!-- NIP -->
          <div class="form-group">
            <label for="nip">NIP</label>
            <input type="text" id="nip" name="nip" required>
          </div>

          <!-- Nama -->
          <div class="form-group">
            <label for="nama">Nama Dosen</label>
            <input type="text" id="nama" name="nama" required>
          </div>
          

          <!-- Pangkat -->
          <div class="form-group">
            <label for="pangkat">Pangkat</label>
            <select id="pangkat" name="pangkat" required>
              <option value="">-- Pilih Pangkat --</option>
              <option value="Dosen Tetap">Dosen Tetap</option>
              <option value="Dosen Tidak Tetap">Dosen Tidak Tetap</option>
            </select>
          </div>

          <!-- Program Studi -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="prodi">Program Studi</label>
                <select id="prodi" name="prodi" class="form-select">
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
            <button type="submit" name="tambah_dosen" class="btn btn-success">
              Tambah Dosen
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Edit Dosen -->
    <div id="editModal" class="modal">
      <div class="modal-content">
        <h2>Edit Data Dosen</h2>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" id="edit_old_nip" name="old_nip">
          
          <!-- NIP -->
          <div class="form-group">
            <label for="edit_nip">NIP</label>
            <input type="text" id="edit_nip" name="nip" required>
          </div>

          <!-- Nama -->
          <div class="form-group">
            <label for="edit_nama">Nama Dosen</label>
            <input type="text" id="edit_nama" name="nama" required>
          </div>

          <!-- Pangkat -->
          <div class="form-group">
            <label for="edit_pangkat">Pangkat</label>
            <select id="edit_pangkat" name="pangkat" required>
              <option value="">-- Pilih Pangkat --</option>
              <option value="Dosen Tetap">Dosen Tetap</option>
              <option value="Dosen Tidak Tetap">Dosen Tidak Tetap</option>
            </select>
          </div>

          <!-- Program Studi -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="edit_prodi">Program Studi</label>
                <select id="edit_prodi" name="prodi" class="form-select">
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
            <button type="submit" name="edit_dosen" class="btn btn-success">
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
        document.getElementById('edit_old_nip').value = data.nip;
        document.getElementById('edit_nip').value = data.nip;
        document.getElementById('edit_nama').value = data.nama;
        document.getElementById('edit_pangkat').value = data.pangkat || '';
        
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
          // Add current user_id as an option
                    for (let i = 0; i < userIdSelect.options.length; i++) {
            if (userIdSelect.options[i].value == data.user_id) {
              userIdSelect.selectedIndex = i;
              break;
            }
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
        if (event.target.classList.contains('modal')) {
          event.target.style.display = 'none';
        }
      }
      
      // Hide alerts after some time
      setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
          alert.style.display = 'none';
        });
      }, 5000);
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>