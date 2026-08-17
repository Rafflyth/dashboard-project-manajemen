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
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$searchTerm = trim((string)($_GET['search'] ?? ''));
$detailTaskId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
$detailTask = null;
$allowedStatuses = ['all', 'Pending', 'In Progress', 'Completed'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $message = 'Tugas berhasil diselesaikan.';
}
if ($detailTaskId) {
    if ($isAdmin) {
        $detailTask = fetchOne(
            'SELECT t.*, p.name AS project_name, u.name AS assignee_name FROM tasks t JOIN projects p ON p.id = t.project_id JOIN users u ON u.id = t.assigned_user_id WHERE t.id = :id',
            [':id' => $detailTaskId]
        );
    } else {
        $detailTask = fetchOne(
            'SELECT t.*, p.name AS project_name FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = :id AND t.assigned_user_id = :uid',
            [':id' => $detailTaskId, ':uid' => $user['id']]
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $projectId = (int)($_POST['project_id'] ?? 0);
    $assignedUserId = (int)($_POST['assigned_user_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Pending');
    $dueDate = trim($_POST['due_date'] ?? '');
    $taskFile = null;

    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $taskFile = safeUploadFile($_FILES['task_file'], 'uploads/tasks', 10485760);
        if ($taskFile === null) {
            $message = 'Format atau ukuran file tugas tidak valid. Gunakan PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, WEBP, ZIP, RAR, atau TXT dengan maksimal 10 MB.';
        }
    }

    if ($title && $projectId && $assignedUserId && $message === '') {
        executeQuery(
            'INSERT INTO tasks (project_id, title, description, task_file, assigned_user_id, status, due_date, created_at) VALUES (:project_id, :title, :description, :task_file, :assigned_user_id, :status, :due_date, NOW())',
            [
                ':project_id' => $projectId,
                ':title' => $title,
                ':description' => trim($_POST['description'] ?? ''),
                ':task_file' => $taskFile,
                ':assigned_user_id' => $assignedUserId,
                ':status' => $status,
                ':due_date' => $dueDate ?: null,
            ]
        );
        if ($isAdmin) {
            logActivity($user['id'], 'tasks', 'create', 'Task created: ' . $title . ' (assigned to user ID ' . $assignedUserId . ')');
        }
        $message = 'Tugas berhasil dibuat.' . ($taskFile ? ' Lampiran file berhasil diunggah.' : '');
    }
}

if (isset($_GET['complete']) && !$isAdmin) {
    $taskId = (int)$_GET['complete'];
    executeQuery('UPDATE tasks SET status = :status WHERE id = :id AND assigned_user_id = :uid', [':status' => 'Completed', ':id' => $taskId, ':uid' => $user['id']]);
    header('Location: tasks.php?success=1');
    exit;
}

if (isset($_GET['delete']) && $isAdmin) {
    $taskId = (int)$_GET['delete'];
    $taskInfo = fetchOne('SELECT title FROM tasks WHERE id = :id', [':id' => $taskId]);
    if ($taskInfo) {
        logActivity($user['id'], 'tasks', 'delete', 'Task deleted: ' . $taskInfo['title']);
    }
    executeQuery('DELETE FROM tasks WHERE id = :id', [':id' => $taskId]);
    header('Location: tasks.php');
    exit;
}

$projects = fetchAll('SELECT id, name FROM projects ORDER BY name ASC');
$users = fetchAll('SELECT id, name FROM users ORDER BY name ASC');

$taskBaseSql = 'SELECT t.*, p.name AS project_name, u.name AS assignee_name FROM tasks t JOIN projects p ON p.id = t.project_id JOIN users u ON u.id = t.assigned_user_id';
$taskParams = [];
$taskWhere = '';
if ($isAdmin) {
    $taskWhere = '';
} else {
    $taskWhere = ' WHERE t.assigned_user_id = :uid';
    $taskParams[':uid'] = $user['id'];
}
if ($statusFilter !== 'all') {
    $taskWhere .= ($taskWhere === '' ? ' WHERE ' : ' AND ') . 't.status = :status';
    $taskParams[':status'] = $statusFilter;
}
if ($searchTerm !== '') {
    $taskWhere .= ($taskWhere === '' ? ' WHERE ' : ' AND ') . '(t.title LIKE :search OR p.name LIKE :search OR u.name LIKE :search)';
    $taskParams[':search'] = '%' . $searchTerm . '%';
}
$tasks = fetchAll($taskBaseSql . $taskWhere . ' ORDER BY t.created_at DESC', $taskParams);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management</title>
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
                <?php if ($isAdmin): ?>
                    <a class="menu-item" href="projects.php">Projects</a>
                    <a class="menu-item" href="users.php">Users</a>
                <?php endif; ?>
                <a class="menu-item active" href="tasks.php">Tasks</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Task Management</h2>
                    <p><?php echo $isAdmin ? 'Kelola semua tugas proyek.' : 'Lihat dan selesaikan tugas Anda.'; ?></p>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="toolbar-row">
                <div class="status-tabs">
                    <a href="tasks.php?status=all<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">Semua</a>
                    <a href="tasks.php?status=Pending<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'Pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="tasks.php?status=In Progress<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'In Progress' ? 'active' : ''; ?>">In Progress</a>
                    <a href="tasks.php?status=Completed<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'Completed' ? 'active' : ''; ?>">Completed</a>
                </div>

                <form class="search-box" method="get">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Cari tugas, project, user...">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <button type="submit">Cari</button>
                </form>
            </div>

            <?php if ($detailTask): ?>
                <section class="detail-panel">
                    <h4>Detail Tugas</h4>
                    <div class="detail-meta">
                        <div><strong>Judul:</strong> <?php echo htmlspecialchars($detailTask['title']); ?></div>
                        <div><strong>Project:</strong> <?php echo htmlspecialchars($detailTask['project_name']); ?></div>
                        <div><strong>Status:</strong> <?php echo htmlspecialchars($detailTask['status']); ?></div>
                        <div><strong>Due Date:</strong> <?php echo htmlspecialchars($detailTask['due_date'] ?: '-'); ?></div>
                        <?php if ($isAdmin): ?>
                            <div><strong>Assignee:</strong> <?php echo htmlspecialchars($detailTask['assignee_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="detail-body"><?php echo nl2br(htmlspecialchars($detailTask['description'] ?: 'Tidak ada deskripsi tambahan.')); ?></div>
                    <div style="margin-top: 16px;">
                        <a class="button-link secondary" href="tasks.php?status=<?php echo urlencode($statusFilter); ?>">Kembali</a>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <section class="panel">
                    <h3>Tambah Tugas Baru</h3>
                    <form method="post" enctype="multipart/form-data" class="form-grid">
                        <div>
                            <label>Judul Tugas</label>
                            <input type="text" name="title" required>
                        </div>
                        <div>
                            <label>Project</label>
                            <select name="project_id" required>
                                <option value="">Pilih project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Ditugaskan ke</label>
                            <select name="assigned_user_id" required>
                                <option value="">Pilih pengguna</option>
                                <?php foreach ($users as $assignee): ?>
                                    <option value="<?php echo $assignee['id']; ?>"><?php echo htmlspecialchars($assignee['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="full-width">
                            <label>Deskripsi</label>
                            <textarea name="description" rows="4"></textarea>
                        </div>
                        <div class="full-width file-upload-wrap">
                            <label>Upload Lampiran Tugas</label>
                            <div class="file-upload-box">
                                <span class="upload-icon">📎</span>
                                <input type="file" name="task_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.zip,.rar,.txt">
                            </div>
                            <div class="upload-help">Contoh file yang didukung: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, WEBP, ZIP, RAR, TXT (maks. 10 MB)</div>
                        </div>
                        <div>
                            <label>Tanggal Jatuh Tempo</label>
                            <input type="date" name="due_date">
                        </div>
                        <div class="full-width">
                            <button type="submit" class="btn-primary">Simpan Tugas</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="panel table-card">
                <h3>Daftar Tugas</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Project</th>
                                <?php if ($isAdmin): ?>
                                    <th>Assignee</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tasks)): ?>
                                <tr>
                                    <td colspan="<?php echo $isAdmin ? 6 : 5; ?>" class="text-center">Tidak ada tugas untuk ditampilkan. <?php echo $isAdmin ? 'Tambahkan tugas baru di atas.' : 'Silakan hubungi admin untuk mendapatkan penugasan.'; ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                                    <?php if ($isAdmin): ?>
                                        <td><?php echo htmlspecialchars($task['assignee_name']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($task['status']); ?></td>
                                    <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                                    <td>
                                        <div class="action-row">
                                            <a class="button-link view" href="tasks.php?status=<?php echo urlencode($statusFilter); ?>&detail=<?php echo (int)$task['id']; ?>">👁 Detail</a>
                                            <?php if (!empty($task['task_file'])): ?>
                                                <a class="button-link primary" href="<?php echo htmlspecialchars($task['task_file']); ?>" target="_blank" rel="noopener">↓ File</a>
                                            <?php endif; ?>
                                            <?php if (!$isAdmin): ?>
                                                <?php if ($task['status'] !== 'Completed'): ?>
                                                    <a class="button-link success" href="tasks.php?complete=<?php echo $task['id']; ?>">✓ Selesai</a>
                                                <?php else: ?>
                                                    <span class="badge completed">Selesai</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a class="button-link delete" href="tasks.php?delete=<?php echo $task['id']; ?>" onclick="return confirm('Hapus tugas ini?');">🗑 Hapus</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
