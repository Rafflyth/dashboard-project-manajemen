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

$taskCount = 0;
$taskPending = 0;
$taskCompleted = 0;
$totalUsers = (int)(fetchOne('SELECT COUNT(*) AS count FROM users')['count'] ?? 0);
$totalProjects = (int)(fetchOne('SELECT COUNT(*) AS count FROM projects')['count'] ?? 0);
$totalTasks = (int)(fetchOne('SELECT COUNT(*) AS count FROM tasks')['count'] ?? 0);
$totalClients = (int)(fetchOne('SELECT COUNT(*) AS count FROM clients')['count'] ?? 0);
$totalNotifications = (int)(fetchOne('SELECT COUNT(*) AS count FROM notifications')['count'] ?? 0);

if (!$isAdmin) {
    $taskCount = (int)(fetchOne('SELECT COUNT(*) AS count FROM tasks WHERE assigned_user_id = :uid', [':uid' => $user['id']])['count'] ?? 0);
    $taskPending = (int)(fetchOne('SELECT COUNT(*) AS count FROM tasks WHERE assigned_user_id = :uid AND status != :status', [':uid' => $user['id'], ':status' => 'Completed'])['count'] ?? 0);
    $taskCompleted = (int)(fetchOne('SELECT COUNT(*) AS count FROM tasks WHERE assigned_user_id = :uid AND status = :status', [':uid' => $user['id'], ':status' => 'Completed'])['count'] ?? 0);
}

$notificationCount = $isAdmin ? $totalNotifications : (int)(fetchOne('SELECT COUNT(*) AS count FROM notifications WHERE user_id = :uid', [':uid' => $user['id']])['count'] ?? 0);
$projectCount = $isAdmin ? $totalProjects : $taskCount;
$clientCount = $isAdmin ? $totalClients : $taskPending;
$createCount = $isAdmin ? $totalTasks : $taskCompleted;

$globalSearch = trim((string)($_GET['q'] ?? ''));
$searchResults = [];
if ($globalSearch !== '') {
    $searchLike = '%' . $globalSearch . '%';

    if ($isAdmin) {
        $searchResults = array_merge(
            $searchResults,
            fetchAll('SELECT id, title, description, status, "task" AS type FROM tasks WHERE title LIKE :q OR description LIKE :q LIMIT 5', [':q' => $searchLike]),
            fetchAll('SELECT id, name AS title, description, status, "project" AS type FROM projects WHERE name LIKE :q OR description LIKE :q LIMIT 5', [':q' => $searchLike]),
            fetchAll('SELECT id, name AS title, email AS description, "member" AS type FROM users WHERE name LIKE :q OR email LIKE :q LIMIT 5', [':q' => $searchLike]),
            fetchAll('SELECT id, name AS title, email AS description, "client" AS type FROM clients WHERE name LIKE :q OR email LIKE :q LIMIT 5', [':q' => $searchLike]),
            fetchAll('SELECT id, subject AS title, message AS description, "message" AS type FROM messages WHERE subject LIKE :q OR message LIKE :q LIMIT 5', [':q' => $searchLike]),
            fetchAll('SELECT id, title, notes AS description, status, "plan" AS type FROM plans WHERE title LIKE :q OR notes LIKE :q LIMIT 5', [':q' => $searchLike]),
            fetchAll('SELECT id, title, message AS description, "notification" AS type FROM notifications WHERE title LIKE :q OR message LIKE :q LIMIT 5', [':q' => $searchLike])
        );
    } else {
        $searchResults = array_merge(
            $searchResults,
            fetchAll('SELECT id, title, description, status, "task" AS type FROM tasks WHERE assigned_user_id = :uid AND (title LIKE :q OR description LIKE :q) LIMIT 5', [':uid' => $user['id'], ':q' => $searchLike]),
            fetchAll('SELECT p.id, p.name AS title, p.description, p.status, "project" AS type FROM projects p JOIN tasks t ON t.project_id = p.id WHERE t.assigned_user_id = :uid AND (p.name LIKE :q OR p.description LIKE :q) GROUP BY p.id LIMIT 5', [':uid' => $user['id'], ':q' => $searchLike]),
            fetchAll('SELECT id, title, notes AS description, status, "plan" AS type FROM plans WHERE user_id = :uid AND (title LIKE :q OR notes LIKE :q) LIMIT 5', [':uid' => $user['id'], ':q' => $searchLike]),
            fetchAll('SELECT id, subject AS title, message AS description, "message" AS type FROM messages WHERE (sender_user_id = :uid OR receiver_user_id = :uid) AND (subject LIKE :q OR message LIKE :q) LIMIT 5', [':uid' => $user['id'], ':q' => $searchLike])
        );
    }

    foreach ($searchResults as &$result) {
        $type = ucfirst((string)($result['type'] ?? 'item'));
        $title = (string)($result['title'] ?? '');
        $meta = (string)($result['status'] ?? ($result['description'] ?? ''));
        $link = '#';

        if (($result['type'] ?? '') === 'task') {
            $link = 'tasks.php?detail=' . (int)$result['id'];
        } elseif (($result['type'] ?? '') === 'project') {
            $link = 'projects.php';
        } elseif (($result['type'] ?? '') === 'member') {
            $link = 'users.php';
        } elseif (($result['type'] ?? '') === 'client') {
            $link = 'client.php';
        } elseif (($result['type'] ?? '') === 'message') {
            $link = 'messages.php?detail=' . (int)$result['id'];
        } elseif (($result['type'] ?? '') === 'plan') {
            $link = 'plans.php?detail=' . (int)$result['id'];
        } elseif (($result['type'] ?? '') === 'notification') {
            $link = 'notifications.php';
        }

        $result = [
            'type' => $type,
            'title' => $title,
            'meta' => $meta,
            'link' => $link,
        ];
    }
    unset($result);
}

$stats = [
    'notification' => $isAdmin ? "$notificationCount Recent Notifications" : "$notificationCount Recent Notifications",
    'project' => $isAdmin ? "$projectCount Active Projects" : ($taskCount ? "$taskCount My Tasks" : 'No Tasks Assigned'),
    'client' => $isAdmin ? "$clientCount Clients" : "$taskPending Pending",
    'create' => $isAdmin ? "$createCount Open Tasks" : "$taskCompleted Completed",
];

$chartLabel = $isAdmin ? 'Project Statistic' : 'Progress Overview';
$taskTitle = $isAdmin ? 'Latest Tasks' : 'Daily Task';

if ($isAdmin) {
    $taskItems = fetchAll(
        'SELECT t.title, t.status, p.name AS project_name, u.name AS assignee_name, t.due_date FROM tasks t LEFT JOIN projects p ON p.id = t.project_id LEFT JOIN users u ON u.id = t.assigned_user_id ORDER BY t.created_at DESC LIMIT 4'
    );
    $taskItems = array_map(function ($task) {
        $color = 'blue';
        if ($task['status'] === 'Completed') $color = 'green';
        elseif ($task['status'] === 'In Progress') $color = 'yellow';
        elseif ($task['status'] === 'Pending') $color = 'red';

        return [
            'title' => $task['title'],
            'time' => $task['due_date'] ?: 'No date',
            'color' => $color,
            'meta' => $task['project_name'] . ' · ' . $task['assignee_name'],
        ];
    }, $taskItems);
} else {
    $taskItems = fetchAll(
        'SELECT t.title, t.status, p.name AS project_name, t.due_date FROM tasks t LEFT JOIN projects p ON p.id = t.project_id WHERE t.assigned_user_id = :uid ORDER BY t.created_at DESC LIMIT 4',
        [':uid' => $user['id']]
    );
    $taskItems = array_map(function ($task) {
        $color = 'blue';
        if ($task['status'] === 'Completed') $color = 'green';
        elseif ($task['status'] === 'In Progress') $color = 'yellow';
        elseif ($task['status'] === 'Pending') $color = 'red';

        return [
            'title' => $task['title'],
            'time' => $task['due_date'] ?: 'No date',
            'color' => $color,
            'meta' => $task['project_name'],
        ];
    }, $taskItems);
}

$summaryTitle = $isAdmin ? 'Kelola semua proyek, user, dan tugas dari satu dashboard' : 'Lihat ringkasan pekerjaan dan tugas Anda';

$recentActivities = fetchAll(
    'SELECT al.*, u.name AS actor_name FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 5'
);

$notifications = $isAdmin
    ? fetchAll('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 4')
    : fetchAll('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 4', [':uid' => $user['id']]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($user['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-mark"></div>
                <div>
                    <strong>Dashboard</strong>
                    <p>Project Management Admin</p>
                </div>
            </div>
            <nav class="menu">
                <a class="menu-item active" href="dashboard.php">Dashboard</a>
                <?php if ($isAdmin): ?>
                    <a class="menu-item" href="projects.php">Projects</a>
                    <a class="menu-item" href="users.php">Users</a>
                    <a class="menu-item" href="client.php">Clients</a>
                    <a class="menu-item" href="activity_logs.php">Activity Log</a>
                    <a class="menu-item" href="notifications.php">Notifications</a>
                    <a class="menu-item" href="settings.php">Settings</a>
                <?php else: ?>
                    <a class="menu-item" href="projects.php">Projects</a>
                    <a class="menu-item" href="tasks.php">My Tasks</a>
                    <a class="menu-item" href="messages.php">Messages</a>
                    <a class="menu-item" href="board.php">Board</a>
                    <a class="menu-item" href="plans.php">Plans</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php">Log Out</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Dashboard</h2>
                </div>
                <div class="top-right">
                    <form class="global-search-form" method="get" action="dashboard.php">
                        <input type="search" name="q" value="<?php echo htmlspecialchars($globalSearch); ?>" placeholder="Cari semua modul...">
                        <button type="submit">Cari</button>
                    </form>
                    <div class="profile">
                        <span><?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</span>
                    </div>
                </div>
            </header>

            <?php if ($globalSearch !== ''): ?>
                <section class="panel" style="margin-bottom: 24px;">
                    <div class="panel-header">
                        <h3>Hasil pencarian untuk: <?php echo htmlspecialchars($globalSearch); ?></h3>
                    </div>
                    <?php if (!empty($searchResults)): ?>
                        <div class="search-results">
                            <?php foreach ($searchResults as $result): ?>
                                <a class="search-result" href="<?php echo htmlspecialchars($result['link']); ?>">
                                    <span class="search-kind"><?php echo htmlspecialchars($result['type']); ?></span>
                                    <strong><?php echo htmlspecialchars($result['title']); ?></strong>
                                    <small><?php echo htmlspecialchars($result['meta']); ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Tidak ada hasil yang cocok.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="overview-cards">
                <?php foreach ($stats as $key => $text): ?>
                    <article class="card <?php echo $key; ?>">
                        <span><?php echo ucfirst($key); ?></span>
                        <strong><?php echo htmlspecialchars($text); ?></strong>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if (!$isAdmin): ?>
            <section class="panel task-summary-panel">
                <h3>Ringkasan Tugas Saya</h3>
                <div class="summary-grid">
                    <div class="summary-card">
                        <strong><?php echo $taskCount; ?></strong>
                        <span>Total Tugas</span>
                    </div>
                    <div class="summary-card">
                        <strong><?php echo $taskPending; ?></strong>
                        <span>Pending</span>
                    </div>
                    <div class="summary-card">
                        <strong><?php echo $taskCompleted; ?></strong>
                        <span>Selesai</span>
                    </div>
                </div>
                <a class="btn-primary" href="tasks.php">Lihat Tugas Saya</a>
            </section>
            <?php endif; ?>

            <section class="content-grid">
                <div class="panel chart-panel">
                    <div class="panel-header">
                        <h3><?php echo $chartLabel; ?></h3>
                        <div class="periods">
                            <button class="active">1M</button>
                            <button>3M</button>
                            <button>6M</button>
                            <button>All</button>
                        </div>
                    </div>
                    <div class="chart-box">
                        <div class="chart-line chart-line-1"></div>
                        <div class="chart-line chart-line-2"></div>
                    </div>
                </div>

                <div class="panel summary-panel">
                    <h3><?php echo htmlspecialchars($summaryTitle); ?></h3>
                    <p>Kelola tugas, proyek, dan komunikasi sesuai peran Anda.</p>
                    <button class="btn-primary">Try Free</button>
                </div>
            </section>

            <section class="secondary-grid">
                <div class="small-card">
                    <h4>Site Health</h4>
                    <div class="progress-circle">
                        <span>84%</span>
                    </div>
                    <p>Top-10% websites › 92%</p>
                </div>
                <div class="small-card">
                    <h4>Online Sales</h4>
                    <div class="progress-ring">
                        <span>80%</span>
                    </div>
                    <p>Mobile 68%</p>
                </div>
                <div class="task-board">
                    <div class="task-header">
                        <h4><?php echo htmlspecialchars($taskTitle); ?></h4>
                        <span>Task List</span>
                    </div>
                    <div class="task-list">
                        <?php if (empty($taskItems)): ?>
                            <div class="task-item blue">
                                <strong>Tidak ada tugas</strong>
                                <span>Belum ada data</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($taskItems as $task): ?>
                                <div class="task-item <?php echo $task['color']; ?>">
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                        <?php if (!empty($task['meta'])): ?>
                                            <span style="font-size:12px; opacity:0.9"><?php echo htmlspecialchars($task['meta']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($task['time']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if ($isAdmin): ?>
                <section class="panel" style="margin-top: 24px;">
                    <div class="panel-header">
                        <h3>5 Aktivitas Terakhir</h3>
                        <a class="button-link" href="activity_logs.php">Lihat Semua</a>
                    </div>
                    <div class="task-list">
                        <?php if (empty($recentActivities)): ?>
                            <div class="task-item blue">
                                <strong>Belum ada aktivitas</strong>
                                <span>System idle</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="task-item blue" style="display:block; margin-bottom:10px;">
                                    <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:6px;">
                                        <strong><?php echo htmlspecialchars($activity['module']); ?></strong>
                                        <span style="font-size:12px; opacity:0.9"><?php echo htmlspecialchars($activity['created_at']); ?></span>
                                    </div>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <span><?php echo htmlspecialchars($activity['action']); ?></span>
                                        <small><?php echo htmlspecialchars($activity['actor_name'] ?? 'System'); ?> — <?php echo htmlspecialchars($activity['details'] ?: '-'); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
