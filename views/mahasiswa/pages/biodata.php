<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__.'/../../../includes/config.php';

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

// Get logged in student data
$user_id = $_SESSION['user_id'];
$mahasiswa_sql = "SELECT m.*, ps.nama_prodi, u.username 
              FROM mahasiswa m 
              LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
              LEFT JOIN users u ON m.user_id = u.id
              WHERE m.user_id = ?";
$stmt = $conn->prepare($mahasiswa_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mahasiswa_result = $stmt->get_result();

if ($mahasiswa_result->num_rows === 0) {
    $_SESSION['error_message'] = "Data mahasiswa tidak ditemukan untuk akun ini.";
    header("Location: dashboard.php");
    exit();
}

$mahasiswa = $mahasiswa_result->fetch_assoc();

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    // Validate and sanitize input
    $nama = clean_input($_POST['nama']);
    $nim = clean_input($_POST['nim']);
    $prodi = $_POST['prodi'] ? clean_input($_POST['prodi']) : NULL;
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $alamat = clean_input($_POST['alamat']);
    $semester_aktif = intval(clean_input($_POST['semester_aktif']));
    
    // Validate inputs
    if (empty($nama) || empty($nim) || empty($semester_aktif)) {
        $_SESSION['error_message'] = "Nama, NIM, dan semester aktif wajib diisi";
        header("Location: biodata.php");
        exit();
    }

    if ($semester_aktif < 1 || $semester_aktif > 14) {
        $_SESSION['error_message'] = "Semester aktif harus antara 1 sampai 14";
        header("Location: biodata.php");
        exit();
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Format email tidak valid";
        header("Location: biodata.php");
        exit();
    }

    // Initialize foto_profil with existing value
    $foto_profil = $mahasiswa['foto_profil'];
    
    // Handle file upload
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__.'/../../../uploads/profil_mahasiswa/';
        
        // Create directory if not exists
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($fileInfo, $_FILES['foto_profil']['tmp_name']);
        finfo_close($fileInfo);
        
        if (in_array($fileType, $allowedTypes)) {
            // Generate unique filename
            $ext = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
            $filename = 'mahasiswa_' . $mahasiswa['nim'] . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $targetPath)) {
                // Delete old photo if exists
                if (!empty($mahasiswa['foto_profil']) && file_exists(__DIR__.'/../../../' . $mahasiswa['foto_profil'])) {
                    unlink(__DIR__.'/../../../' . $mahasiswa['foto_profil']);
                }
                
                $foto_profil = 'uploads/profil_mahasiswa/' . $filename;
            }
        } else {
            $_SESSION['error_message'] = "Format file tidak didukung. Hanya JPEG, PNG, atau GIF yang diperbolehkan";
            header("Location: biodata.php");
            exit();
        }
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Update mahasiswa table
        $update_mahasiswa_sql = "UPDATE mahasiswa SET 
                            nim = ?, 
                            nama = ?, 
                            prodi = ?, 
                            alamat = ?, 
                            nohp = ?, 
                            email = ?, 
                            semester_aktif = ?,
                            foto_profil = ?
                           WHERE user_id = ?";
        
        $stmt = $conn->prepare($update_mahasiswa_sql);
        $stmt->bind_param("ssssssiss", 
            $nim, 
            $nama, 
            $prodi, 
            $alamat, 
            $nohp, 
            $email,
            $semester_aktif,
            $foto_profil,
            $user_id);
        
        // Also update users table for consistency
        $update_user_sql = "UPDATE users SET nama = ? WHERE id = ?";
        $stmt_user = $conn->prepare($update_user_sql);
        $stmt_user->bind_param("si", $nama, $user_id);
        
        // Execute both updates
        $stmt->execute();
        $stmt_user->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Profil berhasil diperbarui!";
        // Update session nama if changed
        $_SESSION['nama'] = $nama;
        // Refresh data
        header("Location: biodata.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Gagal memperbarui profil: " . $e->getMessage();
        header("Location: biodata.php");
        exit();
    }
}

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = clean_input($_POST['current_password']);
    $new_password = clean_input($_POST['new_password']);
    $confirm_password = clean_input($_POST['confirm_password']);
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error_message'] = "Semua field password harus diisi";
        header("Location: biodata.php");
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = "Password baru dan konfirmasi password tidak cocok";
        header("Location: biodata.php");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        $_SESSION['error_message'] = "Password baru harus minimal 8 karakter";
        header("Location: biodata.php");
        exit();
    }
    
    // Verify current password
    $check_sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($current_password !== $user['password']) {
        $_SESSION['error_message'] = "Password saat ini salah";
        header("Location: biodata.php");
        exit();
    }
    
    // Update password
    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $new_password, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Password berhasil diubah!";
        header("Location: biodata.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Gagal mengubah password: " . $conn->error;
        header("Location: biodata.php");
        exit();
    }
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/table.css">
    <link rel="website icon" type="png" href="../../../img/logouin.png">
    <title>Biodata Mahasiswa</title>
    <style>
        .profile-picture {
            width: 150px;
            height: 200px;
            object-fit: cover;
            border-radius: none;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-header {
            background-color: #000000;
            color: white;
            padding: 2rem;
            border: 2px solid #00cc6666;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body id="body-pd">
    <header class="header" id="header">
        <div class="header_toggle"> <i class='bx bx-menu' id="header-toggle"></i> </div>
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
            <a href="../dashboard.php" class="nav_link">
              <i class="bx bx-home"></i>
              <span class="nav_name">Dashboard</span>
            </a>
            <a href="#" class="nav_link active">
              <i class="bx bxs-user"></i>
              <span class="nav_name">Biodata</span>
            </a>
            <div class="accordion-item bg-transparent border-0">
              <a class="nav_link collapsed" data-bs-toggle="collapse" href="#submenuAkademik" role="button" aria-expanded="false" aria-controls="submenuAkademik">
                <i class="bx bxs-graduation"></i>
                <span class="nav_name d-flex align-items-center w-100">Akademik <i class="bx bx-chevron-right arrow ms-auto"></i></span>
              </a>
              <div class="collapse nav_submenu ps-4" id="submenuAkademik">
                <a href="krs.php" class="nav_link">• Kartu Rencana Studi</a>
                <a href="khs.php" class="nav_link">• Kartu Hasil Studi</a>
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
            <div id="todo">
                <div class="profile-header d-flex align-items-center mb-4">
                <div class="me-4">
                    <img src="<?php echo !empty($mahasiswa['foto_profil']) ? '../../../' . htmlspecialchars($mahasiswa['foto_profil']) : '../../../images/default-profile.jpg'; ?>" 
                         alt="Profile Picture" class="profile-picture">
                </div>
                <div>
                    <h1 class="mb-1"><?php echo htmlspecialchars($mahasiswa['nama']); ?></h1>
                    <p class="mb-1"><i class="bx bxs-id-badge me-2"></i><?php echo htmlspecialchars($mahasiswa['nim']); ?></p>
                    <p class="mb-1"><i class="bx bxs-graduation-cap me-2"></i><?php echo htmlspecialchars($mahasiswa['nama_prodi'] ?? 'Belum diatur'); ?></p>
                    <p class="mb-1"><i class="bx bxs-layer-group me-2"></i>Semester <?php echo htmlspecialchars($mahasiswa['semester_aktif']); ?></p>
                </div>
                </div>

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

                <!-- Biodata Form -->
                <div class="card">
                <div class="card-header" style="background-color:#ffffff1a; color: white;">
                    <h5 class="mb-0">
            <i class="bx bxs-edit me-2"></i>Edit Profil
        </h5>
    </div>
    <div class="card-body">
        <form id="biodataForm" method="post" action="biodata.php" enctype="multipart/form-data">
            <!-- Data Pribadi -->
            <div class="mb-4">
                <h4 class="ps-2 py-2" style="background-color: #00cc661a;">Data Pribadi</h4>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nim" class="form-label">NIM</label>
                        <input type="text" class="form-control" id="nim" name="nim" value="<?= htmlspecialchars($mahasiswa['nim']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($mahasiswa['nama']) ?>" required>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="foto_profil" class="form-label">Foto Profil</label>
                        <input type="file" class="form-control" id="foto_profil" name="foto_profil" accept="image/*">
                    </div>
                </div>
            </div>

            <!-- Data Akademik -->
            <div class="mb-4">
                <h4 class="ps-2 py-2" style="background-color: #00cc661a;">Data Akademik</h4>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="prodi" class="form-label">Program Studi</label>
                        <select class="form-select" id="prodi" name="prodi" required>
                            <option value="">Pilih Program Studi</option>
                            <?php $prodi_result->data_seek(0); // Reset pointer ?>
                            <?php while($prodi = $prodi_result->fetch_assoc()): ?>
                                <option value="<?= $prodi['kode_prodi'] ?>" <?= ($mahasiswa['prodi'] == $prodi['kode_prodi']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prodi['nama_prodi']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="semester_aktif" class="form-label">Semester Aktif</label>
                        <select class="form-select" id="semester_aktif" name="semester_aktif">
                            <?php for ($i = 1; $i <= 14; $i++): ?>
                                <option value="<?= $i ?>" <?= ($mahasiswa['semester_aktif'] == $i) ? 'selected' : '' ?>>
                                    Semester <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Kontak -->
            <div class="mb-4">
                <h4 class="ps-2 py-2" style="background-color: #00cc661a;">Kontak</h4>
                
                <div class="row g-3">
                    <div class="col-md-12">
                        <label for="alamat" class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars($mahasiswa['alamat']) ?></textarea>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="nohp" class="form-label">Nomor Telepon</label>
                        <input type="text" class="form-control" id="nohp" name="nohp" value="<?= htmlspecialchars($mahasiswa['nohp']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($mahasiswa['email']) ?>">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-4 d-flex justify-content-between">
                <a href="../dashboard.php" class="btn" style="background-color: #f8f9fa; color: #00cc66; border: 1px solid #00cc66;">
                    <i class="bx bxs-arrow-left me-2"></i>Kembali
                </a>
                <button type="submit" name="update_profil" class="btn btn-success">
                    <i class="bx bxs-save me-2"></i>Simpan Perubahan
                </button>
            </div>
            </form>
        </div>
    </div>

    <!-- Account Security Card -->
    <div class="card col-lg-12 mb-4" style="margin-top: 1.5rem;">
        <div class="card-header" style="background-color:#ffffff1a; color: white;">
            <h5 class="mb-0">
                <i class="bx bx-shield-quarter me-2"></i>Keamanan Akun
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($mahasiswa['nim']) ?>" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="Mahasiswa" readonly>
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Ubah Password</h6>
                            <p class="small text-muted mb-0">Gunakan password yang kuat dan unik</p>
                        </div>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#passwordModal">
                            <i class="bx bxs-key me-2"></i>Ubah Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <!-- Password Change Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordModalLabel">Ubah Password</h5>
                </div>
                <form method="post" action="biodata.php">
                    <div class="modal-body">
                        <div class="mb-3 password-input-group">
                            <label for="current_password" class="form-label">Password Saat Ini</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <i class="" onclick="togglePassword('current_password', this)"></i>
                        </div>
                        <div class="mb-3 password-input-group">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <i class="" onclick="togglePassword('new_password', this)"></i>
                        </div>
                        <div class="mb-3 password-input-group">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <i class="" onclick="togglePassword('confirm_password', this)"></i>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="change_password" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar
        document.addEventListener("DOMContentLoaded", function(event) {
            const showNavbar = (toggleId, navId, bodyId, headerId) => {
                const toggle = document.getElementById(toggleId),
                nav = document.getElementById(navId),
                bodypd = document.getElementById(bodyId),
                headerpd = document.getElementById(headerId);
                
                if(toggle && nav && bodypd && headerpd) {
                    toggle.addEventListener('click', () => {
                        nav.classList.toggle('show');
                        toggle.classList.toggle('bx-x');
                        bodypd.classList.toggle('body-pd');
                        headerpd.classList.toggle('body-pd');
                    });
                }
            }
            
            showNavbar('header-toggle','nav-bar','body-pd','header');
            
            // Add active class to the current nav item
            const linkColor = document.querySelectorAll('.nav_link');
            
            function colorLink() {
                if(linkColor) {
                    linkColor.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            }
            linkColor.forEach(l => l.addEventListener('click', colorLink));
        });
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
$conn->close();
?>