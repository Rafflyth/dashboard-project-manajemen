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

$messageStatus = '';
$editingMessage = null;
$detailMessageId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
$detailMessage = null;
$editMessageId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editMessageId) {
    $editingMessage = fetchOne('SELECT * FROM messages WHERE id = :id AND sender_user_id = :uid', [':id' => $editMessageId, ':uid' => $user['id']]);
}
if ($detailMessageId) {
    $detailMessage = fetchOne(
        'SELECT m.*, s.name AS sender_name, r.name AS receiver_name FROM messages m JOIN users s ON s.id = m.sender_user_id LEFT JOIN users r ON r.id = m.receiver_user_id WHERE m.id = :id AND (m.sender_user_id = :uid OR m.receiver_user_id = :uid)',
        [':id' => $detailMessageId, ':uid' => $user['id']]
    );
}

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    executeQuery('DELETE FROM messages WHERE id = :id AND sender_user_id = :uid', [':id' => $deleteId, ':uid' => $user['id']]);
    header('Location: messages.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $messageText = trim($_POST['message'] ?? '');
    $receiverId = (int)($_POST['receiver_user_id'] ?? 0);

    if ($subject && $messageText && $receiverId) {
        if ($messageId) {
            executeQuery(
                'UPDATE messages SET receiver_user_id = :receiver, subject = :subject, message = :message WHERE id = :id AND sender_user_id = :uid',
                [':receiver' => $receiverId, ':subject' => $subject, ':message' => $messageText, ':id' => $messageId, ':uid' => $user['id']]
            );
            $messageStatus = 'Pesan berhasil diperbarui.';
        } else {
            executeQuery(
                'INSERT INTO messages (sender_user_id, receiver_user_id, subject, message, created_at) VALUES (:sender, :receiver, :subject, :message, NOW())',
                [':sender' => $user['id'], ':receiver' => $receiverId, ':subject' => $subject, ':message' => $messageText]
            );
            $messageStatus = 'Pesan berhasil dikirim.';
        }
        if (!$messageId) {
            header('Location: messages.php');
            exit;
        }
    }
}

$users = fetchAll('SELECT id, name FROM users WHERE id != :uid ORDER BY name ASC', [':uid' => $user['id']]);
$messages = fetchAll(
    'SELECT m.*, s.name AS sender_name, r.name AS receiver_name FROM messages m JOIN users s ON s.id = m.sender_user_id LEFT JOIN users r ON r.id = m.receiver_user_id WHERE m.sender_user_id = :uid OR m.receiver_user_id = :uid ORDER BY m.created_at DESC',
    [':uid' => $user['id']]
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
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
                <a class="menu-item active" href="messages.php">Messages</a>
                <a class="menu-item" href="board.php">Board</a>
                <a class="menu-item" href="plans.php">Plans</a>
                <a class="menu-item" href="logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="page-title">
                    <h2>Messages</h2>
                    <p>Kirim dan lihat percakapan tim.</p>
                </div>
            </header>

            <?php if ($messageStatus): ?>
                <div class="alert success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($messageStatus); ?></div>
            <?php endif; ?>

            <?php if ($detailMessage): ?>
                <section class="detail-panel">
                    <h4>Lihat Detail Pesan</h4>
                    <div class="detail-meta">
                        <div><strong>Subjek:</strong> <?php echo htmlspecialchars($detailMessage['subject']); ?></div>
                        <div><strong>Pengirim:</strong> <?php echo htmlspecialchars($detailMessage['sender_name']); ?></div>
                        <div><strong>Penerima:</strong> <?php echo htmlspecialchars($detailMessage['receiver_name'] ?? 'Semua'); ?></div>
                        <div><strong>Waktu:</strong> <?php echo htmlspecialchars($detailMessage['created_at']); ?></div>
                    </div>
                    <div class="detail-body"><?php echo nl2br(htmlspecialchars($detailMessage['message'])); ?></div>
                    <div style="margin-top: 16px;">
                        <a class="button-link secondary" href="messages.php">Kembali</a>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel">
                <h3><?php echo $editingMessage ? 'Edit Pesan' : 'Kirim Pesan'; ?></h3>
                <form method="post" class="form-grid">
                    <?php if ($editingMessage): ?>
                        <input type="hidden" name="message_id" value="<?php echo (int)$editingMessage['id']; ?>">
                    <?php endif; ?>
                    <div>
                        <label>Tujuan</label>
                        <select name="receiver_user_id" required>
                            <option value="">Pilih penerima</option>
                            <?php foreach ($users as $person): ?>
                                <option value="<?php echo (int)$person['id']; ?>" <?php echo (($editingMessage['receiver_user_id'] ?? 0) == $person['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($person['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Subjek</label>
                        <input type="text" name="subject" value="<?php echo htmlspecialchars($editingMessage['subject'] ?? ''); ?>" required>
                    </div>
                    <div class="full-width">
                        <label>Pesan</label>
                        <textarea name="message" rows="4" required><?php echo htmlspecialchars($editingMessage['message'] ?? ''); ?></textarea>
                    </div>
                    <div class="full-width">
                        <button type="submit" class="btn-primary"><?php echo $editingMessage ? 'Update Pesan' : 'Kirim'; ?></button>
                        <?php if ($editingMessage): ?>
                            <a class="button-link secondary" href="messages.php">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="panel table-card">
                <h3>Riwayat Pesan</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Subjek</th>
                                <th>Pengirim</th>
                                <th>Penerima</th>
                                <th>Pesan</th>
                                <th>Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Belum ada pesan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($msg['sender_name']); ?></td>
                                        <td><?php echo htmlspecialchars($msg['receiver_name'] ?? 'Semua'); ?></td>
                                        <td><?php echo htmlspecialchars($msg['message']); ?></td>
                                        <td><?php echo htmlspecialchars($msg['created_at']); ?></td>
                                        <td>
                                            <div class="action-row">
                                                <a class="button-link" href="messages.php?detail=<?php echo (int)$msg['id']; ?>">Lihat Detail</a>
                                                <?php if ((int)$msg['sender_user_id'] === (int)$user['id']): ?>
                                                    <a class="button-link" href="messages.php?edit=<?php echo (int)$msg['id']; ?>">Edit</a>
                                                    <a class="button-link danger" href="messages.php?delete=<?php echo (int)$msg['id']; ?>" onclick="return confirm('Hapus pesan ini?');">Hapus</a>
                                                <?php else: ?>
                                                    <span class="badge completed">View</span>
                                                <?php endif; ?>
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
