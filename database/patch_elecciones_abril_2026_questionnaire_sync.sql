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
          AND title = 'Imagen y liderazgo político'
        ORDER BY id
        LIMIT 1
    )
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
          AND title = 'Elecciones seccionales'
        ORDER BY id
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND sort_order = 4
        ORDER BY id
        LIMIT 1
    )
);

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
    ('leadership', 'Q23', 'Ud se considera:', 'single_choice', 1, 16, '[]', NULL),
    ('leadership', 'Q24', 'O se considera:', 'single_choice', 1, 17, '[]', NULL),
    ('sectional', 'Q22A', 'Ud tiene conocimiento del adelanto del proceso electoral para autoridades seccionales y del CPCCS?', 'single_choice', 1, 1, '[]', NULL),
    ('sectional', 'Q26', 'Si las elecciones a Prefecto/a fueran hoy, por quién votaría Papeleta (D)', 'single_choice', 1, 26, '[]', NULL),
    ('sectional', 'Q28_MANTA', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (A)', 'single_choice', 0, 28, '[{"question_code":"Q1","operator":"equals","value":"MANTA"}]', NULL),
    ('sectional', 'Q28_EL_CARMEN', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (A)', 'single_choice', 0, 28, '[{"question_code":"Q1","operator":"equals","value":"EL_CARMEN"}]', NULL),
    ('sectional', 'Q28_SUCRE', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (A)', 'single_choice', 0, 28, '[{"question_code":"Q1","operator":"equals","value":"SUCRE"}]', NULL),
    ('sectional', 'Q29', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (B)', 'single_choice', 0, 29, '[{"question_code":"Q1","operator":"equals","value":"PORTOVIEJO"}]', NULL),
    ('sectional', 'Q29_MANTA', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (B)', 'single_choice', 0, 29, '[{"question_code":"Q1","operator":"equals","value":"MANTA"}]', NULL),
    ('sectional', 'Q29_EL_CARMEN', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (B)', 'single_choice', 0, 29, '[{"question_code":"Q1","operator":"equals","value":"EL_CARMEN"}]', '{"report_scope":"special"}');

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
    question_type = VALUES(question_type),
    is_required = VALUES(is_required),
    sort_order = VALUES(sort_order),
    visibility_rules_json = VALUES(visibility_rules_json),
    validation_rules_json = VALUES(validation_rules_json),
    settings_json = VALUES(settings_json);

UPDATE survey_questions
SET sort_order = CASE code
    WHEN 'Q22A' THEN 1
    WHEN 'Q23_ALCALDE_APOYO' THEN 2
    WHEN 'Q24_ALCALDE_RC_PORTOVIEJO' THEN 3
    WHEN 'Q24_ALCALDE_RC_MANTA' THEN 4
    WHEN 'Q24_ALCALDE_RC_CHONE' THEN 5
    WHEN 'Q24_ALCALDE_RC_EL_CARMEN' THEN 6
    WHEN 'Q24_ALCALDE_RC_JIPIJAPA' THEN 7
    WHEN 'Q24_ALCALDE_RC_MONTECRISTI' THEN 8
    WHEN 'Q24_ALCALDE_RC_PEDERNALES' THEN 9
    WHEN 'Q24_ALCALDE_RC_SUCRE' THEN 10
    WHEN 'Q24_ALCALDE_RC_SANTA_ANA' THEN 11
    WHEN 'Q24_ALCALDE_RC_TOSAGUA' THEN 12
    WHEN 'Q24_ALCALDE_RC_JUNIN' THEN 13
    WHEN 'Q24_ALCALDE_RC_24_DE_MAYO' THEN 14
    WHEN 'Q24_ALCALDE_RC_PICHINCHA' THEN 15
    WHEN 'Q24_ALCALDE_RC_SAN_VICENTE' THEN 16
    WHEN 'Q24_ALCALDE_RC_PAJAN' THEN 17
    WHEN 'Q24_ALCALDE_RC_OLMEDO' THEN 18
    WHEN 'Q24_ALCALDE_RC_FLAVIO_ALFARO' THEN 19
    WHEN 'Q24_ALCALDE_RC_PUERTO_LOPEZ' THEN 20
    WHEN 'Q24_ALCALDE_RC_JARAMIJO' THEN 21
    WHEN 'Q24_ALCALDE_RC_ROCAFUERTE' THEN 22
    WHEN 'Q24_ALCALDE_RC_JAMA' THEN 23
    WHEN 'Q23_PREFECTO_APOYO' THEN 24
    WHEN 'Q24_PREFECTO_RC' THEN 25
    WHEN 'Q26' THEN 26
    WHEN 'Q27' THEN 27
    WHEN 'Q28' THEN 28
    WHEN 'Q28_MANTA' THEN 28
    WHEN 'Q28_EL_CARMEN' THEN 28
    WHEN 'Q28_SUCRE' THEN 28
    WHEN 'Q29' THEN 29
    WHEN 'Q29_MANTA' THEN 29
    WHEN 'Q29_EL_CARMEN' THEN 29
    WHEN 'Q30' THEN 30
    ELSE sort_order
END
WHERE @survey_id IS NOT NULL
  AND survey_id = @survey_id
  AND code IN (
      'Q22A',
      'Q23_ALCALDE_APOYO',
      'Q24_ALCALDE_RC_PORTOVIEJO',
      'Q24_ALCALDE_RC_MANTA',
      'Q24_ALCALDE_RC_CHONE',
      'Q24_ALCALDE_RC_EL_CARMEN',
      'Q24_ALCALDE_RC_JIPIJAPA',
      'Q24_ALCALDE_RC_MONTECRISTI',
      'Q24_ALCALDE_RC_PEDERNALES',
      'Q24_ALCALDE_RC_SUCRE',
      'Q24_ALCALDE_RC_SANTA_ANA',
      'Q24_ALCALDE_RC_TOSAGUA',
      'Q24_ALCALDE_RC_JUNIN',
      'Q24_ALCALDE_RC_24_DE_MAYO',
      'Q24_ALCALDE_RC_PICHINCHA',
      'Q24_ALCALDE_RC_SAN_VICENTE',
      'Q24_ALCALDE_RC_PAJAN',
      'Q24_ALCALDE_RC_OLMEDO',
      'Q24_ALCALDE_RC_FLAVIO_ALFARO',
      'Q24_ALCALDE_RC_PUERTO_LOPEZ',
      'Q24_ALCALDE_RC_JARAMIJO',
      'Q24_ALCALDE_RC_ROCAFUERTE',
      'Q24_ALCALDE_RC_JAMA',
      'Q23_PREFECTO_APOYO',
      'Q24_PREFECTO_RC',
      'Q26',
      'Q27',
      'Q28',
      'Q28_MANTA',
      'Q28_EL_CARMEN',
      'Q28_SUCRE',
      'Q29',
      'Q29_MANTA',
      'Q29_EL_CARMEN',
      'Q30'
  );

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
    ('Q22A', 'SI', 'Sí', 'Sí', 1, 0),
    ('Q22A', 'NO', 'No', 'No', 2, 0),
    ('Q23', 'CORREISTA', 'Correísta', 'Correísta', 1, 0),
    ('Q23', 'ALGO_CORREISTA', 'Algo correísta', 'Algo correísta', 2, 0),
    ('Q23', 'INDIFERENTE', 'Indiferente', 'Indiferente', 3, 0),
    ('Q23', 'ALGO_ANTICORREISTA', 'Algo anticorreísta', 'Algo anticorreísta', 4, 0),
    ('Q23', 'ANTICORREISTA', 'Anticorreísta', 'Anticorreísta', 5, 0),
    ('Q24', 'NOBOISTA', 'Noboísta', 'Noboísta', 1, 0),
    ('Q24', 'ALGO_NOBOISTA', 'Algo noboísta', 'Algo noboísta', 2, 0),
    ('Q24', 'INDIFERENTE', 'Indiferente', 'Indiferente', 3, 0),
    ('Q24', 'ALGO_ANTINOBOISTA', 'Algo antinoboísta', 'Algo antinoboísta', 4, 0),
    ('Q24', 'ANTINOBOISTA', 'Antinoboísta', 'Antinoboísta', 5, 0),
    ('Q26', 'CANDIDATO_RAFAEL_CORREA', 'Candidato Rafael Correa', 'Candidato Rafael Correa', 1, 0),
    ('Q26', 'AGUSTIN_CASANOVA', 'B. Agustín Casanova - Caminantes', 'B. Agustín Casanova - Caminantes', 2, 0),
    ('Q26', 'CARLOS_LUIS_ANDRADE', 'C. Carlos Luis Andrade - INDEPENDIENTE', 'C. Carlos Luis Andrade - INDEPENDIENTE', 3, 0),
    ('Q26', 'LEONARDO_RODRIGUEZ', 'D. Leonardo Rodriguez', 'D. Leonardo Rodriguez', 4, 0),
    ('Q26', 'MARIO_DEGENNA_FERNANDEZ', 'Mario DeGenna Fernandez - ADN', 'Mario DeGenna Fernandez - ADN', 5, 0),
    ('Q26', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 6, 0),
    ('Q26', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 7, 0),
    ('Q28_MANTA', 'JAIME_ESTRADA_BONILLA', 'Jaime Estrada Bonilla - RC', 'Jaime Estrada Bonilla - RC', 1, 0),
    ('Q28_MANTA', 'MARIA_BEATRIZ_SANTOS', 'Maria Beatriz Santos - Caminantes', 'Maria Beatriz Santos - Caminantes', 2, 0),
    ('Q28_MANTA', 'DIEGO_FRANCO', 'Diego Franco - ADN', 'Diego Franco - ADN', 3, 0),
    ('Q28_MANTA', 'JORGE_CEVALLOS', 'Jorge Cevallos - AVANZA', 'Jorge Cevallos - AVANZA', 4, 0),
    ('Q28_MANTA', 'MARCIANA_VALDIVIEZO', 'Marciana Valdiviezo', 'Marciana Valdiviezo', 5, 0),
    ('Q28_MANTA', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 6, 0),
    ('Q28_MANTA', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 7, 0),
    ('Q28_EL_CARMEN', 'MARIA_FERNANDA_FERRIN', 'Maria Fernanda Ferrin - RC', 'Maria Fernanda Ferrin - RC', 1, 0),
    ('Q28_EL_CARMEN', 'FRANKLIN_ESPINOZA', 'Franklin Espinoza - Caminantes', 'Franklin Espinoza - Caminantes', 2, 0),
    ('Q28_EL_CARMEN', 'URBANO_VERA', 'Urbano Vera - ADN', 'Urbano Vera - ADN', 3, 0),
    ('Q28_EL_CARMEN', 'RODRIGO_MENA', 'Rodrigo Mena - AVANZA', 'Rodrigo Mena - AVANZA', 4, 0),
    ('Q28_EL_CARMEN', 'MAYRA_CRUZ', 'Mayra Cruz - CONSTRUYE', 'Mayra Cruz - CONSTRUYE', 5, 0),
    ('Q28_EL_CARMEN', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 6, 0),
    ('Q28_EL_CARMEN', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 7, 0),
    ('Q28_SUCRE', 'FERNANDO_BARREIRO', 'Fernando Barreiro - RC', 'Fernando Barreiro - RC', 1, 0),
    ('Q28_SUCRE', 'PABEL_CANTOS', 'Pabel Cantos - Caminantes', 'Pabel Cantos - Caminantes', 2, 0),
    ('Q28_SUCRE', 'JOSE_GABRIEL_MENDOZA', 'Jose Gabriel Mendoza - ADN', 'Jose Gabriel Mendoza - ADN', 3, 0),
    ('Q28_SUCRE', 'INGRID_ZAMBRANO', 'Ingrid Zambrano - AVANZA', 'Ingrid Zambrano - AVANZA', 4, 0),
    ('Q28_SUCRE', 'MANUEL_GILCES', 'Manuel Gilces', 'Manuel Gilces', 5, 0),
    ('Q28_SUCRE', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 6, 0),
    ('Q28_SUCRE', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 7, 0),
    ('Q29', 'CANDIDATO_RC', 'Candidato RC', 'Candidato RC', 1, 0),
    ('Q29', 'JAVIER_PINCAY', 'Javier Pincay - ADN', 'Javier Pincay - ADN', 2, 0),
    ('Q29', 'LEONARDO_ORLANDO', 'C. Leonardo Orlando - INDEPENDIENTE', 'C. Leonardo Orlando - INDEPENDIENTE', 3, 0),
    ('Q29', 'LEONEL_MUNOZ', 'Leonel Muñoz', 'Leonel Muñoz', 4, 0),
    ('Q29', 'VALENTINA_CENTENO', 'Valentina Centeno - ADN', 'Valentina Centeno - ADN', 5, 0),
    ('Q29', 'MARIA_JOSE_FERNANDEZ', 'D. María José Fernandez - CAMINANTES', 'D. María José Fernandez - CAMINANTES', 6, 0),
    ('Q29', 'OTRO', 'Otro, alguien nuevo', 'Otro, alguien nuevo', 7, 0),
    ('Q29', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 8, 0),
    ('Q29', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 9, 0),
    ('Q29_MANTA', 'JAIME_ESTRADA_BONILLA', 'Jaime Estrada Bonilla - RC', 'Jaime Estrada Bonilla - RC', 1, 0),
    ('Q29_MANTA', 'MARIA_BEATRIZ_SANTOS', 'Maria Beatriz Santos - Caminantes', 'Maria Beatriz Santos - Caminantes', 2, 0),
    ('Q29_MANTA', 'MARIO_DEGENNA_FERNANDEZ', 'Mario DeGenna Fernandez - ADN', 'Mario DeGenna Fernandez - ADN', 3, 0),
    ('Q29_MANTA', 'JORGE_CEVALLOS', 'Jorge Cevallos - AVANZA', 'Jorge Cevallos - AVANZA', 4, 0),
    ('Q29_MANTA', 'MARCIANA_VALDIVIEZO', 'Marciana Valdiviezo', 'Marciana Valdiviezo', 5, 0),
    ('Q29_MANTA', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 6, 0),
    ('Q29_MANTA', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 7, 0),
    ('Q29_EL_CARMEN', 'CESAR_DELGADO', 'Cesar Delgado - RC', 'Cesar Delgado - RC', 1, 0),
    ('Q29_EL_CARMEN', 'ANGELA_PLUA', 'Angela Plua - ADN', 'Angela Plua - ADN', 2, 0),
    ('Q29_EL_CARMEN', 'ALDO_INTRIAGO', 'Aldo Intriago - AVANZA', 'Aldo Intriago - AVANZA', 3, 0),
    ('Q29_EL_CARMEN', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 4, 0),
    ('Q29_EL_CARMEN', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 5, 0),
    ('Q30', 'GABRIELA_MOLINA', 'Gabriela Molina - RC', 'Gabriela Molina - RC', 1, 0),
    ('Q30', 'JAVIER_PINCAY', 'Javier Pincay - AVANZA', 'Javier Pincay - AVANZA', 2, 0),
    ('Q30', 'LEONEL_MUNOZ', 'Leonel Muñoz', 'Leonel Muñoz', 3, 0),
    ('Q30', 'VALENTINA_CENTENO', 'Valentina Centeno - ADN', 'Valentina Centeno - ADN', 4, 0),
    ('Q30', 'MARIA_JOSE_FERNANDEZ', 'D. María José Fernandez - CAMINANTES', 'D. María José Fernandez - CAMINANTES', 5, 0),
    ('Q30', 'OTRO', 'Otro, alguien nuevo', 'Otro, alguien nuevo', 6, 0),
    ('Q30', 'NO_SABE', 'E. No sabe, no ha decidido', 'E. No sabe, no ha decidido', 7, 0),
    ('Q30', 'NULO', 'F. Nulo/Ninguno', 'F. Nulo/Ninguno', 8, 0);

DELETE o
FROM survey_question_options o
INNER JOIN survey_questions q ON q.id = o.question_id
INNER JOIN (
    SELECT DISTINCT question_code
    FROM tmp_option_updates
) t ON t.question_code = q.code
WHERE @survey_id IS NOT NULL
  AND q.survey_id = @survey_id;

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
