<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once __DIR__.'/../../../includes/config.php';

// Verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if ($role !== 'mahasiswa') {
    header("Location: ../../login.php");
    exit();
}

// Get student data
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

// Input sanitization function
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Add new KRS
if (isset($_POST['tambah_krs'])) {
    $jadwal_id = intval($_POST['jadwal_id']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);
    $semester = clean_input($_POST['semester']);
    
    // Check if already exists
    $check_sql = "SELECT id FROM krs WHERE mahasiswa_id = ? AND jadwal_id = ? AND tahun_ajaran = ? AND semester = ?";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        $_SESSION['error_message'] = "Error database: " . $conn->error;
        header("Location: krs.php");
        exit();
    }
    
    $check_stmt->bind_param("iiss", $mahasiswa_data['id'], $jadwal_id, $tahun_ajaran, $semester);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Mata kuliah ini sudah ada dalam KRS Anda untuk periode yang sama!";
    } else {
        // Insert KRS data
        $sql = "INSERT INTO krs (mahasiswa_id, jadwal_id, tahun_ajaran, semester, status) 
                VALUES (?, ?, ?, ?, 'aktif')";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $_SESSION['error_message'] = "Error database: " . $conn->error;
            header("Location: krs.php");
            exit();
        }
        
        $stmt->bind_param("iiss", $mahasiswa_data['id'], $jadwal_id, $tahun_ajaran, $semester);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mata kuliah berhasil ditambahkan ke KRS!";
        } else {
            $_SESSION['error_message'] = "Gagal menambahkan mata kuliah ke KRS: " . $stmt->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
    header("Location: krs.php");
    exit();
}

// Delete KRS
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    $sql = "DELETE FROM krs WHERE id = ? AND mahasiswa_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['error_message'] = "Error database: " . $conn->error;
        header("Location: krs.php");
        exit();
    }
    
    $stmt->bind_param("ii", $id, $mahasiswa_data['id']);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Mata kuliah berhasil dihapus dari KRS!";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus mata kuliah dari KRS: " . $stmt->error;
    }
    $stmt->close();
    header("Location: krs.php");
    exit();
}

// Get KRS data
$krs_sql = "SELECT k.id, k.mahasiswa_id, k.jadwal_id, k.tahun_ajaran, k.semester, k.status,
                   mk.kelas, mk.matakuliah, mk.sks,
                   d.nama AS nama_dosen,
                   j.hari, j.waktu_mulai, j.waktu_selesai, j.ruangan
            FROM krs k
            JOIN jadwal_kuliah j ON k.jadwal_id = j.id
            JOIN matkul mk ON j.matkul_id = mk.id
            JOIN dosen d ON j.dosen_nip = d.nip
            WHERE k.mahasiswa_id = ?
            ORDER BY k.tahun_ajaran DESC, k.semester ASC, j.hari ASC, j.waktu_mulai ASC";
$krs_stmt = $conn->prepare($krs_sql);
$krs_stmt->bind_param("i", $mahasiswa_data['id']);
$krs_stmt->execute();
$krs_result = $krs_stmt->get_result();

// Get courses for dropdown
$jadwal_sql = "SELECT j.id, mk.kelas, mk.matakuliah, d.nama AS nama_dosen, j.hari, j.waktu_mulai, j.waktu_selesai, mk.sks 
               FROM jadwal_kuliah j
               JOIN matkul mk ON j.matkul_id = mk.id
               JOIN dosen d ON j.dosen_nip = d.nip
               ORDER BY j.hari, j.waktu_mulai";
$jadwal_result = $conn->query($jadwal_sql);

if (!$jadwal_result) {
    die("Error query jadwal: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KRS - Portal Akademik</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
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
            <a href="#" class="nav_link">
              <i class="bx bxs-user"></i>
              <span class="nav_name">Biodata</span>
            </a>
            <div class="accordion-item bg-transparent border-0">
              <a class="nav_link collapsed active" data-bs-toggle="collapse" href="#submenuAkademik" role="button" aria-expanded="false" aria-controls="submenuAkademik">
                <i class="bx bxs-graduation"></i>
                <span class="nav_name d-flex align-items-center w-100">Akademik <i class="bx bx-chevron-right arrow ms-auto"></i></span>
              </a>
              <div class="collapse nav_submenu ps-4" id="submenuAkademik">
                <a href="krs.php" class="nav_link active">• Kartu Rencana Studi</a>
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
            <div class="card-header">
                <h4 class="mb-0">Kartu Rencana Studi</h4>
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

                <form method="POST" action="">
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
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_data['nama_prodi']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. HP</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_data['nohp']); ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_data['email']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alamat</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_data['alamat']); ?>" readonly>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">Tambah Mata Kuliah</h5>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                            <select class="form-select" id="tahun_ajaran" name="tahun_ajaran" required>
                                <option value="2023/2024">2023/2024</option>
                                <option value="2024/2025" selected>2024/2025</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap" selected>Genap</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="jadwal_id" class="form-label">Mata Kuliah</label>
                            <select class="form-select" id="jadwal_id" name="jadwal_id" required>
                                <option value="">Pilih Mata Kuliah</option>
                                <?php while ($jadwal = $jadwal_result->fetch_assoc()): ?>
                                    <option value="<?php echo $jadwal['id']; ?>">
                                        <?php echo htmlspecialchars($jadwal['kelas'] . ' - ' . $jadwal['matakuliah'] . ' (' . $jadwal['hari'] . ' ' . $jadwal['waktu_mulai'] . '-' . $jadwal['waktu_selesai'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mt-3">
                            <button type="submit" name="tambah_krs" class="btn btn-primary w-100">Tambah Mata Kuliah</button>
                        </div>
                    </div>
                </form>

                <hr class="my-4">

                <h5 class="mb-3">Daftar Mata Kuliah</h5>

                <div class="table-data">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Kuliah</th>
                                <th>SKS</th>
                                <th>Dosen</th>
                                <th>Jadwal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($krs_result->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $krs_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['kelas'] . ' - ' . $row['matakuliah']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo $row['tahun_ajaran']; ?> - <?php echo $row['semester']; ?>
                                            </small>
                                        </td>
                                        <td><?php echo $row['sks']; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
                                        <td>
                                            <?php echo $row['hari']; ?><br>
                                            <?php echo date('H:i', strtotime($row['waktu_mulai'])); ?> - 
                                            <?php echo date('H:i', strtotime($row['waktu_selesai'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] == 'aktif'): ?>
                                                <span class="status-aktif">Aktif</span>
                                            <?php else: ?>
                                                <span class="status-batal">Batal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="krs.php?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus mata kuliah ini dari KRS?')">
                                                <i class="bx bxs-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bx bxs-calendar-times bxs-2x mb-3"></i>
                                            <p class="mb-0">Belum ada mata kuliah yang ditambahkan</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <!-- Print Button -->
                <div class="text-end mt-4">
                    <button class="btn btn-success" onclick="window.print()">
                        <i class="bx bxs-printer me-1"></i> Cetak KHS
                    </button>
                </div>
                </div>
            </div>
    </main>
    <script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
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
        
        // Hide alerts after 3 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 3000);
    });
</script>
    </script>
    
      <script src="../../../js/script.js"></script>
</body>
</html>

<?php
// Close database connections
$krs_stmt->close();
$mahasiswa_stmt->close();
$conn->close();
?>