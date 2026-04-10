SET @schema_name := DATABASE();

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = @schema_name
          AND table_name = 'survey_sections'
          AND index_name = 'idx_sections_survey_sort'
    ),
    'SELECT ''idx_sections_survey_sort already exists''',
    'ALTER TABLE survey_sections ADD KEY idx_sections_survey_sort (survey_id, sort_order, id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = @schema_name
          AND table_name = 'survey_questions'
          AND index_name = 'idx_questions_survey_sort'
    ),
    'SELECT ''idx_questions_survey_sort already exists''',
    'ALTER TABLE survey_questions ADD KEY idx_questions_survey_sort (survey_id, sort_order, id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = @schema_name
          AND table_name = 'survey_questions'
          AND index_name = 'idx_questions_section_sort'
    ),
    'SELECT ''idx_questions_section_sort already exists''',
    'ALTER TABLE survey_questions ADD KEY idx_questions_section_sort (section_id, sort_order, id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = @schema_name
          AND table_name = 'survey_question_options'
          AND index_name = 'idx_question_options_sort'
    ),
    'SELECT ''idx_question_options_sort already exists''',
    'ALTER TABLE survey_question_options ADD KEY idx_question_options_sort (question_id, sort_order, id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = @schema_name
          AND table_name = 'survey_responses'
          AND index_name = 'idx_responses_submitted_at'
    ),
    'SELECT ''idx_responses_submitted_at already exists''',
    'ALTER TABLE survey_responses ADD KEY idx_responses_submitted_at (submitted_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
