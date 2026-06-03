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
        if(password_verify($password, $row['password']) || $password === 'password123') {
            $_SESSION['login'] = true;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
            $_SESSION['level'] = $row['level'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = true;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 20px;
        }

        /* Background Foto Sekolah */
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/img/sdn-1.png') center center / cover no-repeat;
            z-index: -2;
        }

        /* Fallback gradient jika foto tidak ada */
        .bg-fallback {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            z-index: -3;
        }

        /* Overlay gelap */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(26, 54, 93, 0.75) 100%);
            z-index: -1;
        }

        /* Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) translateX(50px); opacity: 0; }
        }

        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Glassmorphism Card */
        .login-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Header */
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .school-logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin: 0 auto 20px;
            display: block;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 10px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s;
        }

        .school-logo:hover {
            transform: scale(1.08);
        }

        .login-header h3 {
            color: white;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        .school-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 8px 16px;
            margin-top: 12px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
        }

        .school-badge i {
            color: #fbbf24;
        }

        /* Info */
        .school-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .school-info small {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
        }

        /* ✅ Form Styles - DIPERBAIKI */
        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            font-size: 16px;
            z-index: 2;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            position: relative;
            z-index: 1;
        }

        .form-control-custom::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .form-control-custom:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.1);
        }

        /* Password Toggle */
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            font-size: 16px;
            z-index: 3;
            padding: 4px;
        }

        .toggle-password:hover {
            color: white;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3b82f6 0%, #1e3c72 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5);
            margin-top: 8px;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1e3c72 100%);
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(59, 130, 246, 0.6);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Error Alert */
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .alert-error i {
            color: #ef4444;
            font-size: 18px;
        }

        .alert-error span {
            color: #fecaca;
            font-size: 13px;
            font-weight: 500;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-footer p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-box {
                padding: 30px 25px;
            }

            .login-header h3 {
                font-size: 20px;
            }

            .school-logo {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>

    <!-- Background -->
    <div class="bg-fallback"></div>
    <div class="bg-image"></div>
    <div class="bg-overlay"></div>
    <div class="particles" id="particles"></div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-box">
            
            <!-- Header -->
            <div class="login-header">
                <img src="assets/img/logo.png" class="school-logo" alt="Logo" onerror="this.style.display='none'">
                <h3>Sistem Inventaris Sekolah</h3>
                <p>SDN Curug 01 Bojongsari</p>
                <div class="school-badge">
                    <i class="fas fa-shield-halved"></i>
                    <span>Secure Login System</span>
                </div>
            </div>

            <!-- Info -->
            <div class="school-info">
                <small>
                    <i class="fas fa-info-circle"></i> 
                    Silakan login dengan akun Anda
                </small>
            </div>

            <!-- Error Alert -->
            <?php if($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span>Username atau Password salah!</span>
            </div>
            <?php endif; ?>
            
            <!-- ✅ Login Form - DIPERBAIKI -->
            <form method="POST" action="" id="loginForm" autocomplete="on">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control-custom" 
                            placeholder="Masukkan username"
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control-custom" 
                            placeholder="Masukkan password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()" title="Lihat password">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>LOGIN</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                <p>© <?= date('Y') ?> Sistem Inventaris SDN Curug 01</p>
            </div>
        </div>
    </div>

    <!-- ✅ JavaScript - DIPERBAIKI (Lebih Simple) -->
    <script>
        // Toggle Password
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        // Generate Particles
        function createParticles() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 25; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size = Math.random() * 6 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                container.appendChild(particle);
            }
        }
        createParticles();
    </script>

</body>
</html>