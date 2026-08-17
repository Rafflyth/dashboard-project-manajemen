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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');

    if ($name) {
        executeQuery(
            'INSERT INTO projects (name, description, status, created_at) VALUES (:name, :description, :status, NOW())',
            [':name' => $name, ':description' => $description, ':status' => $status]
        );
        logActivity($user['id'], 'projects', 'create', 'Project created: ' . $name . ' (' . $status . ')');
        $message = 'Project berhasil ditambahkan.';
    }
}

if (isset($_GET['delete'])) {
    $projectId = (int)$_GET['delete'];
    $projectInfo = fetchOne('SELECT name FROM projects WHERE id = :id', [':id' => $projectId]);
    if ($projectInfo) {
        logActivity($user['id'], 'projects', 'delete', 'Project deleted: ' . $projectInfo['name']);
    }
    executeQuery('DELETE FROM tasks WHERE project_id = :id', [':id' => $projectId]);
    executeQuery('DELETE FROM projects WHERE id = :id', [':id' => $projectId]);
    header('Location: projects.php');
    exit;
}

$projects = fetchAll(
    'SELECT p.*, COUNT(t.id) AS task_count FROM projects p LEFT JOIN tasks t ON t.project_id = p.id GROUP BY p.id ORDER BY p.created_at DESC'
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management</title>
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
                <a class="menu-item active" href="projects.php">Projects</a>
                <a class="menu-item" href="users.php">Users</a>
                <a class="menu-item" href="tasks.php">Tasks</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Project Management</h2>
                    <p>Kelola daftar proyek dan statusnya.</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <section class="panel">
                <h3>Tambah Project Baru</h3>
                <form method="post" class="form-grid">
                    <div>
                        <label>Nama Project</label>
                        <input type="text" name="name" required>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="full-width">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="4"></textarea>
                    </div>
                    <div class="full-width">
                        <button type="submit" class="btn-primary">Simpan Project</button>
                    </div>
                </form>
            </section>

            <section class="panel table-card">
                <h3>Daftar Projects</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Status</th>
                                <th>Tugas</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                                <tbody>
                            <?php if (empty($projects)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Belum ada project. Tambahkan project baru di atas.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['status']); ?></td>
                                    <td><?php echo htmlspecialchars($project['task_count']); ?></td>
                                    <td><?php echo htmlspecialchars($project['created_at']); ?></td>
                                    <td>
                                        <a class="button-link" href="projects.php?delete=<?php echo $project['id']; ?>" onclick="return confirm('Hapus project ini?');">Hapus</a>
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
