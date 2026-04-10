-- =============================================================================
-- Patch: Vías del Bicentenario
-- Agrega dos preguntas:
--   Q31_VIAS_BICENTENARIO  → "¿Ud conoce acerca de las Vías del bicentenario?"
--                            Solo para: Portoviejo, Jipijapa, Montecristi,
--                            Santa Ana, Bolívar, Pichincha
--   Q32_VIAS_SATISFACCION  → "¿Cómo se siente con la ejecución de los trabajos
--                            de las vías del bicentenario?"
--                            Solo visible si respondió "SI" en Q31
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

SET @survey_slug := 'elecciones-abril-2026';
SET @survey_id := (
    SELECT id
    FROM surveys
    WHERE slug = @survey_slug
    LIMIT 1
);

SET @sectional_section_id := COALESCE(
    (
        SELECT section_id
        FROM survey_questions
        WHERE survey_id = @survey_id
          AND code = 'Q26'
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND sort_order = 4
        ORDER BY id
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND title = 'Elecciones seccionales'
        ORDER BY id
        LIMIT 1
    )
);

-- -------------------------------------------------------------------------
-- 1. Insertar preguntas
-- -------------------------------------------------------------------------

INSERT INTO survey_questions (
    survey_id,
    section_id,
    code,
    prompt,
    help_text,
    question_type,
    is_required,
    placeholder,
    sort_order,
    visibility_rules_json,
    validation_rules_json,
    settings_json
) VALUES
    (
        @survey_id,
        @sectional_section_id,
        'Q31_VIAS_BICENTENARIO',
        '¿Ud conoce acerca de las Vías del bicentenario?',
        NULL,
        'single_choice',
        1,
        NULL,
        30,
        '[{"question_code":"Q1","operator":"in","value":["PORTOVIEJO","JIPIJAPA","MONTECRISTI","SANTA_ANA","BOLIVAR","PICHINCHA"]}]',
        NULL,
        NULL
    ),
    (
        @survey_id,
        @sectional_section_id,
        'Q32_VIAS_SATISFACCION',
        '¿Cómo se siente con la ejecución de los trabajos de las vías del bicentenario?',
        NULL,
        'single_choice',
        1,
        NULL,
        31,
        '[{"question_code":"Q31_VIAS_BICENTENARIO","operator":"equals","value":"SI"},{"question_code":"Q1","operator":"in","value":["PORTOVIEJO","JIPIJAPA","MONTECRISTI","SANTA_ANA","BOLIVAR","PICHINCHA"]}]',
        NULL,
        NULL
    )
ON DUPLICATE KEY UPDATE
    section_id = VALUES(section_id),
    prompt = VALUES(prompt),
    help_text = VALUES(help_text),
    question_type = VALUES(question_type),
    is_required = VALUES(is_required),
    placeholder = VALUES(placeholder),
    sort_order = VALUES(sort_order),
    visibility_rules_json = VALUES(visibility_rules_json),
    validation_rules_json = VALUES(validation_rules_json),
    settings_json = VALUES(settings_json);

-- -------------------------------------------------------------------------
-- 2. Insertar opciones para Q31 (¿Conoce las Vías del bicentenario?)
-- -------------------------------------------------------------------------

INSERT INTO survey_question_options (question_id, code, label, value, sort_order, is_other_option)
SELECT q.id, v.option_code, v.option_label, v.option_value, v.sort_order, v.is_other
FROM survey_questions q
JOIN (
    SELECT 'Q31_VIAS_BICENTENARIO' AS qcode, 'SI' AS option_code, 'Sí' AS option_label, 'Sí' AS option_value, 1 AS sort_order, 0 AS is_other
    UNION ALL
    SELECT 'Q31_VIAS_BICENTENARIO', 'NO', 'No', 'No', 2, 0
) v ON v.qcode = q.code
WHERE q.survey_id = @survey_id
  AND q.code = 'Q31_VIAS_BICENTENARIO'
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    value = VALUES(value),
    sort_order = VALUES(sort_order),
    is_other_option = VALUES(is_other_option);

-- -------------------------------------------------------------------------
-- 3. Insertar opciones para Q32 (Satisfacción con la ejecución)
-- -------------------------------------------------------------------------

INSERT INTO survey_question_options (question_id, code, label, value, sort_order, is_other_option)
SELECT q.id, v.option_code, v.option_label, v.option_value, v.sort_order, v.is_other
FROM survey_questions q
JOIN (
    SELECT 'Q32_VIAS_SATISFACCION' AS qcode, 'MUY_SATISFECHO'   AS option_code, 'Muy satisfecho'   AS option_label, 'Muy satisfecho'   AS option_value, 1 AS sort_order, 0 AS is_other
    UNION ALL
    SELECT 'Q32_VIAS_SATISFACCION', 'POCO_SATISFECHO',  'Poco satisfecho',  'Poco satisfecho',  2, 0
    UNION ALL
    SELECT 'Q32_VIAS_SATISFACCION', 'INDIFERENTE',      'Indiferente',      'Indiferente',      3, 0
    UNION ALL
    SELECT 'Q32_VIAS_SATISFACCION', 'POCO_INSATISFECHO', 'Poco insatisfecho', 'Poco insatisfecho', 4, 0
) v ON v.qcode = q.code
WHERE q.survey_id = @survey_id
  AND q.code = 'Q32_VIAS_SATISFACCION'
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    value = VALUES(value),
    sort_order = VALUES(sort_order),
    is_other_option = VALUES(is_other_option);

COMMIT;
