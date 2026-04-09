SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

SET @survey_slug := 'elecciones-abril-2026';
SET @survey_id := (
    SELECT id
    FROM surveys
    WHERE slug = @survey_slug
    LIMIT 1
);

SET @leadership_section_id := COALESCE(
    (
        SELECT section_id
        FROM survey_questions
        WHERE survey_id = @survey_id
          AND code = 'Q19'
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND sort_order = 3
        ORDER BY id
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND title = 'Imagen y liderazgo político'
        ORDER BY id
        LIMIT 1
    )
);

INSERT INTO survey_sections (
    survey_id,
    title,
    description,
    sort_order,
    settings_json
)
SELECT
    @survey_id,
    'Elecciones seccionales',
    'Intención de voto para prefectura y alcaldía.',
    4,
    NULL
FROM DUAL
WHERE @survey_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM survey_sections
      WHERE survey_id = @survey_id
        AND sort_order = 4
  )
  AND NOT EXISTS (
      SELECT 1
      FROM survey_sections
      WHERE survey_id = @survey_id
        AND title = 'Elecciones seccionales'
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

UPDATE survey_sections
SET title = 'Elecciones seccionales',
    description = 'Intención de voto para prefectura y alcaldía.',
    sort_order = 4
WHERE id = @sectional_section_id;

DROP TEMPORARY TABLE IF EXISTS tmp_question_updates;
CREATE TEMPORARY TABLE tmp_question_updates (
    section_ref VARCHAR(20) NOT NULL,
    code VARCHAR(60) NOT NULL PRIMARY KEY,
    prompt TEXT NOT NULL,
    question_type VARCHAR(32) NOT NULL,
    is_required TINYINT(1) NOT NULL,
    sort_order INT NOT NULL,
    visibility_rules_json LONGTEXT NULL,
    settings_json LONGTEXT NULL
);

INSERT INTO tmp_question_updates (
    section_ref,
    code,
    prompt,
    question_type,
    is_required,
    sort_order,
    visibility_rules_json,
    settings_json
) VALUES
    ('leadership', 'Q20A', '¿Qué imagen tiene de Rafael Correa?', 'rating', 1, 11, '[]', NULL),
    ('leadership', 'Q21A', '¿Usted le cree a Rafael Correa?', 'single_choice', 1, 12, '[]', NULL),
    ('leadership', 'Q20', '¿Qué imagen tiene de Luisa González?', 'rating', 1, 13, '[]', NULL),
    ('leadership', 'Q21', '¿Usted le cree a Luisa González?', 'single_choice', 1, 14, '[]', NULL),
    ('leadership', 'Q22', 'Según usted, ¿cuál de las siguientes figuras representa mejor al correísmo en Manabí?', 'single_choice', 1, 15, '[]', NULL),
    ('leadership', 'Q23', 'Usted se considera:', 'single_choice', 1, 16, '[]', NULL),
    ('leadership', 'Q24', 'O se considera:', 'single_choice', 1, 17, '[]', NULL),
    ('leadership', 'Q25', '¿Con qué partido o movimiento político se identifica más?', 'single_choice', 1, 18, '[]', NULL),
    ('sectional', 'Q23_ALCALDE_APOYO', 'Ud votaría al candidato a alcalde apoyado por:', 'single_choice', 1, 1, '[]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_PORTOVIEJO', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Portoviejo?', 'single_choice', 1, 2, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"PORTOVIEJO"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_MANTA', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Manta?', 'single_choice', 1, 3, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"MANTA"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_CHONE', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Chone?', 'single_choice', 1, 4, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"CHONE"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_EL_CARMEN', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de El Carmen?', 'single_choice', 1, 5, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"EL_CARMEN"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_JIPIJAPA', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Jipijapa?', 'single_choice', 1, 6, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"JIPIJAPA"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_MONTECRISTI', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Montecristi?', 'single_choice', 1, 7, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"MONTECRISTI"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_PEDERNALES', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Pedernales?', 'single_choice', 1, 8, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"PEDERNALES"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_SUCRE', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Sucre?', 'single_choice', 1, 9, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"SUCRE"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_SANTA_ANA', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Santa Ana?', 'single_choice', 1, 10, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"SANTA_ANA"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_TOSAGUA', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Tosagua?', 'single_choice', 1, 11, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"TOSAGUA"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_JUNIN', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Junín?', 'single_choice', 1, 12, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"JUNIN"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_24_DE_MAYO', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de 24 de Mayo?', 'single_choice', 1, 13, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"24_DE_MAYO"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_PICHINCHA', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Pichincha?', 'single_choice', 1, 14, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"PICHINCHA"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_SAN_VICENTE', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de San Vicente?', 'single_choice', 1, 15, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"SAN_VICENTE"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_PAJAN', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Paján?', 'single_choice', 1, 16, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"PAJAN"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_OLMEDO', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Olmedo?', 'single_choice', 1, 17, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"OLMEDO"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_FLAVIO_ALFARO', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Flavio Alfaro?', 'single_choice', 1, 18, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"FLAVIO_ALFARO"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_PUERTO_LOPEZ', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Puerto López?', 'single_choice', 1, 19, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"PUERTO_LOPEZ"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_JARAMIJO', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Jaramijó?', 'single_choice', 1, 20, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"JARAMIJO"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_ROCAFUERTE', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Rocafuerte?', 'single_choice', 1, 21, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"ROCAFUERTE"}]', NULL),
    ('sectional', 'Q24_ALCALDE_RC_JAMA', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para la alcaldía de Jama?', 'single_choice', 1, 22, '[{"question_code":"Q23_ALCALDE_APOYO","operator":"equals","value":"RAFAEL_CORREA"},{"question_code":"Q1","operator":"equals","value":"JAMA"}]', NULL),
    ('sectional', 'Q23_PREFECTO_APOYO', 'Ud votaría al candidato a prefecto apoyado por:', 'single_choice', 1, 23, '[]', NULL),
    ('sectional', 'Q24_PREFECTO_RC', 'Si respondió Rafael Correa, ¿por cuál de los siguientes candidatos apoyados por Rafael Correa votaría para prefectura?', 'single_choice', 1, 24, '[{"question_code":"Q23_PREFECTO_APOYO","operator":"equals","value":"RAFAEL_CORREA"}]', NULL),
    ('sectional', 'Q26', 'Si las elecciones a Prefecto/a fueran hoy, ¿por quién votaría? Papeleta A', 'single_choice', 1, 25, '[]', NULL),
    ('sectional', 'Q27', 'Si las elecciones a Prefecto/a fueran hoy, ¿por quién votaría? Papeleta B', 'single_choice', 1, 26, '[]', NULL),
    ('sectional', 'Q28', 'Si las elecciones a Alcalde/sa fueran hoy, ¿por quién votaría? Papeleta A', 'single_choice', 0, 27, '[{"question_code":"Q1","operator":"equals","value":"PORTOVIEJO"}]', NULL),
    ('sectional', 'Q29', 'Si las elecciones a Alcalde/sa fueran hoy, ¿por quién votaría? Papeleta B', 'single_choice', 0, 28, '[{"question_code":"Q1","operator":"equals","value":"PORTOVIEJO"}]', NULL),
    ('sectional', 'Q30', 'Si las elecciones a Alcalde/sa fueran hoy, ¿por quién votaría? Papeleta C', 'single_choice', 0, 29, '[{"question_code":"Q1","operator":"equals","value":"PORTOVIEJO"}]', NULL);

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
)
SELECT
    @survey_id,
    CASE t.section_ref
        WHEN 'leadership' THEN @leadership_section_id
        ELSE @sectional_section_id
    END,
    t.code,
    t.prompt,
    NULL,
    t.question_type,
    t.is_required,
    NULL,
    t.sort_order,
    t.visibility_rules_json,
    NULL,
    t.settings_json
FROM tmp_question_updates t
WHERE @survey_id IS NOT NULL
  AND (
      (t.section_ref = 'leadership' AND @leadership_section_id IS NOT NULL)
      OR (t.section_ref = 'sectional' AND @sectional_section_id IS NOT NULL)
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

DROP TEMPORARY TABLE IF EXISTS tmp_option_updates;
CREATE TEMPORARY TABLE tmp_option_updates (
    question_code VARCHAR(60) NOT NULL,
    option_code VARCHAR(80) NOT NULL,
    option_label VARCHAR(255) NOT NULL,
    option_value VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL,
    is_other_option TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (question_code, option_code)
);

INSERT INTO tmp_option_updates (
    question_code,
    option_code,
    option_label,
    option_value,
    sort_order,
    is_other_option
) VALUES
    ('Q20A', 'MUY_BUENO', 'Muy bueno', 'Muy bueno', 1, 0),
    ('Q20A', 'BUENO', 'Bueno', 'Bueno', 2, 0),
    ('Q20A', 'INDIFERENTE', 'Indiferente', 'Indiferente', 3, 0),
    ('Q20A', 'MALO', 'Malo', 'Malo', 4, 0),
    ('Q20A', 'MUY_MALO', 'Muy malo', 'Muy malo', 5, 0),
    ('Q21A', 'SI', 'Sí', 'Sí', 1, 0),
    ('Q21A', 'NO', 'No', 'No', 2, 0),
    ('Q23_ALCALDE_APOYO', 'JAIME_ESTRADA', 'Jaime Estrada', 'Jaime Estrada', 1, 0),
    ('Q23_ALCALDE_APOYO', 'AGUSTIN_CASANOVA', 'Agustín Casanova', 'Agustín Casanova', 2, 0),
    ('Q23_ALCALDE_APOYO', 'LEONARDO_ORLANDO', 'Leonardo Orlando', 'Leonardo Orlando', 3, 0),
    ('Q23_ALCALDE_APOYO', 'MARIANO_ZAMBRANO', 'Mariano Zambrano', 'Mariano Zambrano', 4, 0),
    ('Q23_ALCALDE_APOYO', 'RAFAEL_CORREA', 'Rafael Correa', 'Rafael Correa', 5, 0),
    ('Q23_ALCALDE_APOYO', 'DANIEL_NOBOA', 'Daniel Noboa', 'Daniel Noboa', 6, 0),
    ('Q23_ALCALDE_APOYO', 'JAIME_NEBOT', 'Jaime Nebot', 'Jaime Nebot', 7, 0),
    ('Q24_ALCALDE_RC_PORTOVIEJO', 'OPT_1', 'Gabriela Molina', 'Gabriela Molina', 1, 0),
    ('Q24_ALCALDE_RC_PORTOVIEJO', 'OPT_2', 'Fernando Cedeño', 'Fernando Cedeño', 2, 0),
    ('Q24_ALCALDE_RC_PORTOVIEJO', 'OPT_3', 'Leonardo Orlando', 'Leonardo Orlando', 3, 0),
    ('Q24_ALCALDE_RC_PORTOVIEJO', 'OPT_4', 'José Vicente Santos', 'José Vicente Santos', 4, 0),
    ('Q24_ALCALDE_RC_PORTOVIEJO', 'OPT_5', 'Melissa Zambrano', 'Melissa Zambrano', 5, 0),
    ('Q24_ALCALDE_RC_MANTA', 'OPT_1', 'Jaime Estrada', 'Jaime Estrada', 1, 0),
    ('Q24_ALCALDE_RC_MANTA', 'OPT_2', 'Otro', 'Otro', 2, 1),
    ('Q24_ALCALDE_RC_CHONE', 'OPT_1', 'Raúl Andrade', 'Raúl Andrade', 1, 0),
    ('Q24_ALCALDE_RC_CHONE', 'OPT_2', 'Raisa Corral', 'Raisa Corral', 2, 0),
    ('Q24_ALCALDE_RC_EL_CARMEN', 'OPT_1', 'Cristóbal Vélez', 'Cristóbal Vélez', 1, 0),
    ('Q24_ALCALDE_RC_EL_CARMEN', 'OPT_2', 'Kelly Buenaventura', 'Kelly Buenaventura', 2, 0),
    ('Q24_ALCALDE_RC_EL_CARMEN', 'OPT_3', 'Dairy Moreira', 'Dairy Moreira', 3, 0),
    ('Q24_ALCALDE_RC_EL_CARMEN', 'OPT_4', 'María Fernanda Ferrín', 'María Fernanda Ferrín', 4, 0),
    ('Q24_ALCALDE_RC_JIPIJAPA', 'OPT_1', 'César Delgado', 'César Delgado', 1, 0),
    ('Q24_ALCALDE_RC_JIPIJAPA', 'OPT_2', 'Jonny Cañarte', 'Jonny Cañarte', 2, 0),
    ('Q24_ALCALDE_RC_MONTECRISTI', 'OPT_1', 'Adrián Pazmiño', 'Adrián Pazmiño', 1, 0),
    ('Q24_ALCALDE_RC_MONTECRISTI', 'OPT_2', 'Otro', 'Otro', 2, 1),
    ('Q24_ALCALDE_RC_PEDERNALES', 'OPT_1', 'Santos Cedeño P', 'Santos Cedeño P', 1, 0),
    ('Q24_ALCALDE_RC_PEDERNALES', 'OPT_2', 'Carlos Cedeño', 'Carlos Cedeño', 2, 0),
    ('Q24_ALCALDE_RC_PEDERNALES', 'OPT_3', 'María Emilia Arauz', 'María Emilia Arauz', 3, 0),
    ('Q24_ALCALDE_RC_SUCRE', 'OPT_1', 'Fernando Barreiro', 'Fernando Barreiro', 1, 0),
    ('Q24_ALCALDE_RC_SUCRE', 'OPT_2', 'Otro', 'Otro', 2, 1),
    ('Q24_ALCALDE_RC_SANTA_ANA', 'OPT_1', 'Nela Cedeño', 'Nela Cedeño', 1, 0),
    ('Q24_ALCALDE_RC_SANTA_ANA', 'OPT_2', 'Ingrid Bravo', 'Ingrid Bravo', 2, 0),
    ('Q24_ALCALDE_RC_SANTA_ANA', 'OPT_3', 'Roberth Cevallos', 'Roberth Cevallos', 3, 0),
    ('Q24_ALCALDE_RC_SANTA_ANA', 'OPT_4', 'Marianela Lobo', 'Marianela Lobo', 4, 0),
    ('Q24_ALCALDE_RC_TOSAGUA', 'OPT_1', 'Romel Cedeño', 'Romel Cedeño', 1, 0),
    ('Q24_ALCALDE_RC_TOSAGUA', 'OPT_2', 'Leonardo Sánchez', 'Leonardo Sánchez', 2, 0),
    ('Q24_ALCALDE_RC_JUNIN', 'OPT_1', 'Klever Solórzano', 'Klever Solórzano', 1, 0),
    ('Q24_ALCALDE_RC_JUNIN', 'OPT_2', 'Gema Mendoza', 'Gema Mendoza', 2, 0),
    ('Q24_ALCALDE_RC_24_DE_MAYO', 'OPT_1', 'Erite Alarcón', 'Erite Alarcón', 1, 0),
    ('Q24_ALCALDE_RC_24_DE_MAYO', 'OPT_2', 'Otro', 'Otro', 2, 1),
    ('Q24_ALCALDE_RC_PICHINCHA', 'OPT_1', 'Leodan Intriago', 'Leodan Intriago', 1, 0),
    ('Q24_ALCALDE_RC_PICHINCHA', 'OPT_2', 'Judy Mendoza', 'Judy Mendoza', 2, 0),
    ('Q24_ALCALDE_RC_PICHINCHA', 'OPT_3', 'Alejandro López', 'Alejandro López', 3, 0),
    ('Q24_ALCALDE_RC_SAN_VICENTE', 'OPT_1', 'Fabricio Lara', 'Fabricio Lara', 1, 0),
    ('Q24_ALCALDE_RC_SAN_VICENTE', 'OPT_2', 'César Mendoza', 'César Mendoza', 2, 0),
    ('Q24_ALCALDE_RC_PAJAN', 'OPT_1', 'Karen Marcillo', 'Karen Marcillo', 1, 0),
    ('Q24_ALCALDE_RC_PAJAN', 'OPT_2', 'Joao Acuña', 'Joao Acuña', 2, 0),
    ('Q24_ALCALDE_RC_OLMEDO', 'OPT_1', 'Lourdes Guerrero', 'Lourdes Guerrero', 1, 0),
    ('Q24_ALCALDE_RC_OLMEDO', 'OPT_2', 'Ingris Mendoza', 'Ingris Mendoza', 2, 0),
    ('Q24_ALCALDE_RC_FLAVIO_ALFARO', 'OPT_1', 'Fabián Rodríguez', 'Fabián Rodríguez', 1, 0),
    ('Q24_ALCALDE_RC_FLAVIO_ALFARO', 'OPT_2', 'Otro', 'Otro', 2, 1),
    ('Q24_ALCALDE_RC_PUERTO_LOPEZ', 'OPT_1', 'Miguel Macías', 'Miguel Macías', 1, 0),
    ('Q24_ALCALDE_RC_PUERTO_LOPEZ', 'OPT_2', 'Belén Villanueva', 'Belén Villanueva', 2, 0),
    ('Q24_ALCALDE_RC_JARAMIJO', 'OPT_1', 'Cristhina Calderón', 'Cristhina Calderón', 1, 0),
    ('Q24_ALCALDE_RC_JARAMIJO', 'OPT_2', 'Otro', 'Otro', 2, 1),
    ('Q24_ALCALDE_RC_ROCAFUERTE', 'OPT_1', 'Luis Castro', 'Luis Castro', 1, 0),
    ('Q24_ALCALDE_RC_ROCAFUERTE', 'OPT_2', 'Iván de la Torre', 'Iván de la Torre', 2, 0),
    ('Q24_ALCALDE_RC_JAMA', 'OPT_1', 'Ángel Rojas', 'Ángel Rojas', 1, 0),
    ('Q24_ALCALDE_RC_JAMA', 'OPT_2', 'Luisa Cuadrado', 'Luisa Cuadrado', 2, 0),
    ('Q24_ALCALDE_RC_JAMA', 'OPT_3', 'Mary Carmen Coveña', 'Mary Carmen Coveña', 3, 0),
    ('Q23_PREFECTO_APOYO', 'RAFAEL_CORREA', 'Rafael Correa', 'Rafael Correa', 1, 0),
    ('Q23_PREFECTO_APOYO', 'DANIEL_NOBOA', 'Daniel Noboa', 'Daniel Noboa', 2, 0),
    ('Q23_PREFECTO_APOYO', 'JAIME_NEBOT', 'Jaime Nebot', 'Jaime Nebot', 3, 0),
    ('Q23_PREFECTO_APOYO', 'LEONARDO_ORLANDO', 'Leonardo Orlando', 'Leonardo Orlando', 4, 0),
    ('Q24_PREFECTO_RC', 'OPT_1', 'Luisa González', 'Luisa González', 1, 0),
    ('Q24_PREFECTO_RC', 'OPT_2', 'Jaime Estrada Medranda', 'Jaime Estrada Medranda', 2, 0),
    ('Q24_PREFECTO_RC', 'OPT_3', 'Juan José Peña', 'Juan José Peña', 3, 0),
    ('Q24_PREFECTO_RC', 'OPT_4', 'José Antonio Orlando', 'José Antonio Orlando', 4, 0),
    ('Q24_PREFECTO_RC', 'OPT_5', 'Gabriela Molina', 'Gabriela Molina', 5, 0),
    ('Q26', 'CANDIDATO_RAFAEL_CORREA', 'Candidato Rafael Correa', 'Candidato Rafael Correa', 1, 0),
    ('Q26', 'AGUSTIN_CASANOVA', 'Agustín Casanova - Caminantes', 'Agustín Casanova - Caminantes', 2, 0),
    ('Q26', 'CARLOS_LUIS_ANDRADE', 'Carlos Luis Andrade - Independiente', 'Carlos Luis Andrade - Independiente', 3, 0),
    ('Q26', 'LEONARDO_RODRIGUEZ', 'Leonardo Rodríguez', 'Leonardo Rodríguez', 4, 0),
    ('Q26', 'MARIO_AMADO_ZAMBRANO', 'Mario Amado Zambrano - ADN', 'Mario Amado Zambrano - ADN', 5, 0),
    ('Q26', 'NO_SABE', 'No sabe / No ha decidido', 'No sabe / No ha decidido', 6, 0),
    ('Q26', 'NULO', 'Nulo / Ninguno', 'Nulo / Ninguno', 7, 0),
    ('Q27', 'JAIME_ESTRADA', 'Jaime Estrada - Si Podemos', 'Jaime Estrada - Si Podemos', 1, 0),
    ('Q27', 'AGUSTIN_CASANOVA', 'Agustín Casanova - Caminantes', 'Agustín Casanova - Caminantes', 2, 0),
    ('Q27', 'MARIO_AMADO_ZAMBRANO', 'Mario Amado Zambrano - ADN', 'Mario Amado Zambrano - ADN', 3, 0),
    ('Q27', 'LEONARDO_ORLANDO', 'Candidato Leonardo Orlando', 'Candidato Leonardo Orlando', 4, 0),
    ('Q27', 'LEONARDO_RODRIGUEZ', 'Leonardo Rodríguez', 'Leonardo Rodríguez', 5, 0),
    ('Q27', 'NO_SABE', 'No sabe / No ha decidido', 'No sabe / No ha decidido', 6, 0),
    ('Q27', 'NULO', 'Nulo / Ninguno', 'Nulo / Ninguno', 7, 0),
    ('Q28', 'LEONARDO_ORLANDO', 'Leonardo Orlando - Si Podemos / RC', 'Leonardo Orlando - Si Podemos / RC', 1, 0),
    ('Q28', 'JAVIER_PINCAY', 'Javier Pincay - Avanza-Machete', 'Javier Pincay - Avanza-Machete', 2, 0),
    ('Q28', 'MARIA_JOSE_FERNANDEZ', 'María José Fernández - Caminantes', 'María José Fernández - Caminantes', 3, 0),
    ('Q28', 'LEONEL_MUNOZ', 'Leonel Muñoz', 'Leonel Muñoz', 4, 0),
    ('Q28', 'OTRO', 'Otro / Alguien nuevo', 'Otro / Alguien nuevo', 5, 0),
    ('Q28', 'NO_SABE', 'No sabe / No ha decidido', 'No sabe / No ha decidido', 6, 0),
    ('Q28', 'NULO', 'Nulo / Ninguno', 'Nulo / Ninguno', 7, 0),
    ('Q29', 'CANDIDATO_RC', 'Candidato RC', 'Candidato RC', 1, 0),
    ('Q29', 'JAVIER_PINCAY', 'Javier Pincay - ADN', 'Javier Pincay - ADN', 2, 0),
    ('Q29', 'LEONARDO_ORLANDO', 'Leonardo Orlando - Independiente', 'Leonardo Orlando - Independiente', 3, 0),
    ('Q29', 'LEONEL_MUNOZ', 'Leonel Muñoz', 'Leonel Muñoz', 4, 0),
    ('Q29', 'MARIA_JOSE_FERNANDEZ', 'María José Fernández - ADN', 'María José Fernández - ADN', 5, 0),
    ('Q29', 'OTRO', 'Otro / Alguien nuevo', 'Otro / Alguien nuevo', 6, 0),
    ('Q29', 'NO_SABE', 'No sabe / No ha decidido', 'No sabe / No ha decidido', 7, 0),
    ('Q29', 'NULO', 'Nulo / Ninguno', 'Nulo / Ninguno', 8, 0),
    ('Q30', 'GABRIELA_MOLINA', 'Gabriela Molina - RC', 'Gabriela Molina - RC', 1, 0),
    ('Q30', 'JAVIER_PINCAY', 'Javier Pincay - ADN', 'Javier Pincay - ADN', 2, 0),
    ('Q30', 'LEONEL_MUNOZ', 'Leonel Muñoz', 'Leonel Muñoz', 3, 0),
    ('Q30', 'MARIA_JOSE_FERNANDEZ', 'María José Fernández - ADN', 'María José Fernández - ADN', 4, 0),
    ('Q30', 'OTRO', 'Otro / Alguien nuevo', 'Otro / Alguien nuevo', 5, 0),
    ('Q30', 'NO_SABE', 'No sabe / No ha decidido', 'No sabe / No ha decidido', 6, 0),
    ('Q30', 'NULO', 'Nulo / Ninguno', 'Nulo / Ninguno', 7, 0);

DELETE o
FROM survey_question_options o
INNER JOIN survey_questions q ON q.id = o.question_id
INNER JOIN (
    SELECT DISTINCT question_code
    FROM tmp_option_updates
) t ON t.question_code = q.code
WHERE q.survey_id = @survey_id;

INSERT INTO survey_question_options (
    question_id,
    option_code,
    option_label,
    option_value,
    sort_order,
    is_other_option
)
SELECT
    q.id,
    t.option_code,
    t.option_label,
    t.option_value,
    t.sort_order,
    t.is_other_option
FROM tmp_option_updates t
INNER JOIN survey_questions q
    ON q.survey_id = @survey_id
   AND q.code = t.question_code
WHERE @survey_id IS NOT NULL
ORDER BY q.id, t.sort_order;

DROP TEMPORARY TABLE IF EXISTS tmp_option_updates;
DROP TEMPORARY TABLE IF EXISTS tmp_question_updates;

COMMIT;
