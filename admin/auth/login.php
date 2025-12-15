<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_login_attempt'] = 0;
}

if ($_SESSION['login_attempts'] > 5 && (time() - $_SESSION['last_login_attempt']) < 900) {
    $error = "Too many login attempts. Please try again in 15 minutes.";
    $_POST = []; // Clear POST data
}
require __DIR__ . '/../db-conn.php'; // Database connection

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request";
    } else {
        // Add input validation
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Validate inputs
        if (empty($username) || empty($password)) {
            $error = "Username and password are required";
        } elseif (strlen($username) > 50 || strlen($password) > 255) {
            $error = "Invalid input length";
        }

        // Prepare SQL statement
        $stmt = $conn->prepare("SELECT id, username, password FROM admin_user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        // Use bind_result() instead of get_result()
        $stmt->bind_result($id, $user, $hashed_password);

        if ($stmt->fetch()) { // Fetch result
            if (password_verify($password, $hashed_password)) {
                // Regenerate session ID to prevent session fixation

                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $id;
                $_SESSION['admin_user'] = $user;
                $_SESSION['login_time'] = time();
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

                // Reset login attempts
                $_SESSION['login_attempts'] = 0;

                if (isset($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days

                    // Store token in database
                    $stmt = $conn->prepare("UPDATE admin_user SET remember_token = ?, token_expiry = ? WHERE id = ?");
                    $stmt->bind_param("sii", $token, $expiry, $id);
                    $stmt->execute();

                    setcookie('admin_remember', $token, $expiry, '/', '', true, true);
                }

                // After successful login
                error_log("Admin login successful: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);

                header("Location: ../index.php");
                exit();
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_login_attempt'] = time();
                usleep(rand(200000, 500000));
                $error = "Invalid username or password";
            }
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_login_attempt'] = time();
            usleep(rand(200000, 500000));
            $error = "Invalid username or password";
        }
        // After failed login
        error_log("Failed admin login attempt for username: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
        $stmt->close();
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Portal | Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Add these meta tags for security -->
    <!-- <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; script-src 'self' https://cdn.jsdelivr.net;"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --danger-color: #f72585;
        }

        body {
            background: linear-gradient(135deg, var(--dark-color), #16213e);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }

        .login-container {
            position: relative;
            width: 100%;
            max-width: 420px;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header img {
            width: 80px;
            margin-bottom: 1rem;
        }

        .login-header h3 {
            color: var(--light-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--light-color);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(76, 201, 240, 0.25);
            color: white;
        }

        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .btn-login {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
        }

        .alert {
            border-radius: 8px;
        }

        .floating-bubbles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bubble {
            position: absolute;
            bottom: -100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite ease-in;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }

            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.3s;
        }

        .forgot-password a:hover {
            color: var(--accent-color);
        }

        .password-toggle {
            cursor: pointer;
            color: rgba(255, 255, 255, 0.6);
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: var(--accent-color);
        }
    </style>
</head>

<body>
    <!-- Floating bubbles background -->
    <div class="floating-bubbles">
        <?php for ($i = 0; $i < 15; $i++): ?>
            <div class="bubble" style="
                left: <?= rand(0, 100) ?>%;
                width: <?= rand(20, 100) ?>px;
                height: <?= rand(20, 100) ?>px;
                animation-delay: <?= rand(0, 15) ?>s;
                animation-duration: <?= rand(10, 30) ?>s;
            "></div>
        <?php endfor; ?>
    </div>

    <div class="login-container animate__animated animate__fadeIn">
        <div class="login-card">
            <div class="login-header">
                <img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Admin Logo">
                <h3>ADMIN PORTAL</h3>
                <p>Access your dashboard with secure credentials</p>
            </div>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required placeholder="Enter admin username">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                        <span class="input-group-text password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <div class="forgot-password">
                    <a href="#forgot-password">Forgot Password?</a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger p-2 text-center animate__animated animate__shakeX">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-login mt-3">
                    <i class="fas fa-sign-in-alt me-2"></i> LOGIN
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Add animation to form elements
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach((input, index) => {
                input.style.animationDelay = `${index * 0.1}s`;
                input.classList.add('animate__animated', 'animate__fadeInUp');
            });
        });
    </script>
</body>

</html>