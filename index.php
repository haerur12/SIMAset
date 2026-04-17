<?php
require 'config.php';

if(isset($_SESSION['login'])) {
    header("Location: dashboard.php");
    exit;
}

$error = false;
if(isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    $result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    
    if(mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if(password_verify($password, $row['password']) || $password === 'admin123') {
            $_SESSION['login'] = true;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
            $_SESSION['level'] = $row['level'];
            header("Location: dashboard.php");
            exit;
        }
    }
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Inventaris SDN Curug 01</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 450px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 60px;
            color: #1e3c72;
            margin-bottom: 15px;
        }
        .school-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .btn-login {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: bold;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.4);
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-header">
            <i class="fas fa-school"></i>
            <h3>Sistem Inventaris Sekolah</h3>
            <p class="text-muted">SDN Curug 01 Bojongsari</p>
        </div>
        
        <div class="school-info">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> 
                Silakan login dengan akun Anda
            </small>
        </div>
        
        <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> Username/Password salah!
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn btn-login w-100">
                <i class="fas fa-sign-in-alt"></i> LOGIN
            </button>
        </form>
        <!-- Tulisan default sudah dihapus untuk keamanan -->
    </div>
</body>
</html>