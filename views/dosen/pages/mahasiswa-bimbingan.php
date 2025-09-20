<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/config.php';

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
    header("Location: ../../../login.php");
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

// Mahasiswa Bimbingan
$mahasiswa_bimbingan_sql = "SELECT m.id, m.nim, m.nama, m.prodi, p.nama_prodi
                            FROM mahasiswa m
                            JOIN dosen_pembimbing dp ON m.id = dp.mahasiswa_id
                            JOIN program_studi p ON m.prodi = p.kode_prodi
                            WHERE dp.dosen_nip = ? AND dp.status = 'aktif'
                            ORDER BY m.nama ASC";
$mahasiswa_bimbingan_stmt = $conn->prepare($mahasiswa_bimbingan_sql);
if (!$mahasiswa_bimbingan_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$mahasiswa_bimbingan_stmt->bind_param("s", $dosen_data['nip']);
$mahasiswa_bimbingan_stmt->execute();
$mahasiswa_bimbingan = $mahasiswa_bimbingan_stmt->get_result();

// Proses persetujuan KRS
if (isset($_POST['approve_krs'])) {
    $mahasiswa_id = (int)$_POST['mahasiswa_id'];
    $status = $_POST['status'];
    $catatan = clean_input($_POST['catatan']);
    
    // Verifikasi bahwa mahasiswa adalah bimbingan dosen ini
    $check_sql = "SELECT 1 FROM dosen_pembimbing 
                  WHERE dosen_nip = ? AND mahasiswa_id = ? AND status = 'aktif'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $dosen_data['nip'], $mahasiswa_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        // Update semua KRS mahasiswa untuk semester ini
        $tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
        $semester = (date('n') >= 8) ? 'Ganjil' : 'Genap';
        
        $update_sql = "UPDATE krs 
                       SET status = ?, 
                           disetujui_oleh = ?,
                           catatan = ?,
                           tanggal_persetujuan = NOW()
                       WHERE mahasiswa_id = ? 
                       AND tahun_ajaran = ?
                       AND semester = ?
                       AND status = 'aktif'";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssss", $status, $dosen_data['nip'], $catatan, $mahasiswa_id, $tahun_ajaran, $semester);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "KRS berhasil " . ($status == 'disetujui' ? 'disetujui' : 'ditolak');
        } else {
            $_SESSION['error_message'] = "Gagal memproses persetujuan KRS";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Anda tidak memiliki akses untuk menyetujui KRS mahasiswa ini";
    }
    $check_stmt->close();
    
    header("Location: mahasiswa-bimbingan.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Mahasiswa Bimbingan - Portal Akademik</title>
    
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
            <a href="../dashboard.php" class="nav_link">
              <i class="bx bx-home"></i>
              <span class="nav_name">Dashboard</span>
            </a>

            <!-- Menu Dosen -->
            <a href="daftar-mahasiswa.php" class="nav_link">
              <i class="bx bxs-user-detail"></i>
              <span class="nav_name">Daftar Mahasiswa</span>
            </a>
            <a href="input-nilai.php" class="nav_link">
              <i class="bx bxs-book-content"></i>
              <span class="nav_name">Input Nilai</span>
            </a>
            <a href="jadwal-mengajar.php" class="nav_link">
              <i class="bx bxs-calendar"></i>
              <span class="nav_name">Jadwal Mengajar</span>
            </a>
            <a href="materi-perkuliahan.php" class="nav_link">
              <i class="bx bxs-book"></i>
              <span class="nav_name">Materi Kuliah</span>
            </a>
            <a href="mahasiswa-bimbingan.php" class="nav_link active">
              <i class="bx bxs-group"></i>
              <span class="nav_name">Mahasiswa Bimbingan</span>
            </a>
            <a href="profil-dosen.php" class="nav_link">
              <i class="bx bxs-user"></i>
                            <span class="nav_name">Profil Dosen</span>
            </a>
          </div>
        </div>
        <a href="../../../logout.php" class="nav_link">
          <i class="bx bx-log-out nav_icon"></i>
          <span class="nav_name">Logout</span>
        </a>
      </nav>
    </div>

    <!-- Container Main -->
        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <br>
          <div class="card-header">
            <h4>Daftar Mahasiswa Bimbingan</h4>
          </div>
          <div class="card-body">
            <?php if ($mahasiswa_bimbingan->num_rows > 0): ?>
              <div class="table-responsive">
                <table class="table-custom">
                  <thead class="table-dark">
                    <tr>
                      <th>No</th>
                      <th>NIM</th>
                      <th>Nama Mahasiswa</th>
                      <th>Program Studi</th>
                      <th>Status KRS</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    $no = 1;
                    while ($mhs = $mahasiswa_bimbingan->fetch_assoc()): 
                      // Cek status KRS mahasiswa
                      $krs_sql = "SELECT status, catatan FROM krs 
                                 WHERE mahasiswa_id = ? 
                                 AND tahun_ajaran = ?
                                 AND semester = ?
                                 AND status != 'aktif'
                                 LIMIT 1";
                      $tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
                      $semester = (date('n') >= 8) ? 'Ganjil' : 'Genap';
                      
                      $krs_stmt = $conn->prepare($krs_sql);
                      $krs_stmt->bind_param("iss", $mhs['id'], $tahun_ajaran, $semester);
                      $krs_stmt->execute();
                      $krs_result = $krs_stmt->get_result();
                      $krs_data = $krs_result->fetch_assoc();
                      $krs_stmt->close();
                    ?>
                      <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($mhs['nim']); ?></td>
                        <td><?= htmlspecialchars($mhs['nama']); ?></td>
                        <td><?= htmlspecialchars($mhs['nama_prodi']); ?></td>
                        <td>
                          <?php if ($krs_data): ?>
                            <span class="badge bg-<?= $krs_data['status'] == 'disetujui' ? 'success' : 'danger'; ?>">
                              <?= ucfirst($krs_data['status']); ?>
                            </span>
                            <?php if ($krs_data['catatan']): ?>
                              <br><small><?= htmlspecialchars($krs_data['catatan']); ?></small>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum Diproses</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#approveModal<?= $mhs['id']; ?>">
                            <i class="bx bx-check"></i> Proses KRS
                          </button>
                          <a href="detail-mahasiswa.php?id=<?= $mhs['id']; ?>" class="btn btn-sm btn-info">
                            <i class="bx bx-detail"></i> Detail
                          </a>
                        </td>
                      </tr>

                      <!-- Modal Approve KRS -->
                      <div class="modal fade" id="approveModal<?= $mhs['id']; ?>" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title" id="approveModalLabel">Persetujuan KRS</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                              <div class="modal-body">
                                <input type="hidden" name="mahasiswa_id" value="<?= $mhs['id']; ?>">
                                <div class="mb-3">
                                  <label class="form-label">Status Persetujuan</label>
                                  <select class="form-select" name="status" required>
                                    <option value="disetujui">Setujui</option>
                                    <option value="ditolak">Tolak</option>
                                  </select>
                                </div>
                                <div class="mb-3">
                                  <label class="form-label">Catatan (Opsional)</label>
                                  <textarea class="form-control" name="catatan" rows="3"></textarea>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="approve_krs" class="btn btn-primary">Simpan</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-info">Anda belum memiliki mahasiswa bimbingan.</div>
            <?php endif; ?>
          </div>

    <script src="../../../js/main.js"></script>
  </body>
</html>

<?php
// Tutup semua statement dan koneksi
$dosen_stmt->close();
$matkul_stmt->close();
$kelas_stmt->close();
$mahasiswa_stmt->close();
$jadwal_stmt->close();
$jadwal_lengkap_stmt->close();
$nilai_stmt->close();
$grade_stmt->close();
$mahasiswa_bimbingan_stmt->close();
$conn->close();
?>