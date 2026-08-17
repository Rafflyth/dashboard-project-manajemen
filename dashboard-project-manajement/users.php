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

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== (int)$user['id']) {
        $targetUser = fetchOne('SELECT name, email FROM users WHERE id = :id', [':id' => $id]);
        if ($targetUser) {
            logActivity($user['id'], 'users', 'delete', 'User deleted: ' . $targetUser['name'] . ' (' . $targetUser['email'] . ')');
        }
        executeQuery('DELETE FROM users WHERE id = :id', [':id' => $id]);
    }
    header('Location: users.php');
    exit;
}

$users = fetchAll('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-mark"></div>
                <div>
                    <strong>ProLogs</strong>
                    <p>Project Management Admin</p>
                </div>
            </div>
            <nav class="menu">
                <a class="menu-item" href="dashboard.php">Dashboard</a>
                <a class="menu-item" href="projects.php">Projects</a>
                <a class="menu-item active" href="users.php">Users</a>
                <a class="menu-item" href="tasks.php">Tasks</a>
                <a class="menu-item" href="client.php">Clients</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>User Management</h2>
                    <p>Kelola seluruh pengguna di sistem.</p>
                </div>
            </header>

            <section class="panel table-card">
                <h3>Daftar Users</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Belum ada user.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $person): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($person['name']); ?></td>
                                        <td><?php echo htmlspecialchars($person['email']); ?></td>
                                        <td><?php echo htmlspecialchars($person['role']); ?></td>
                                        <td><?php echo htmlspecialchars($person['created_at']); ?></td>
                                        <td>
                                            <?php if ((int)$person['id'] !== (int)$user['id']): ?>
                                                <div class="action-row">
                                                    <a class="button-link danger" href="users.php?delete=<?php echo (int)$person['id']; ?>" onclick="return confirm('Hapus user ini?');">Hapus</a>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge completed">Anda</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
