<?php
session_start();
require_once __DIR__ . '/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (!$identity || !$newPassword || !$confirmPassword) {
        $error = 'Semua field wajib diisi.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password baru dan konfirmasi tidak sama.';
    } else {
        $user = fetchOne(
            'SELECT * FROM users WHERE email = :identity OR name = :identity LIMIT 1',
            [':identity' => $identity]
        );

        if (!$user) {
            $error = 'Email atau username tidak ditemukan.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            executeQuery('UPDATE users SET password = :password WHERE id = :id', [':password' => $hash, ':id' => $user['id']]);
            $message = 'Password berhasil direset. Silakan login kembali.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Reset Password</h1>
            <p>Gunakan email atau username akun Anda.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post" action="forgot_password.php">
            <label>Email atau Username</label>
            <input type="text" name="identity" placeholder="admin@example.com atau Admin" required>

            <label>Password Baru</label>
            <input type="password" name="new_password" placeholder="Minimal 6 karakter" required>

            <label>Konfirmasi Password Baru</label>
            <input type="password" name="confirm_password" placeholder="Ulangi password baru" required>

            <button type="submit" class="btn-primary">Reset Password</button>
        </form>

        <div class="auth-footer">
            <p>Sudah ingat? <a href="login.php">Login</a></p>
        </div>
    </div>
</body>
</html>
