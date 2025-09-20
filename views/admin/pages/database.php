<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../../../login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? '';

// Only allow admin to access this page
if ($role !== 'admin') {
    header("Location: ../../../unauthorized.php");
    exit();
}

include_once '../../../includes/config.php';

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');

// Function to get all table names from the database
function getDatabaseTables($conn) {
    $tables = [];
    $query = "SHOW TABLES";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
    }
    return $tables;
}

// Function to get table structure
function getTableStructure($conn, $tableName) {
    $structure = [];
    $query = "DESCRIBE $tableName";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $structure[] = $row;
        }
    }
    return $structure;
}

// Function to get table data with pagination
function getTableData($conn, $tableName, $page = 1, $perPage = 10) {
    $offset = ($page - 1) * $perPage;
    $query = "SELECT * FROM $tableName LIMIT $offset, $perPage";
    $result = $conn->query($query);
    
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM $tableName";
    $countResult = $conn->query($countQuery);
    $total = $countResult ? $countResult->fetch_assoc()['total'] : 0;
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage
    ];
}

// Get current table from URL or default to first table
$tables = getDatabaseTables($conn);
$currentTable = $_GET['table'] ?? ($tables[0] ?? '');
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['perPage']) ? max(5, intval($_GET['perPage'])) : 10;

// Get table data if a table is selected
$tableData = [];
$tableStructure = [];
$pagination = [];
if ($currentTable && in_array($currentTable, $tables)) {
    $tableStructure = getTableStructure($conn, $currentTable);
    $tableDataResult = getTableData($conn, $currentTable, $page, $perPage);
    $tableData = $tableDataResult['data'];
    $pagination = [
        'total' => $tableDataResult['total'],
        'page' => $tableDataResult['page'],
        'perPage' => $tableDataResult['perPage'],
        'totalPages' => ceil($tableDataResult['total'] / $tableDataResult['perPage'])
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../../css/style.css" />
    <link rel="stylesheet" href="../../../css/table.css" />
    <link rel="website icon" type="png" href="../../../img/logouin.png" />
    <title>Database - Admin Portal</title>
    <style>
        .database-sidebar {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: #000000;
            color: #ffffff;
            text-align: left;
            margin-bottom: 2rem;
            border-radius: none;
            background-clip: border-box;
            border: 2px solid #00cc6666;
            overflow: hidden; /* opsional, bantu buang overflow dari isi tabel */
        }
        
        .database-sidebar .list-group-item {
            background-color: transparent;
            border-color: #444;
            color: #ddd;
        }
        
        .database-sidebar .list-group-item.active {
            background-color: #00cc6666;
            border-color: #00cc6666;
        }
        
        .database-sidebar .list-group-item:hover:not(.active) {
            background-color: #333;
        }
        
        .empty-table {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            color: #6c757d;
        }
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
                                <a href="database.php" class="nav_link active">• Database</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; justify-content: center">
                <a href="../../../logout.php" class="nav_link"> <i class='bx bx-log-out nav_icon'></i> <span class="nav_name">LogOut</span> </a>
            </div>
        </nav>
    </div>
    
    <main>
        <!-- Konten Utama -->
        <div>
            <h2>Database Management</h2>
        </div>
        <div id="todo">
                <!-- Sidebar with table list -->
                <div class="card-body">
                    <div class="database-sidebar">
                        <h5 class="mb-3 text-white">Database Tables</h5>
                        <div class="list-group">
                            <?php foreach ($tables as $table): ?>
                                <a href="?table=<?= urlencode($table) ?>" 
                                   class="list-group-item list-group-item-action <?= ($table === $currentTable) ? 'active' : '' ?>">
                                    <i class="bx bx-table me-2"></i><?= htmlspecialchars($table) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Main content area -->
                <div class="card">
                    <?php if ($currentTable): ?>
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="mb-0 text-white">Table: <?= htmlspecialchars($currentTable) ?></h3>
                                <span class="badge bg-success"><?= $pagination['total'] ?? 0 ?> records</span>
                            </div>
                            <div class="card-body">
                                
                                <div class="tab-content" id="tableTabsContent">
                                    <!-- Data Tab -->
                                    <div class="" id="data" role="tabpanel" aria-labelledby="data-tab">
                                        <div class="table-responsive">
                                            <div class="table-data">
                                                <?php if (!empty($tableData)): ?>
                                                    <table class="table-custom">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <?php foreach ($tableStructure as $column): ?>
                                                                    <th><?= htmlspecialchars($column['Field']) ?></th>
                                                                <?php endforeach; ?>
                                                                <th>Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tableData as $row): ?>
                                                                <tr>
                                                                    <?php foreach ($tableStructure as $column): 
                                                                        $field = $column['Field'];
                                                                        $value = $row[$field] ?? null;
                                                                    ?>
                                                                        <td>
                                                                            <?php if (is_null($value)): ?>
                                                                                <span class="text-muted">NULL</span>
                                                                            <?php elseif ($value === ''): ?>
                                                                                <span class="text-muted">(empty)</span>
                                                                            <?php else: ?>
                                                                                <?= htmlspecialchars(substr(strval($value), 0, 100)) ?>
                                                                                <?php if (strlen(strval($value)) > 100): ?>
                                                                                    <span class="text-muted">...</span>
                                                                                <?php endif; ?>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    <?php endforeach; ?>
                                                                    <td class="action-buttons">
                                                                        <a href="#" class="edit">
                                                                            <i class="bx bxs-edit"></i>
                                                                        </a>
                                                                        <a href="#" class="delete">
                                                                            <i class="bx bxs-trash"></i>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                <?php else: ?>
                                                    <div class="empty-table">
                                                        <div class="text-center py-4">
                                                            <i class="bx bx-table bx-lg mb-3" style="color: #6c757d;"></i>
                                                            <p class="mb-0">No data found in this table</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($pagination) && $pagination['total'] > 0): ?>
                                            <div class="pagination-container mt-3">
                                                <nav aria-label="Table pagination">
                                                    <ul class="pagination justify-content-center">
                                                        <li class="page-item <?= ($pagination['page'] <= 1) ? 'disabled' : '' ?>">
                                                            <a class="page-link" 
                                                            href="?table=<?= urlencode($currentTable) ?>&page=<?= $pagination['page'] - 1 ?>&perPage=<?= $pagination['perPage'] ?>" 
                                                            style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                                                Previous
                                                            </a>
                                                        </li>
                                                        
                                                        <?php 
                                                        $startPage = max(1, $pagination['page'] - 2);
                                                        $endPage = min($pagination['totalPages'], $pagination['page'] + 2);
                                                        
                                                        if ($startPage > 1): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" 
                                                                href="?table=<?= urlencode($currentTable) ?>&page=1&perPage=<?= $pagination['perPage'] ?>" 
                                                                style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                                                    1
                                                                </a>
                                                            </li>
                                                            <?php if ($startPage > 2): ?>
                                                                <li class="page-item disabled">
                                                                    <span class="page-link" style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px;">
                                                                        ...
                                                                    </span>
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        
                                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                            <li class="page-item <?= ($i == $pagination['page']) ? 'active' : '' ?>">
                                                                <a class="page-link" 
                                                                href="?table=<?= urlencode($currentTable) ?>&page=<?= $i ?>&perPage=<?= $pagination['perPage'] ?>" 
                                                                style="color: <?= ($i == $pagination['page']) ? '#ffffff' : '#ffffff'; ?>; background-color: <?= ($i == $pagination['page']) ? '#00cc6666' : '#000000'; ?>; border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                                                    <?= $i ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>
                                                        
                                                        <?php if ($endPage < $pagination['totalPages']): ?>
                                                            <?php if ($endPage < $pagination['totalPages'] - 1): ?>
                                                                <li class="page-item disabled">
                                                                    <span class="page-link" style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px;">
                                                                        ...
                                                                    </span>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li class="page-item">
                                                                <a class="page-link" 
                                                                href="?table=<?= urlencode($currentTable) ?>&page=<?= $pagination['totalPages'] ?>&perPage=<?= $pagination['perPage'] ?>" 
                                                                style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                                                    <?= $pagination['totalPages'] ?>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <li class="page-item <?= ($pagination['page'] >= $pagination['totalPages']) ? 'disabled' : '' ?>">
                                                            <a class="page-link" 
                                                            href="?table=<?= urlencode($currentTable) ?>&page=<?= $pagination['page'] + 1 ?>&perPage=<?= $pagination['perPage'] ?>" 
                                                            style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; margin: 0 2px; border-radius: 4px; padding: 6px 12px; text-decoration: none; transition: all 0.3s ease;">
                                                                Next
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </nav>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">
                                                    Showing <?= (($pagination['page'] - 1) * $pagination['perPage'] + 1) ?> to 
                                                    <?= min($pagination['page'] * $pagination['perPage'], $pagination['total']) ?> of 
                                                    <?= $pagination['total'] ?> entries
                                                </small>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="perPageDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="color:rgb(255, 255, 255); background-color:rgb(0, 0, 0); border: 1px solid #00cc6666; border-radius: 4px; padding: 6px 12px;">
                                                        <?= $pagination['perPage'] ?> per page
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="perPageDropdown" style="background-color: #000000; border: 1px solid #00cc6666;">
                                                        <li><a class="dropdown-item" href="?table=<?= urlencode($currentTable) ?>&page=1&perPage=5" style="color: #ffffff;">5 per page</a></li>
                                                        <li><a class="dropdown-item" href="?table=<?= urlencode($currentTable) ?>&page=1&perPage=10" style="color: #ffffff;">10 per page</a></li>
                                                        <li><a class="dropdown-item" href="?table=<?= urlencode($currentTable) ?>&page=1&perPage=25" style="color: #ffffff;">25 per page</a></li>
                                                        <li><a class="dropdown-item" href="?table=<?= urlencode($currentTable) ?>&page=1&perPage=50" style="color: #ffffff;">50 per page</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Structure Tab -->
                                    <div class="" id="structure" role="tabpanel" aria-labelledby="structure-tab">
                                        <div class="structure-card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Struktur Tabel</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table-custom">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Type</th>
                                                                <th>Null</th>
                                                                <th>Key</th>
                                                                <th>Default</th>
                                                                <th>Extra</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tableStructure as $column): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($column['Field']) ?></td>
                                                                    <td><?= htmlspecialchars($column['Type']) ?></td>
                                                                    <td><?= htmlspecialchars($column['Null']) ?></td>
                                                                    <td><?= htmlspecialchars($column['Key']) ?></td>
                                                                    <td><?= htmlspecialchars($column['Default'] ?? 'NULL') ?></td>
                                                                    <td><?= htmlspecialchars($column['Extra']) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php else: ?>
                        <div class="card" style="background-color:rgb(255, 255, 255);">
                            <div class="card-body text-center py-5">
                                <i class="bx bx-database bx-lg mb-3" style="color:rgb(255, 255, 255);"></i>
                                <h5 class="text-white">No table selected</h5>
                                <p class="text-muted">Please select a table from the sidebar to view its contents</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="mt-5 mb-3">
                <div class="text-center">
                    <p class="text-muted mb-0">© 2025 UIN Sumatera Utara | Portal Sistem Informasi Akademik</p>
                    <small class="text-muted">By : Muhammad Richie Hadiansah</small>
                </div>
            </footer>
        </div>
    </main>
    
    <script src="../../../js/script.js"></script>
    <script>
        // Add active class to current table in sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const currentTable = '<?= $currentTable ?>';
            if (currentTable) {
                const tableLinks = document.querySelectorAll('.list-group-item');
                tableLinks.forEach(link => {
                    if (link.textContent.trim() === currentTable) {
                        link.classList.add('active');
                    }
                });
            }
            
            // Update URL when changing per page value
            const perPageLinks = document.querySelectorAll('.dropdown-item[href*="perPage="]');
            perPageLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const newUrl = this.getAttribute('href');
                    window.location.href = newUrl;
                });
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection
if (isset($conn) && $conn) {
    $conn->close();
}
?>