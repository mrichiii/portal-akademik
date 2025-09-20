<?php
session_start();
include '../../../includes/config.php';

// Fungsi untuk membersihkan input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Proses tambah user (baik mahasiswa maupun dosen)
if (isset($_POST['tambah_user'])) {
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);
    $nama = clean_input($_POST['nama']);
    $role = clean_input($_POST['role']); // Ambil role dari form
    
    // Validasi role
    if (!in_array($role, ['mahasiswa', 'dosen'])) {
        $error_message = "Role tidak valid";
    } else {
        // Jika role mahasiswa, ambil juga prodi
        $prodi = ($role == 'mahasiswa') ? clean_input($_POST['prodi']) : NULL;

        if ($role == 'mahasiswa') {
            $sql = "INSERT INTO users (username, password, role, nama, prodi) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $username, $password, $role, $nama, $prodi);
        } else {
            $sql = "INSERT INTO users (username, password, role, nama) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $username, $password, $role, $nama);
        }

        if ($stmt->execute()) {
            $success_message = ucfirst($role) . " baru berhasil ditambahkan";
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Proses edit user
if (isset($_POST['edit_user'])) {
    $id = clean_input($_POST['id']);
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);
    $nama = clean_input($_POST['nama']);
    $role = clean_input($_POST['role']);
    
    // Validasi role
    if (!in_array($role, ['mahasiswa', 'dosen'])) {
        $error_message = "Role tidak valid";
    } else {
        // Jika role mahasiswa, ambil juga prodi
        $prodi = ($role == 'mahasiswa') ? clean_input($_POST['prodi']) : NULL;

        // Hash password jika diubah
        $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

        if ($role == 'mahasiswa') {
            if ($hashed_password) {
                $sql = "UPDATE users SET 
                        username = ?, 
                        password = ?, 
                        nama = ?,
                        role = ?,
                        prodi = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $username, $hashed_password, $nama, $role, $prodi, $id);
            } else {
                $sql = "UPDATE users SET 
                        username = ?, 
                        nama = ?,
                        role = ?,
                        prodi = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $username, $nama, $role, $prodi, $id);
            }
        } else {
            if ($hashed_password) {
                $sql = "UPDATE users SET 
                        username = ?, 
                        password = ?, 
                        nama = ?,
                        role = ?,
                        prodi = NULL 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $username, $hashed_password, $nama, $role, $id);
            } else {
                $sql = "UPDATE users SET 
                        username = ?, 
                        nama = ?,
                        role = ?,
                        prodi = NULL 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $username, $nama, $role, $id);
            }
        }

        if ($stmt->execute()) {
            $success_message = "Data " . ucfirst($role) . " berhasil diperbarui";
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Proses hapus user
if (isset($_GET['delete_id'])) {
    $id = clean_input($_GET['delete_id']);
    
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success_message = "User berhasil dihapus";
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Ambil semua data user dengan role mahasiswa
$sql_mahasiswa = "SELECT * FROM users WHERE role = 'mahasiswa'";
$result_mahasiswa = $conn->query($sql_mahasiswa);

// Ambil semua data user dengan role dosen
$sql_dosen = "SELECT * FROM users WHERE role = 'dosen'";
$result_dosen = $conn->query($sql_dosen);


// Ambil data prodi untuk dropdown
$prodi_sql = "SELECT * FROM program_studi";
$prodi_result = $conn->query($prodi_sql);
$prodi_options = [];
while ($row = $prodi_result->fetch_assoc()) {
    $prodi_options[$row['kode_prodi']] = $row['nama_prodi'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Kelola Mahasiswa/I</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
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
                  <a href="#" class="nav_link">• Manajemen User</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1>Kelola Pengguna</h1>
          <button class="btn btn-primary" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Tambah Pengguna
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

        <!-- Tabel Dosen -->
        <div class="card mb-5 text-white" style="background-color: #ffffff1a;">
          <h3 class="mb-0 ">Daftar Dosen</h3>
          <div class="card-body">
            <div class="table-responsive">
              <div class="table-data">
                <div class="users">
                  <div class="head">
                  </div>
                  <table class="table-custom">
                    <thead class="table-dark">
                      <tr>
                        <th>No</th>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      if ($no_dosen = 1) {
                          while($row = $result_dosen->fetch_assoc()) { 
                      ?>
                      <tr>
                        <td><?php echo $no_dosen++; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['nama'] ? $row['nama'] : '-'; ?></td>
                        <td><?php echo ucfirst($row['role']); ?></td>
                        <td class="action-buttons">
                          <a href="#" class="edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                            <i class="bx bxs-edit"></i>
                          </a>
                          <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus dosen ini?');">
                            <i class="bx bxs-trash"></i>
                          </a>
                        </td>
                      </tr>
                      <?php 
                          }
                      } else {
                      ?>
                      <tr>
                        <td colspan="5" style="text-align: center;">Tidak ada data Dosen</td>
                      </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabel Mahasiswa -->
        <div class="card mb-5 text-white" style="background-color: #ffffff1a;">
          <h3 class="mb-0 ">Daftar Mahasiswa</h3>
          <div class="card-body">
            <div class="table-responsive">
              <div class="table-data">
                <div class="users">
                  <div class="head">
                  </div>
                  <table class="table-custom">
                    <thead class="table-dark">
                      <tr>
                        <th>No</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Program Studi</th>
                        <th>Jabatan</th>
                        <th>Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      if ($no_mahasiswa = 1) {
                          while($row = $result_mahasiswa->fetch_assoc()) { 
                      ?>
                      <tr>
                        <td><?php echo $no_mahasiswa++; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['nama'] ? $row['nama'] : '-'; ?></td>
                        <td><?php echo $row['prodi'] ? $row['prodi'] : '-'; ?></td>
                        <td><?php echo ucfirst($row['role']); ?></td>
                        <td class="action-buttons">
                          <a href="#" class="edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                            <i class="bx bxs-edit"></i>
                          </a>
                          <a href="?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Apakah Anda yakin ingin menghapus mahasiswa ini?');">
                            <i class="bx bxs-trash"></i>
                          </a>
                        </td>
                      </tr>
                      <?php 
                          }
                      } else {
                      ?>
                      <tr>
                        <td colspan="6" style="text-align: center;">Tidak ada data Mahasiswa</td>
                      </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
    </main>

    <!-- Modal Tambah Mahasiswa -->
    <div id="addModal" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Tambah Mahasiswa Baru</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="">
            <div class="modal-body">
              <div class="mb-3">
                <label for="username" class="form-label">NIM</label>
                <input type="text" class="form-control" id="username" name="username" required>
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>
              <div class="mb-3">
                <label for="nama" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama" name="nama" required>
              </div>
              <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                  <option value="mahasiswa" selected>Mahasiswa</option>
                  <option value="dosen">Dosen</option>
                </select>
              </div>
              <div class="mb-3" id="prodiField">
                <label for="prodi" class="form-label">Program Studi</label>
                <select class="form-select" id="prodi" name="prodi" required>
                  <option value="">-- Pilih Program Studi --</option>
                  <?php foreach ($prodi_options as $kode => $nama): ?>
                    <option value="<?php echo htmlspecialchars($kode); ?>"><?php echo htmlspecialchars($nama); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="tambah_user" class="btn btn-primary">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Modal Edit Mahasiswa -->
    <div id="editModal" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Data Mahasiswa</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="">
            <input type="hidden" id="edit_id" name="id">
            <div class="modal-body">
              <div class="mb-3">
                <label for="edit_username" class="form-label">NIM</label>
                <input type="text" class="form-control" id="edit_username" name="username" required>
              </div>
              <div class="mb-3">
                <label for="edit_password" class="form-label">Password (Kosongkan jika tidak diubah)</label>
                <input type="password" class="form-control" id="edit_password" name="password">
              </div>
              <div class="mb-3">
                <label for="edit_nama" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="edit_nama" name="nama" required>
              </div>
              <div class="mb-3">
                <label for="edit_role" class="form-label">Role</label>
                <select class="form-select" id="edit_role" name="role" required>
                  <option value="mahasiswa">Mahasiswa</option>
                  <option value="dosen">Dosen</option>
                </select>
              </div>
              <div class="mb-3" id="editProdiField">
                <label for="edit_prodi" class="form-label">Program Studi</label>
                <select class="form-select" id="edit_prodi" name="prodi">
                  <option value="">-- Pilih Program Studi --</option>
                  <?php foreach ($prodi_options as $kode => $nama): ?>
                    <option value="<?php echo htmlspecialchars($kode); ?>"><?php echo htmlspecialchars($nama); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="edit_user" class="btn btn-success">Perbarui</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="../../../js/script.js"></script>
    <script>
      // Fungsi untuk menampilkan modal tambah menggunakan Bootstrap
      function showAddModal() {
        var addModal = new bootstrap.Modal(document.getElementById('addModal'));
        addModal.show();
        
        // Set role default ke mahasiswa
        document.getElementById('role').value = 'mahasiswa';
        toggleProdiField('mahasiswa');
        
        // Event listener untuk perubahan role
        document.getElementById('role').addEventListener('change', function() {
          toggleProdiField(this.value);
        });
      }
      
      // Fungsi untuk menampilkan modal edit menggunakan Bootstrap
      function showEditModal(user) {
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
        
        document.getElementById('edit_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_nama').value = user.nama || '';
        document.getElementById('edit_role').value = user.role || 'mahasiswa';
        document.getElementById('edit_prodi').value = user.prodi || '';
        
        // Tampilkan field prodi berdasarkan role
        toggleEditProdiField(user.role || 'mahasiswa');
        
        // Event listener untuk perubahan role
        document.getElementById('edit_role').addEventListener('change', function() {
          toggleEditProdiField(this.value);
        });
      }
      
      // Fungsi untuk menampilkan/menyembunyikan field prodi berdasarkan role
      function toggleProdiField(role) {
        const prodiField = document.getElementById('prodiField');
        const prodiSelect = document.getElementById('prodi');
        
        if (role === 'mahasiswa') {
          prodiField.style.display = 'block';
          prodiSelect.required = true;
        } else {
          prodiField.style.display = 'none';
          prodiSelect.required = false;
          prodiSelect.value = '';
        }
      }
      
      function toggleEditProdiField(role) {
        const prodiField = document.getElementById('editProdiField');
        const prodiSelect = document.getElementById('edit_prodi');
        
        if (role === 'mahasiswa') {
          prodiField.style.display = 'block';
          prodiSelect.required = true;
        } else {
          prodiField.style.display = 'none';
          prodiSelect.required = false;
          prodiSelect.value = '';
        }
      }
    </script>
</body>
</html>

<?php
// Tutup koneksi database
$conn->close();
?>