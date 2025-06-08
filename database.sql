-- Baza danych ZenDo
CREATE DATABASE IF NOT EXISTS zendo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zendo_db;

-- Tabela użytkowników
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela list zadań
CREATE TABLE task_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela zadań
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    deadline DATETIME NULL,
    completed BOOLEAN DEFAULT FALSE,
    list_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES task_lists(id) ON DELETE CASCADE
);

-- Tabela udostępniania list
CREATE TABLE list_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES task_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (list_id, user_id)
);

-- Indeksy dla optymalizacji
CREATE INDEX idx_tasks_list_id ON tasks(list_id);
CREATE INDEX idx_tasks_completed ON tasks(completed);
CREATE INDEX idx_tasks_deadline ON tasks(deadline);
CREATE INDEX idx_list_shares_user_id ON list_shares(user_id);
CREATE INDEX idx_list_shares_list_id ON list_shares(list_id);

-- Przykładowe dane testowe
INSERT INTO users (name, email, password) VALUES
('Diana Holubieva', 'diana@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- haslo123
('Michał Kościelniak', 'michal@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- haslo123
('Dawid Ćwiok', 'dawid@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- haslo123

INSERT INTO task_lists (name, user_id) VALUES
('Praca', 1),
('Dom', 1),
('Zakupy', 2),
('Projekt ZenDo', 1);

INSERT INTO tasks (title, description, priority, deadline, list_id, completed) VALUES
('Przygotować prezentację', 'Prezentacja o nowych funkcjach aplikacji ZenDo', 'high', '2025-06-05 10:00:00', 1, FALSE),
('Code review', 'Sprawdzić pull requesty zespołu', 'medium', '2025-06-02 16:00:00', 1, FALSE),
('Dokumentacja API', 'Napisać dokumentację dla REST API', 'medium', '2025-06-03 12:00:00', 4, FALSE),
('Zakupy spożywcze', 'Mleko, chleb, jajka, owoce', 'low', '2025-06-01 18:00:00', 3, TRUE),
('Umyć okna', 'Okna w salonie i kuchni', 'low', NULL, 2, FALSE),
('Testy jednostkowe', 'Napisać testy dla modułu zadań', 'high', '2025-06-04 14:00:00', 4, FALSE);

INSERT INTO list_shares (list_id, user_id) VALUES
(1, 2), -- Praca udostępniona Michałowi
(4, 2), -- Projekt ZenDo udostępniony Michałowi
(4, 3), -- Projekt ZenDo udostępniony Dawidowi
(3, 1); -- Zakupy udostępnione Dianie