-- QuizSpace Live migration
-- Можна виконати окремо в phpMyAdmin. Новий режим також створює ці таблиці автоматично при першому запуску.
CREATE TABLE IF NOT EXISTS live_games (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id INT UNSIGNED NOT NULL,
 creator_id INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 game_code VARCHAR(12) NOT NULL UNIQUE,
 status ENUM('lobby','running','finished') NOT NULL DEFAULT 'lobby',
 current_question INT NOT NULL DEFAULT -1,
 question_started_at TIMESTAMP(6) NULL DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(course_id), INDEX(creator_id), INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_game_questions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 game_id INT UNSIGNED NOT NULL,
 prompt TEXT NOT NULL,
 image_url VARCHAR(1000) DEFAULT NULL,
 time_limit INT UNSIGNED NOT NULL DEFAULT 20,
 sort_order INT NOT NULL DEFAULT 0,
 INDEX(game_id,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_game_answers (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 question_id INT UNSIGNED NOT NULL,
 answer_text TEXT NOT NULL,
 is_correct TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 INDEX(question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_game_players (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 game_id INT UNSIGNED NOT NULL,
 nickname VARCHAR(80) NOT NULL,
 avatar VARCHAR(20) NOT NULL DEFAULT '🦊',
 player_token CHAR(64) NOT NULL UNIQUE,
 score INT NOT NULL DEFAULT 0,
 joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_game_responses (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 game_id INT UNSIGNED NOT NULL,
 question_id INT UNSIGNED NOT NULL,
 player_id INT UNSIGNED NOT NULL,
 answer_id INT UNSIGNED NOT NULL,
 is_correct TINYINT(1) NOT NULL DEFAULT 0,
 points INT NOT NULL DEFAULT 0,
 responded_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY one_answer(game_id,question_id,player_id),
 INDEX(game_id,question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
