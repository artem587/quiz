-- QuizSpace Live v5
-- Saved quiz and launch session are separate entities.
-- PIN/QR are stored only in live_sessions and are generated on launch.

CREATE TABLE IF NOT EXISTS live_quizzes (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id INT UNSIGNED NOT NULL,
 creator_id INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(course_id), INDEX(creator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_quiz_questions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 quiz_id INT UNSIGNED NOT NULL,
 prompt TEXT NOT NULL,
 image_url VARCHAR(1000) DEFAULT NULL,
 time_limit INT UNSIGNED NOT NULL DEFAULT 20,
 sort_order INT NOT NULL DEFAULT 0,
 INDEX(quiz_id,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_quiz_answers (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 question_id INT UNSIGNED NOT NULL,
 answer_text TEXT NOT NULL,
 is_correct TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 INDEX(question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_sessions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 quiz_id INT UNSIGNED NOT NULL,
 game_code VARCHAR(12) NOT NULL UNIQUE,
 status ENUM('lobby','running','finished') NOT NULL DEFAULT 'lobby',
 current_question INT NOT NULL DEFAULT -1,
 question_started_at TIMESTAMP(6) NULL DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(quiz_id), INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_session_players (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_id INT UNSIGNED NOT NULL,
 nickname VARCHAR(80) NOT NULL,
 avatar VARCHAR(20) NOT NULL DEFAULT '🦊',
 player_token CHAR(64) NOT NULL UNIQUE,
 score INT NOT NULL DEFAULT 0,
 joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_session_responses (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_id INT UNSIGNED NOT NULL,
 question_id INT UNSIGNED NOT NULL,
 player_id INT UNSIGNED NOT NULL,
 answer_id INT UNSIGNED NOT NULL,
 is_correct TINYINT(1) NOT NULL DEFAULT 0,
 points INT NOT NULL DEFAULT 0,
 responded_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY one_answer(session_id,question_id,player_id),
 INDEX(session_id,question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No PIN/QR migration is intentional: old live_games are legacy sessions.
-- The application keeps them untouched but does not show them as saved quizzes.
