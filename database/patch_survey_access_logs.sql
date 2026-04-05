ALTER TABLE survey_responses
    ADD COLUMN session_token VARCHAR(64) NULL AFTER user_agent,
    ADD COLUMN device_type ENUM('desktop', 'mobile', 'tablet', 'bot', 'unknown') NOT NULL DEFAULT 'unknown' AFTER session_token,
    ADD COLUMN device_os VARCHAR(80) NULL AFTER device_type,
    ADD COLUMN browser VARCHAR(80) NULL AFTER device_os,
    ADD COLUMN screen_resolution VARCHAR(40) NULL AFTER browser,
    ADD COLUMN locale VARCHAR(20) NULL AFTER screen_resolution,
    ADD COLUMN referrer VARCHAR(255) NULL AFTER locale,
    ADD KEY idx_response_session (session_token);

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
