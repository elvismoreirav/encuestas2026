CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'editor', 'analyst') NOT NULL DEFAULT 'super_admin',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS surveys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('draft', 'scheduled', 'active', 'closed', 'archived') NOT NULL DEFAULT 'draft',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    intro_title VARCHAR(180) NULL,
    intro_text TEXT NULL,
    thank_you_text TEXT NULL,
    settings_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_surveys_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_surveys_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_user_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    survey_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_survey_assignment (user_id, survey_id),
    KEY idx_assignments_survey (survey_id),
    KEY idx_assignments_assigned_by (assigned_by),
    CONSTRAINT fk_assignments_user FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    settings_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sections_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NOT NULL,
    code VARCHAR(60) NOT NULL,
    prompt TEXT NOT NULL,
    help_text TEXT NULL,
    question_type ENUM('text', 'textarea', 'single_choice', 'multiple_choice', 'rating', 'matrix') NOT NULL DEFAULT 'single_choice',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    placeholder VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 1,
    visibility_rules_json LONGTEXT NULL,
    validation_rules_json LONGTEXT NULL,
    settings_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_survey_question_code (survey_id, code),
    CONSTRAINT fk_questions_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    CONSTRAINT fk_questions_section FOREIGN KEY (section_id) REFERENCES survey_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_question_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    option_code VARCHAR(80) NOT NULL,
    option_label VARCHAR(255) NOT NULL,
    option_value VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 1,
    is_other_option TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_question_option_code (question_id, option_code),
    CONSTRAINT fk_question_options_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    response_uuid CHAR(36) NOT NULL UNIQUE,
    status ENUM('completed') NOT NULL DEFAULT 'completed',
    metadata_json LONGTEXT NULL,
    started_at DATETIME NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    session_token VARCHAR(64) NULL,
    device_type ENUM('desktop', 'mobile', 'tablet', 'bot', 'unknown') NOT NULL DEFAULT 'unknown',
    device_os VARCHAR(80) NULL,
    browser VARCHAR(80) NULL,
    screen_resolution VARCHAR(40) NULL,
    locale VARCHAR(20) NULL,
    referrer VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_survey_submitted (survey_id, submitted_at),
    KEY idx_response_session (session_token),
    CONSTRAINT fk_responses_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    response_id BIGINT UNSIGNED NULL,
    session_token VARCHAR(64) NULL,
    event_type ENUM('view', 'submit') NOT NULL DEFAULT 'view',
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(64) NULL,
    forwarded_ip VARCHAR(255) NULL,
    user_agent VARCHAR(255) NULL,
    device_type ENUM('desktop', 'mobile', 'tablet', 'bot', 'unknown') NOT NULL DEFAULT 'unknown',
    device_os VARCHAR(80) NULL,
    browser VARCHAR(80) NULL,
    screen_resolution VARCHAR(40) NULL,
    locale VARCHAR(20) NULL,
    referrer VARCHAR(255) NULL,
    metadata_json LONGTEXT NULL,
    KEY idx_access_logs_survey_date (survey_id, occurred_at),
    KEY idx_access_logs_response (response_id),
    KEY idx_access_logs_session (session_token),
    CONSTRAINT fk_access_logs_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    CONSTRAINT fk_access_logs_response FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS response_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id BIGINT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    question_code VARCHAR(60) NOT NULL,
    question_prompt TEXT NOT NULL,
    answer_type ENUM('text', 'textarea', 'single_choice', 'multiple_choice', 'rating', 'matrix') NOT NULL,
    answer_text LONGTEXT NULL,
    answer_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_response_question (response_id, question_id),
    KEY idx_question_code (question_code),
    CONSTRAINT fk_answers_response FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS response_answer_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_answer_id BIGINT UNSIGNED NOT NULL,
    option_code VARCHAR(80) NOT NULL,
    option_label VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_option_code (option_code),
    CONSTRAINT fk_answer_options_answer FOREIGN KEY (response_answer_id) REFERENCES response_answers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
