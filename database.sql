-- QUIZZY database schema
-- Run this in phpMyAdmin or: mysql -u root < database.sql

CREATE DATABASE IF NOT EXISTS quizzy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quizzy_db;

-- ============================================================
-- TEACHER ACCOUNTS
-- ============================================================
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- QUIZ DEFINITION DATA (persists indefinitely, owned by teacher)
-- ============================================================
CREATE TABLE quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    code VARCHAR(10) NOT NULL UNIQUE,
    status ENUM('draft','published','open','in_progress','completed','expired','archived') NOT NULL DEFAULT 'draft',
    default_time_limit INT NOT NULL DEFAULT 20,
    show_feedback_immediately TINYINT(1) NOT NULL DEFAULT 1,
    show_leaderboard_during TINYINT(1) NOT NULL DEFAULT 1,
    show_correct_answers_after TINYINT(1) NOT NULL DEFAULT 1,
    time_bonus_enabled TINYINT(1) NOT NULL DEFAULT 1,
    penalty_enabled TINYINT(1) NOT NULL DEFAULT 0,
    penalty_points INT NOT NULL DEFAULT 0,
    powerups_enabled VARCHAR(255) NOT NULL DEFAULT 'double_points,shield,time_boost,fifty_fifty,score_boost',
    current_question_index INT NOT NULL DEFAULT 0,
    question_started_at DATETIME NULL,
    session_status ENUM('idle','waiting','active','completed') NOT NULL DEFAULT 'idle',
    session_started_at DATETIME NULL,
    session_closed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_code (code),
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB;

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    type ENUM('mcq','truefalse') NOT NULL DEFAULT 'mcq',
    points INT NOT NULL DEFAULT 100,
    time_limit INT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz_order (quiz_id, order_index)
) ENGINE=InnoDB;

CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    answer_text VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    order_index INT NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question (question_id)
) ENGINE=InnoDB;

-- ============================================================
-- QUIZ SESSION DATA (temporary, auto-expires after 24h)
-- ============================================================
CREATE TABLE quiz_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    status ENUM('waiting','active','completed') NOT NULL DEFAULT 'waiting',
    started_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz (quiz_id),
    INDEX idx_closed (closed_at)
) ENGINE=InnoDB;

CREATE TABLE participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    quiz_id INT NOT NULL,
    name VARCHAR(60) NOT NULL,
    participant_token CHAR(64) NOT NULL,
    score INT NOT NULL DEFAULT 0,
    correct_count INT NOT NULL DEFAULT 0,
    incorrect_count INT NOT NULL DEFAULT 0,
    current_question_started_at DATETIME NULL,
    completed_at DATETIME NULL,
    ip_hash CHAR(64) NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES quiz_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_session_name (session_id, name),
    INDEX idx_token (participant_token),
    INDEX idx_session_score (session_id, score)
) ENGINE=InnoDB;

CREATE TABLE participant_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_id INT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    points_awarded INT NOT NULL DEFAULT 0,
    time_taken_ms INT NOT NULL DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_participant_question (participant_id, question_id)
) ENGINE=InnoDB;

CREATE TABLE participant_powerups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    powerup_code VARCHAR(30) NOT NULL,
    uses_remaining INT NOT NULL DEFAULT 1,
    active_for_next_question TINYINT(1) NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_participant_powerup (participant_id, powerup_code)
) ENGINE=InnoDB;

CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    teacher_id INT NULL,
    ip_hash CHAR(64) NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- DEMO SEED DATA
-- Demo teacher login -> username: demo_teacher / password: Demo@1234
-- ============================================================
INSERT INTO teachers (name, username, email, password_hash) VALUES
('Demo Teacher', 'demo_teacher', 'demo@quizzy.test', '$2b$12$fCNPYdmO1oXCIUQU8jtYsO2O/yBtNORRIw3qcwk3kywEfPXolO5Iq');

INSERT INTO quizzes (teacher_id, title, description, code, status, powerups_enabled)
VALUES (1, 'Philippine Geography Basics', 'A short demo quiz about the Philippines.', 'QZDEMO1', 'published', 'double_points,shield,time_boost,fifty_fifty,score_boost');

INSERT INTO questions (quiz_id, question_text, type, points, time_limit, order_index) VALUES
(1, 'What is the capital of the Philippines?', 'mcq', 100, 20, 1),
(1, 'The Philippines is an archipelago.', 'truefalse', 100, 15, 2),
(1, 'Which island group is Cebu part of?', 'mcq', 150, 20, 3);

INSERT INTO answers (question_id, answer_text, is_correct, order_index) VALUES
(1, 'Cebu', 0, 1),
(1, 'Manila', 1, 2),
(1, 'Davao', 0, 3),
(1, 'Baguio', 0, 4),
(2, 'True', 1, 1),
(2, 'False', 0, 2),
(3, 'Luzon', 0, 1),
(3, 'Visayas', 1, 2),
(3, 'Mindanao', 0, 3);
