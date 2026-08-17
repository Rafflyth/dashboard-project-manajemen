<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$planMessage = '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$searchTerm = trim((string)($_GET['search'] ?? ''));
$detailPlanId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
$detailPlan = null;
$allowedStatuses = ['all', 'Planned', 'In Progress', 'Completed'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}
$editPlan = null;
$editPlanId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editPlanId) {
    $editPlan = fetchOne('SELECT * FROM plans WHERE id = :id AND user_id = :uid', [':id' => $editPlanId, ':uid' => $user['id']]);
}
if ($detailPlanId) {
    $detailPlan = fetchOne('SELECT * FROM plans WHERE id = :id AND user_id = :uid', [':id' => $detailPlanId, ':uid' => $user['id']]);
}

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    executeQuery('DELETE FROM plans WHERE id = :id AND user_id = :uid', [':id' => $deleteId, ':uid' => $user['id']]);
    header('Location: plans.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Planned');

    if ($title) {
        if ($planId) {
            executeQuery(
                'UPDATE plans SET title = :title, notes = :notes, due_date = :due_date, status = :status WHERE id = :id AND user_id = :uid',
                [':title' => $title, ':notes' => $notes, ':due_date' => $dueDate ?: null, ':status' => $status, ':id' => $planId, ':uid' => $user['id']]
            );
            $planMessage = 'Rencana berhasil diperbarui.';
        } else {
            executeQuery(
                'INSERT INTO plans (user_id, title, notes, due_date, status, created_at) VALUES (:uid, :title, :notes, :due_date, :status, NOW())',
                [':uid' => $user['id'], ':title' => $title, ':notes' => $notes, ':due_date' => $dueDate ?: null, ':status' => $status]
            );
            $planMessage = 'Rencana berhasil ditambahkan.';
        }
    }
}

$planQuery = 'SELECT * FROM plans WHERE user_id = :uid';
$planParams = [':uid' => $user['id']];
if ($statusFilter !== 'all') {
    $planQuery .= ' AND status = :status';
    $planParams[':status'] = $statusFilter;
}
if ($searchTerm !== '') {
    $planQuery .= ' AND (title LIKE :search OR notes LIKE :search)';
    $planParams[':search'] = '%' . $searchTerm . '%';
}
$planQuery .= ' ORDER BY due_date ASC, created_at DESC';
$plans = fetchAll($planQuery, $planParams);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans</title>
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
                <a class="menu-item" href="board.php">Board</a>
                <a class="menu-item active" href="plans.php">Plans</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Plans</h2>
                    <p>Catat rencana dan target pekerjaan Anda.</p>
                </div>
            </header>

            <?php if ($planMessage): ?>
                <div class="alert success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($planMessage); ?></div>
            <?php endif; ?>

            <div class="toolbar-row">
                <div class="status-tabs">
                    <a href="plans.php?status=all<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">Semua</a>
                    <a href="plans.php?status=Planned<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'Planned' ? 'active' : ''; ?>">Planned</a>
                    <a href="plans.php?status=In Progress<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'In Progress' ? 'active' : ''; ?>">In Progress</a>
                    <a href="plans.php?status=Completed<?php echo $searchTerm !== '' ? '&search=' . urlencode($searchTerm) : ''; ?>" class="status-tab <?php echo $statusFilter === 'Completed' ? 'active' : ''; ?>">Completed</a>
                </div>

                <form class="search-box" method="get">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Cari judul atau catatan...">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <button type="submit">Cari</button>
                </form>
            </div>

            <?php if ($detailPlan): ?>
                <section class="detail-panel">
                    <h4>Detail Rencana</h4>
                    <div class="detail-meta">
                        <div><strong>Judul:</strong> <?php echo htmlspecialchars($detailPlan['title']); ?></div>
                        <div><strong>Status:</strong> <?php echo htmlspecialchars($detailPlan['status']); ?></div>
                        <div><strong>Due Date:</strong> <?php echo htmlspecialchars($detailPlan['due_date'] ?: '-'); ?></div>
                        <div><strong>Dibuat:</strong> <?php echo htmlspecialchars($detailPlan['created_at']); ?></div>
                    </div>
                    <div class="detail-body"><?php echo nl2br(htmlspecialchars($detailPlan['notes'] ?: 'Tidak ada catatan tambahan.')); ?></div>
                    <div style="margin-top: 16px;">
                        <a class="button-link secondary" href="plans.php?status=<?php echo urlencode($statusFilter); ?>">Kembali</a>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel">
                <h3><?php echo $editPlan ? 'Edit Rencana' : 'Tambah Rencana'; ?></h3>
                <form method="post" class="form-grid">
                    <?php if ($editPlan): ?>
                        <input type="hidden" name="plan_id" value="<?php echo (int)$editPlan['id']; ?>">
                    <?php endif; ?>
                    <div>
                        <label>Judul</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($editPlan['title'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['Planned', 'In Progress', 'Completed'] as $statusOption): ?>
                                <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo (($editPlan['status'] ?? 'Planned') === $statusOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Tanggal Target</label>
                        <input type="date" name="due_date" value="<?php echo htmlspecialchars($editPlan['due_date'] ?? ''); ?>">
                    </div>
                    <div class="full-width">
                        <label>Catatan</label>
                        <textarea name="notes" rows="4"><?php echo htmlspecialchars($editPlan['notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="full-width">
                        <button type="submit" class="btn-primary"><?php echo $editPlan ? 'Update Rencana' : 'Simpan Rencana'; ?></button>
                        <?php if ($editPlan): ?>
                            <a class="button-link secondary" href="plans.php">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="panel table-card">
                <h3>Daftar Rencana</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($plans)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Belum ada rencana.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($plans as $plan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($plan['title']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['status']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['due_date']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['notes']); ?></td>
                                        <td>
                                            <div class="action-row">
                                                <a class="button-link" href="plans.php?status=<?php echo urlencode($statusFilter); ?>&detail=<?php echo (int)$plan['id']; ?>">Lihat Detail</a>
                                                <a class="button-link" href="plans.php?edit=<?php echo (int)$plan['id']; ?>">Edit</a>
                                                <a class="button-link danger" href="plans.php?delete=<?php echo (int)$plan['id']; ?>" onclick="return confirm('Hapus rencana ini?');">Hapus</a>
                                            </div>
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
