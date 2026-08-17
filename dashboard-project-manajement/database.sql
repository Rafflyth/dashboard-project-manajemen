CREATE DATABASE IF NOT EXISTS prologs_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prologs_dashboard;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    assigned_user_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_user_id INT NOT NULL,
    receiver_user_id INT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    notes TEXT,
    due_date DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO users (name, email, password, role) VALUES
('Admin', 'admin@example.com', MD5('admin123'), 'admin'),
('User', 'user@example.com', MD5('user123'), 'user');

INSERT IGNORE INTO clients (name, email) VALUES
('PT Serasi Digital', 'hello@serasidigital.com'),
('CV Maju Jaya', 'contact@majujaya.id');

INSERT IGNORE INTO notifications (user_id, title, message, is_read) VALUES
((SELECT id FROM users WHERE email = 'admin@example.com'), 'Rekap harian', 'Ada 3 tugas yang menunggu review admin.', 0),
((SELECT id FROM users WHERE email = 'admin@example.com'), 'Status proyek', 'Dua proyek membutuhkan update status hari ini.', 1),
((SELECT id FROM users WHERE email = 'user@example.com'), 'Tugas baru', 'Anda mendapat satu tugas baru dari proyek Client Website.', 0),
((SELECT id FROM users WHERE email = 'user@example.com'), 'Deadline', 'Jangan lupa cek deadline kampanye Instagram hari Jumat.', 0);

INSERT IGNORE INTO messages (sender_user_id, receiver_user_id, subject, message) VALUES
((SELECT id FROM users WHERE email = 'admin@example.com'), (SELECT id FROM users WHERE email = 'user@example.com'), 'Review progres', 'Silakan kirim update progres tugas anda sebelum jam 17:00.'),
((SELECT id FROM users WHERE email = 'user@example.com'), (SELECT id FROM users WHERE email = 'admin@example.com'), 'Catatan pekerjaan', 'Saya sudah menyelesaikan review UI dan menunggu konfirmasi.');

INSERT IGNORE INTO plans (user_id, title, notes, due_date, status) VALUES
((SELECT id FROM users WHERE email = 'user@example.com'), 'Tinjau desain landing page', 'Verifikasi struktur hero, CTA, dan copywriting untuk desktop.', '2026-08-22', 'In Progress'),
((SELECT id FROM users WHERE email = 'user@example.com'), 'Siapkan campaign brief', 'Kumpulkan materi visual, target audiens, dan anggaran promo.', '2026-08-30', 'Planned');

INSERT IGNORE INTO projects (name, description, status) VALUES
('Client Website', 'Desain dan implementasi website klien.', 'Active'),
('Marketing Campaign', 'Kampanye digital dan promosi produk.', 'On Hold');

INSERT IGNORE INTO tasks (project_id, title, description, assigned_user_id, status, due_date) VALUES
(1, 'Review tampilan UI', 'Cek dan saran perbaikan tampilan dashboard.', 2, 'Pending', '2026-09-05'),
(2, 'Pengaturan kampanye Instagram', 'Buat konten dan jadwal posting.', 2, 'In Progress', '2026-09-10');
