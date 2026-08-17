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
$detailTaskId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
$detailTask = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Pending');

    if ($taskId && $status) {
        executeQuery(
            'UPDATE tasks SET status = :status WHERE id = :id AND assigned_user_id = :uid',
            [':status' => $status, ':id' => $taskId, ':uid' => $user['id']]
        );
        $message = 'Status tugas berhasil diperbarui.';
    }
}

if ($detailTaskId) {
    $detailTask = fetchOne(
        'SELECT t.*, p.name AS project_name, u.name AS assignee_name FROM tasks t JOIN projects p ON p.id = t.project_id JOIN users u ON u.id = t.assigned_user_id WHERE t.id = :id AND t.assigned_user_id = :uid',
        [':id' => $detailTaskId, ':uid' => $user['id']]
    );
}

$statuses = ['Pending', 'In Progress', 'Completed'];
$tasks = fetchAll('SELECT t.*, p.name AS project_name FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.assigned_user_id = :uid ORDER BY FIELD(t.status, "Pending","In Progress","Completed")', [':uid' => $user['id']]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Board</title>
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
                <a class="menu-item" href="tasks.php">My Tasks</a>
                <a class="menu-item" href="messages.php">Messages</a>
                <a class="menu-item active" href="board.php">Board</a>
                <a class="menu-item" href="plans.php">Plans</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Board</h2>
                    <p>Kelola pekerjaan Anda dalam panel status.</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($detailTask): ?>
                <section class="detail-panel">
                    <h4>Detail Tugas</h4>
                    <div class="detail-meta">
                        <div><strong>Judul:</strong> <?php echo htmlspecialchars($detailTask['title']); ?></div>
                        <div><strong>Project:</strong> <?php echo htmlspecialchars($detailTask['project_name']); ?></div>
                        <div><strong>Status:</strong> <?php echo htmlspecialchars($detailTask['status']); ?></div>
                        <div><strong>Due Date:</strong> <?php echo htmlspecialchars($detailTask['due_date'] ?: '-'); ?></div>
                    </div>
                    <div class="detail-body"><?php echo nl2br(htmlspecialchars($detailTask['description'] ?: 'Tidak ada deskripsi tambahan.')); ?></div>
                    <div style="margin-top: 16px;">
                        <a class="button-link secondary" href="board.php">Kembali</a>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel">
                <div class="secondary-grid">
                    <?php foreach ($statuses as $status): ?>
                        <div class="small-card">
                            <h4><?php echo htmlspecialchars($status); ?></h4>
                            <?php $items = array_values(array_filter($tasks, fn($task) => $task['status'] === $status)); ?>
                            <?php if (empty($items)): ?>
                                <p>Tidak ada tugas.</p>
                            <?php else: ?>
                                <?php foreach ($items as $task): ?>
                                    <div class="task-item blue" style="margin-bottom:10px; display:block;">
                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                        <span><?php echo htmlspecialchars($task['project_name']); ?></span>
                                        <div style="margin-top:10px;">
                                            <a class="button-link secondary" href="board.php?detail=<?php echo (int)$task['id']; ?>">Lihat Detail</a>
                                        </div>
                                        <form method="post" style="margin-top:10px;">
                                            <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <?php foreach ($statuses as $statusOption): ?>
                                                    <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $task['status'] === $statusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusOption); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
