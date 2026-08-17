<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userRole = normalizeUserRole($user['role'] ?? '');
$isAdmin = isAdminRole($userRole);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $oldPassword = trim($_POST['old_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($name && $email) {
        executeQuery(
            'UPDATE users SET name = :name, email = :email WHERE id = :id',
            [':name' => $name, ':email' => $email, ':id' => $user['id']]
        );
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $message = 'Profil berhasil diperbarui.';
    }

    if ($oldPassword || $newPassword || $confirmPassword) {
        if (!$oldPassword || !$newPassword || !$confirmPassword) {
            $error = 'Semua field password harus diisi.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Password baru dan konfirmasi tidak sama.';
        } elseif (!authenticateUser($user['email'], $oldPassword)) {
            $error = 'Password lama tidak sesuai.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            executeQuery(
                'UPDATE users SET password = :password WHERE id = :id',
                [':password' => $hash, ':id' => $user['id']]
            );
            $message = 'Password berhasil diubah.';
        }
    }
}

$profile = fetchOne('SELECT id, name, email, role FROM users WHERE id = :id', [':id' => $user['id']]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-mark"></div>
                <div>
                    <strong>ProLogs</strong>
                    <p>Project Management</p>
                </div>
            </div>
            <nav class="menu">
                <a class="menu-item" href="dashboard.php">Dashboard</a>
                <?php if ($isAdmin): ?>
                    <a class="menu-item" href="projects.php">Projects</a>
                    <a class="menu-item" href="client.php">Clients</a>
                    <a class="menu-item" href="notifications.php">Notifications</a>
                    <a class="menu-item active" href="settings.php">Settings</a>
                <?php else: ?>
                    <a class="menu-item" href="tasks.php">My Tasks</a>
                    <a class="menu-item" href="messages.php">Messages</a>
                    <a class="menu-item" href="board.php">Board</a>
                    <a class="menu-item" href="plans.php">Plans</a>
                <?php endif; ?>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Settings</h2>
                    <p>Ubah profil dan preferensi akun Anda.</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <section class="panel">
                <h3>Profil Pengguna</h3>
                <form method="post" class="form-grid">
                    <div>
                        <label>Nama Lengkap</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                    </div>
                    <div class="full-width">
                        <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <h3>Ubah Password</h3>
                <form method="post" class="form-grid">
                    <div>
                        <label>Password Lama</label>
                        <input type="password" name="old_password" placeholder="Masukkan password lama">
                    </div>
                    <div></div>
                    <div>
                        <label>Password Baru</label>
                        <input type="password" name="new_password" placeholder="Minimal 6 karakter">
                    </div>
                    <div>
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" placeholder="Ulangi password baru">
                    </div>
                    <div class="full-width">
                        <button type="submit" class="btn-primary">Ubah Password</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
