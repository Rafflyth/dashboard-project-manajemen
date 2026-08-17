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

$logs = fetchAll(
    'SELECT al.*, u.name AS actor_name FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 50'
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
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
                <a class="menu-item" href="users.php">Users</a>
                <a class="menu-item" href="tasks.php">Tasks</a>
                <a class="menu-item" href="client.php">Clients</a>
                <a class="menu-item active" href="activity_logs.php">Activity Log</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Activity Log</h2>
                    <p>Riwayat aktivitas admin dan sistem.</p>
                </div>
            </header>

            <section class="panel table-card">
                <h3>Log Aktivitas Terbaru</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Admin</th>
                                <th>Modul</th>
                                <th>Aksi</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Belum ada aktivitas.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($log['module']); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['details'] ?: '-'); ?></td>
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
