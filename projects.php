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
$uploadDir = __DIR__ . '/uploads/projects';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $projectFile = null;

    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === UPLOAD_ERR_OK) {
        $tmpFile = $_FILES['project_file']['tmp_name'];
        $originalName = basename($_FILES['project_file']['name']);
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
        $destination = $uploadDir . '/' . $safeName;

        if (is_uploaded_file($tmpFile) && move_uploaded_file($tmpFile, $destination)) {
            $projectFile = 'uploads/projects/' . $safeName;
        }
    }

    if ($name) {
        executeQuery(
            'INSERT INTO projects (name, description, project_file, status, created_at) VALUES (:name, :description, :project_file, :status, NOW())',
            [':name' => $name, ':description' => $description, ':project_file' => $projectFile, ':status' => $status]
        );
        logActivity($user['id'], 'projects', 'create', 'Project created: ' . $name . ' (' . $status . ')');
        $message = 'Project berhasil ditambahkan.' . ($projectFile ? ' File proyek berhasil diunggah.' : '');
    }
}

if (isset($_GET['delete']) && $isAdmin) {
    $projectId = (int)$_GET['delete'];
    $projectInfo = fetchOne('SELECT name, project_file FROM projects WHERE id = :id', [':id' => $projectId]);
    if ($projectInfo) {
        logActivity($user['id'], 'projects', 'delete', 'Project deleted: ' . $projectInfo['name']);
        if (!empty($projectInfo['project_file'])) {
            $filePath = __DIR__ . '/' . ltrim($projectInfo['project_file'], '/');
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
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
                <?php if ($isAdmin): ?>
                    <a class="menu-item" href="users.php">Users</a>
                <?php endif; ?>
                <a class="menu-item" href="tasks.php">Tasks</a>
                <?php if (!$isAdmin): ?>
                    <a class="menu-item" href="messages.php">Messages</a>
                    <a class="menu-item" href="plans.php">Plans</a>
                    <a class="menu-item" href="board.php">Board</a>
                <?php endif; ?>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2><?php echo $isAdmin ? 'Project Management' : 'My Projects'; ?></h2>
                    <p><?php echo $isAdmin ? 'Kelola daftar proyek dan statusnya.' : 'Lihat dan buat proyek Anda sendiri.'; ?></p>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <section class="panel">
                <h3>Tambah Project Baru</h3>
                <form method="post" enctype="multipart/form-data" class="form-grid">
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
                        <label>Upload File Project</label>
                        <input type="file" name="project_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png,.webp">
                        <div class="upload-help">Contoh file yang didukung: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, WEBP, ZIP, RAR</div>
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
                                <th>File</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projects)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Belum ada project. Tambahkan project baru di atas.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['status']); ?></td>
                                    <td><?php echo htmlspecialchars($project['task_count']); ?></td>
                                    <td>
                                        <?php if (!empty($project['project_file'])): ?>
                                            <div class="action-row file-actions">
                                                <a class="button-link preview" href="<?php echo htmlspecialchars($project['project_file']); ?>" target="_blank" rel="noopener">👁 Preview</a>
                                                <a class="button-link primary" href="<?php echo htmlspecialchars($project['project_file']); ?>" download>↓ Download</a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge completed" style="background:#f3f4f6; color:#374151;">No File</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['created_at']); ?></td>
                                    <td>
                                        <?php if ($isAdmin): ?>
                                            <a class="button-link delete" href="projects.php?delete=<?php echo $project['id']; ?>" onclick="return confirm('Hapus project ini?');">🗑 Hapus</a>
                                        <?php else: ?>
                                            <span class="badge completed" style="background:#ecfdf5; color:#166534;">View</span>
                                        <?php endif; ?>
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
