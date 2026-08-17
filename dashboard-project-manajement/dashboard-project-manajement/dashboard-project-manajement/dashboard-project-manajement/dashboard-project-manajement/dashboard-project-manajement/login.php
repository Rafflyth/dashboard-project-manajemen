<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $user = authenticateUser($email, $password);
        if ($user) {
            $normalizedRole = normalizeUserRole($user['role'] ?? '');
            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $normalizedRole,
            ];
            header('Location: dashboard.php');
            exit;
        }
    }

    $error = 'Email atau password salah. Gunakan admin@example.com / admin123 atau user@example.com / user123.';
}

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Login</h1>
        </div>
        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="login.php">
            <label>Email</label>
            <input type="email" name="email" required placeholder="admin@example.com">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Password">
            <button type="submit" class="btn-primary">Login</button>
        </form>
        <div class="auth-note">
            <p></p>
            <p></p>
        </div>
        <div class="auth-footer">
            <p><a href="forgot_password.php">Lupa password?</a></p>
            <p>Belum punya akun? <a href="register.php">Daftar</a></p>
        </div>
    </div>
</body>
</html>
