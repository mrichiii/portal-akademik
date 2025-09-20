<?php
session_start();
include 'includes/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $data = $result->fetch_assoc();
        $_SESSION['user_id'] = $data['id'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['nama'] = $data['nama'];
        $_SESSION['role'] = $data['role'];
        if ($data['role'] == 'admin') {
          header("Location: ./views/admin/dashboard.php");
      } elseif ($data['role'] == 'dosen') {
          header("Location: ./views/dosen/dashboard.php");
      } elseif ($data['role'] == 'mahasiswa') {
          header("Location: ./views/mahasiswa/dashboard.php");
      }
    } else {
        echo "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Akademik</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="website icon" type="png" href="img/logouin.png">
</head>
<body>

<!-- Shape Full  -->
<div class="bg-shape"></div>

<!-- kontainer log in -->
<div class="container">
 <img src="img/logouin.png" alt="Logo UIN" class="logo" />
 <form action="login.php" method="POST">
   <h1>Portal Akademik</h1>
   <div class="form-group">
     <label for="username">NIM / NIP / Username</label>
     <input type="text" id="username" name="username" class="form-control" required placeholder="Masukkan NIM / Username">
   </div>
   <div class="form-group">
     <label for="password">Password</label>
     <input type="password" id="password" name="password" class="form-control" required placeholder="Masukkan Password">
   </div><br>
   <input type="submit" class="btn" value="Login" />
   <div id="errorMsg" class="error-message"></div>
 </form>
</div>

</body>
</html>

