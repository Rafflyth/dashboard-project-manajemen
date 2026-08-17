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

if (isset($_GET['mark_read'])) {
    $id = (int)($_GET['mark_read'] ?? 0);
    executeQuery('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid', [':id' => $id, ':uid' => $user['id']]);
    header('Location: notifications.php');
    exit;
}

$notifications = fetchAll('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC', [':uid' => $user['id']]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
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
                    <a class="menu-item active" href="notifications.php">Notifications</a>
                    <a class="menu-item" href="settings.php">Settings</a>
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
                    <h2>Notifications</h2>
                    <p>Daftar pemberitahuan dan update kerja Anda.</p>
                </div>
            </header>

            <section class="panel table-card">
                <h3>Inbox</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Pesan</th>
                                <th>Status</th>
                                <th>Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notifications)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Belum ada notifikasi.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notifications as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td><?php echo htmlspecialchars($item['message']); ?></td>
                                        <td><?php echo (int)$item['is_read'] === 1 ? 'Read' : 'Unread'; ?></td>
                                        <td><?php echo htmlspecialchars($item['created_at']); ?></td>
                                        <td>
                                            <?php if ((int)$item['is_read'] === 0): ?>
                                                <a class="button-link" href="notifications.php?mark_read=<?php echo (int)$item['id']; ?>">Tandai dibaca</a>
                                            <?php else: ?>
                                                <span class="badge completed">Sudah dibaca</span>
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
