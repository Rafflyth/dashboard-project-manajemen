<?php
$config = require __DIR__ . '/config.php';

function normalizeUserRole($role)
{
    $role = strtolower(trim((string) $role));

    if ($role === 'pengguna' || $role === 'member') {
        return 'user';
    }

    return $role;
}

function isAdminRole($role)
{
    return normalizeUserRole($role) === 'admin';
}

function isUserRole($role)
{
    return normalizeUserRole($role) === 'user';
}

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        global $config;

        if (!empty($config['db_socket'])) {
            $dsn = sprintf(
                'mysql:dbname=%s;charset=%s;unix_socket=%s',
                $config['db_name'],
                $config['db_charset'],
                $config['db_socket']
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['db_host'],
                $config['db_name'],
                $config['db_charset']
            );
            if (!empty($config['db_port'])) {
                $dsn .= ';port=' . intval($config['db_port']);
            }
        }

        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        seedSampleData($pdo);
    }
    return $pdo;
}

function seedSampleData(PDO $pdo)
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_user_id INT NOT NULL,
            receiver_user_id INT NULL,
            subject VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            notes TEXT,
            due_date DATE NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Planned',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            module VARCHAR(100) NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        return;
    }

    try {
        $userCount = $pdo->query('SELECT COUNT(*) AS total FROM users')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$userCount || intval($userCount['total']) === 0) {
        $pdo->exec("INSERT INTO users (name, email, password, role) VALUES
            ('Admin', 'admin@example.com', MD5('admin123'), 'admin'),
            ('User', 'user@example.com', MD5('user123'), 'user')");
    }

    try {
        $clientCount = $pdo->query('SELECT COUNT(*) AS total FROM clients')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$clientCount || intval($clientCount['total']) === 0) {
        $pdo->exec("INSERT INTO clients (name, email) VALUES
            ('PT Serasi Digital', 'hello@serasidigital.com'),
            ('CV Maju Jaya', 'contact@majujaya.id')");
    }

    try {
        $projectCount = $pdo->query('SELECT COUNT(*) AS total FROM projects')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$projectCount || intval($projectCount['total']) === 0) {
        $pdo->exec("INSERT INTO projects (name, description, status) VALUES
            ('Client Website', 'Desain dan implementasi website klien.', 'Active'),
            ('Marketing Campaign', 'Kampanye digital dan promosi produk.', 'On Hold')");
    }

    try {
        $notificationCount = $pdo->query('SELECT COUNT(*) AS total FROM notifications')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$notificationCount || intval($notificationCount['total']) === 0) {
        $adminUser = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $regularUser = $pdo->query("SELECT id FROM users WHERE role = 'user' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if ($adminUser) {
            $pdo->exec("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES
                (" . intval($adminUser['id']) . ", 'Rekap harian', 'Ada 3 tugas yang menunggu review admin.', 0, NOW()),
                (" . intval($adminUser['id']) . ", 'Status proyek', 'Dua proyek membutuhkan update status hari ini.', 1, NOW())");
        }

        if ($regularUser) {
            $pdo->exec("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES
                (" . intval($regularUser['id']) . ", 'Tugas baru', 'Anda mendapat satu tugas baru dari proyek Client Website.', 0, NOW()),
                (" . intval($regularUser['id']) . ", 'Deadline', 'Jangan lupa cek deadline kampanye Instagram hari Jumat.', 0, NOW())");
        }
    }

    try {
        $messageCount = $pdo->query('SELECT COUNT(*) AS total FROM messages')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$messageCount || intval($messageCount['total']) === 0) {
        $adminUser = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $regularUser = $pdo->query("SELECT id FROM users WHERE role = 'user' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if ($adminUser && $regularUser) {
            $pdo->exec("INSERT INTO messages (sender_user_id, receiver_user_id, subject, message, created_at) VALUES
                (" . intval($adminUser['id']) . ", " . intval($regularUser['id']) . ", 'Review progres', 'Silakan kirim update progres tugas anda sebelum jam 17:00.', NOW()),
                (" . intval($regularUser['id']) . ", " . intval($adminUser['id']) . ", 'Catatan pekerjaan', 'Saya sudah menyelesaikan review UI dan menunggu konfirmasi.', NOW())");
        }
    }

    try {
        $planCount = $pdo->query('SELECT COUNT(*) AS total FROM plans')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$planCount || intval($planCount['total']) === 0) {
        $regularUser = $pdo->query("SELECT id FROM users WHERE role = 'user' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if ($regularUser) {
            $pdo->exec("INSERT INTO plans (user_id, title, notes, due_date, status, created_at) VALUES
                (" . intval($regularUser['id']) . ", 'Tinjau desain landing page', 'Verifikasi struktur hero, CTA, dan copywriting untuk desktop.', '2026-08-22', 'In Progress', NOW()),
                (" . intval($regularUser['id']) . ", 'Siapkan campaign brief', 'Kumpulkan materi visual, target audiens, dan anggaran promo.', '2026-08-30', 'Planned', NOW())");
        }
    }

    try {
        $activityCount = $pdo->query('SELECT COUNT(*) AS total FROM activity_logs')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$activityCount || intval($activityCount['total']) === 0) {
        $adminUser = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if ($adminUser) {
            $pdo->exec("INSERT INTO activity_logs (user_id, module, action, details, created_at) VALUES
                (" . intval($adminUser['id']) . ", 'dashboard', 'login', 'Admin login berhasil.', NOW()),
                (" . intval($adminUser['id']) . ", 'projects', 'create', 'Project Client Website dibuat.', NOW()),
                (" . intval($adminUser['id']) . ", 'tasks', 'create', 'Tugas review UI dibuat untuk user.', NOW())");
        }
    }

    try {
        $taskCount = $pdo->query('SELECT COUNT(*) AS total FROM tasks')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$taskCount || intval($taskCount['total']) === 0) {
        $assignee = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $assignee->execute([':email' => 'user@example.com']);
        $assigneeId = $assignee->fetch(PDO::FETCH_ASSOC);

        if (!$assigneeId) {
            $assignee = $pdo->query("SELECT id FROM users WHERE role = 'user' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $assigneeId = $assignee ?: null;
        }

        $project1 = $pdo->query('SELECT id FROM projects ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $project2 = $pdo->query('SELECT id FROM projects ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

        if ($assigneeId && $project1 && $project2) {
            $stmt = $pdo->prepare('INSERT INTO tasks (project_id, title, description, assigned_user_id, status, due_date, created_at) VALUES (:project_id, :title, :description, :assigned_user_id, :status, :due_date, NOW())');
            $stmt->execute([
                ':project_id' => $project1['id'],
                ':title' => 'Review tampilan UI',
                ':description' => 'Cek dan saran perbaikan tampilan dashboard.',
                ':assigned_user_id' => $assigneeId['id'],
                ':status' => 'Pending',
                ':due_date' => '2026-09-05',
            ]);
            $stmt->execute([
                ':project_id' => $project2['id'],
                ':title' => 'Pengaturan kampanye Instagram',
                ':description' => 'Buat konten dan jadwal posting.',
                ':assigned_user_id' => $assigneeId['id'],
                ':status' => 'In Progress',
                ':due_date' => '2026-09-10',
            ]);
        }
    }
}

function authenticateUser($email, $password)
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }

    $stored = $user['password'];
    if (function_exists('password_verify') && (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2'))) {
        $valid = password_verify($password, $stored);
    } elseif (ctype_xdigit($stored) && strlen($stored) === 32) {
        $valid = md5($password) === $stored;
    } else {
        $valid = $password === $stored;
    }

    return $valid ? $user : false;
}

function fetchAll($sql, $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchOne($sql, $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function logActivity($userId, $module, $action, $details = '')
{
    if (!$userId || !$module || !$action) {
        return false;
    }

    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, module, action, details, created_at) VALUES (:user_id, :module, :action, :details, NOW())');
    return $stmt->execute([
        ':user_id' => $userId,
        ':module' => $module,
        ':action' => $action,
        ':details' => $details,
    ]);
}

function executeQuery($sql, $params = [])
{
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}
