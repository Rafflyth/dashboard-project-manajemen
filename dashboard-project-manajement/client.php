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
$editClient = null;
$editClientId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($editClientId) {
    $editClient = fetchOne('SELECT * FROM clients WHERE id = :id', [':id' => $editClientId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name && $email) {
        if ($clientId) {
            executeQuery(
                'UPDATE clients SET name = :name, email = :email WHERE id = :id',
                [':name' => $name, ':email' => $email, ':id' => $clientId]
            );
            logActivity($user['id'], 'clients', 'update', 'Client updated: ' . $name . ' (' . $email . ')');
            $message = 'Client berhasil diperbarui.';
        } else {
            executeQuery('INSERT INTO clients (name, email, created_at) VALUES (:name, :email, NOW())', [
                ':name' => $name,
                ':email' => $email,
            ]);
            logActivity($user['id'], 'clients', 'create', 'Client created: ' . $name . ' (' . $email . ')');
            $message = 'Client berhasil ditambahkan.';
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $clientInfo = fetchOne('SELECT name, email FROM clients WHERE id = :id', [':id' => $id]);
    if ($clientInfo) {
        logActivity($user['id'], 'clients', 'delete', 'Client deleted: ' . $clientInfo['name'] . ' (' . $clientInfo['email'] . ')');
    }
    executeQuery('DELETE FROM clients WHERE id = :id', [':id' => $id]);
    header('Location: client.php');
    exit;
}

$clients = fetchAll('SELECT * FROM clients ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management</title>
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
                <a class="menu-item active" href="client.php">Clients</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Client Management</h2>
                    <p>Kelola daftar klien proyek.</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <section class="panel">
                <h3><?php echo $editClient ? 'Edit Client' : 'Tambah Client Baru'; ?></h3>
                <form method="post" class="form-grid">
                    <?php if ($editClient): ?>
                        <input type="hidden" name="client_id" value="<?php echo (int)$editClient['id']; ?>">
                    <?php endif; ?>
                    <div>
                        <label>Nama Client</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($editClient['name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($editClient['email'] ?? ''); ?>" required>
                    </div>
                    <div class="full-width">
                        <button type="submit" class="btn-primary"><?php echo $editClient ? 'Update Client' : 'Simpan Client'; ?></button>
                        <?php if ($editClient): ?>
                            <a class="button-link secondary" href="client.php">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="panel table-card">
                <h3>Daftar Clients</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo htmlspecialchars($client['created_at']); ?></td>
                                    <td>
                                        <div class="action-row">
                                            <a class="button-link" href="client.php?edit=<?php echo $client['id']; ?>">Edit</a>
                                            <a class="button-link danger" href="client.php?delete=<?php echo $client['id']; ?>" onclick="return confirm('Hapus client ini?');">Hapus</a>
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
