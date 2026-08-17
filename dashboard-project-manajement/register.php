<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'Semua field harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid.';
    } elseif ($password !== $confirm) {
        $error = 'Password dan konfirmasi tidak sama.';
    } else {
        $existing = fetchOne('SELECT id FROM users WHERE email = :email', [':email' => $email]);
        if ($existing) {
            $error = 'Email sudah terdaftar.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            executeQuery(
                'INSERT INTO users (name, email, password, role, created_at) VALUES (:name, :email, :password, :role, NOW())',
                [
                    ':name' => $name,
                    ':email' => $email,
                    ':password' => $hash,
                    ':role' => 'user',
                ]
            );
            $success = 'Registrasi berhasil. Silakan login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Daftar</h1>
            <p>Buat akun pengguna baru.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <label>Nama</label>
            <input type="text" name="name" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <label>Konfirmasi Password</label>
            <input type="password" name="confirm_password" required>
            <button type="submit" class="btn-primary">Daftar</button>
        </form>

        <div class="auth-footer">
            <p>Sudah punya akun? <a href="login.php">Masuk</a></p>
        </div>
    </div>
</body>
</html>
