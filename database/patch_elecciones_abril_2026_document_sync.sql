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

INSERT INTO survey_sections (
    survey_id,
    title,
    description,
    sort_order,
    settings_json
)
SELECT
    @survey_id,
    'Análisis de la Asamblea Nacional e instituciones',
    'Percepción de instituciones, autoridades locales y organizaciones políticas.',
    5,
    NULL
FROM DUAL
WHERE @survey_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM survey_sections
      WHERE survey_id = @survey_id
        AND sort_order = 5
  )
  AND NOT EXISTS (
      SELECT 1
      FROM survey_sections
      WHERE survey_id = @survey_id
        AND title IN ('Análisis de la Asamblea Nacional e instituciones', 'Instituciones e imagen pública')
  );

SET @institutions_section_id := COALESCE(
    (
        SELECT section_id
        FROM survey_questions
        WHERE survey_id = @survey_id
          AND code = 'Q31'
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND sort_order = 5
        ORDER BY id
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND title IN ('Análisis de la Asamblea Nacional e instituciones', 'Instituciones e imagen pública')
        ORDER BY id
        LIMIT 1
    )
);

UPDATE survey_sections
SET title = 'Análisis de la Asamblea Nacional e instituciones',
    description = 'Percepción de instituciones, autoridades locales y organizaciones políticas.',
    sort_order = 5
WHERE id = @institutions_section_id;

INSERT INTO survey_sections (
    survey_id,
    title,
    description,
    sort_order,
    settings_json
)
SELECT
    @survey_id,
    'Análisis de personajes y medios',
    'Matriz de conocimiento político y hábitos informativos.',
    6,
    NULL
FROM DUAL
WHERE @survey_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM survey_sections
      WHERE survey_id = @survey_id
        AND sort_order = 6
  )
  AND NOT EXISTS (
      SELECT 1
      FROM survey_sections
      WHERE survey_id = @survey_id
        AND title IN ('Análisis de personajes y medios', 'Análisis de personajes políticos')
  );

SET @media_section_id := COALESCE(
    (
        SELECT section_id
        FROM survey_questions
        WHERE survey_id = @survey_id
          AND code = 'Q41'
        LIMIT 1
    ),
    (
        SELECT section_id
        FROM survey_questions
        WHERE survey_id = @survey_id
          AND code = 'Q43'
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND sort_order = 6
        ORDER BY id
        LIMIT 1
    ),
    (
        SELECT id
        FROM survey_sections
        WHERE survey_id = @survey_id
          AND title IN ('Análisis de personajes y medios', 'Análisis de personajes políticos')
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
    visibility_rules_json LONGTEXT NULL
);

INSERT INTO tmp_question_updates (
    section_ref,
    code,
    prompt,
    question_type,
    is_required,
    sort_order,
    visibility_rules_json
) VALUES
    ('leadership', 'Q16', '¿Ud considera que Leonardo Orlando, una vez terminado su segundo mandato como Prefecto de Manabí, debe seguir en la política?', 'single_choice', 1, 7, '[]'),
    ('leadership', 'Q17', 'Si respondió que sí en la pregunta anterior, ¿qué dignidad considera usted que Leonardo Orlando debería ocupar?', 'single_choice', 0, 8, '[{"question_code":"Q16","operator":"equals","value":"SI"}]'),
    ('leadership', 'Q18', '¿Cree usted que Leonardo Orlando debe formar su propio partido/movimiento?', 'single_choice', 1, 9, '[]'),
    ('leadership', 'Q19', '¿Cree usted que Leonardo Orlando debe formar alianzas con Daniel Noboa?', 'single_choice', 1, 10, '[]'),
    ('leadership', 'Q20', '¿Qué imagen tiene de Luisa González?', 'rating', 1, 11, '[]'),
    ('leadership', 'Q21', '¿Usted le cree a Luisa González?', 'single_choice', 1, 12, '[]'),
    ('leadership', 'Q22', 'Según usted, ¿cuál de las siguientes figuras representa mejor al correísmo en Manabí?', 'single_choice', 1, 13, '[]'),
    ('leadership', 'Q23', 'Ud se considera:', 'single_choice', 1, 14, '[]'),
    ('leadership', 'Q24', 'O se considera:', 'single_choice', 1, 15, '[]'),
    ('leadership', 'Q25', '¿Con qué partido o movimiento político se identifica más?', 'single_choice', 1, 16, '[]'),
    ('sectional', 'Q26', 'Si las elecciones a Prefecto/a fueran hoy, por quién votaría Papeleta (A)', 'single_choice', 1, 1, '[]'),
    ('sectional', 'Q27', 'Si las elecciones a Prefecto/a fueran hoy, por quién votaría Papeleta (B)', 'single_choice', 1, 2, '[]'),
    ('sectional', 'Q28', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (A)', 'single_choice', 0, 3, '[{"question_code":"Q1","operator":"equals","value":"PORTOVIEJO"}]'),
    ('sectional', 'Q29', 'Si las elecciones a Alcalde/sa fueran hoy, por quién votaría Papeleta (B)', 'single_choice', 0, 4, '[{"question_code":"Q1","operator":"equals","value":"PORTOVIEJO"}]'),
    ('media', 'Q43', 'Si utiliza redes sociales, ¿qué redes usa?', 'multiple_choice', 0, 3, '[{"question_code":"Q42","operator":"equals","value":"REDES_SOCIALES"}]');

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
        WHEN 'sectional' THEN @sectional_section_id
        ELSE @media_section_id
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
    NULL
FROM tmp_question_updates t
WHERE @survey_id IS NOT NULL
  AND (
      (t.section_ref = 'leadership' AND @leadership_section_id IS NOT NULL)
      OR (t.section_ref = 'sectional' AND @sectional_section_id IS NOT NULL)
      OR (t.section_ref = 'media' AND @media_section_id IS NOT NULL)
  )
ON DUPLICATE KEY UPDATE
    section_id = VALUES(section_id),
    prompt = VALUES(prompt),
    question_type = VALUES(question_type),
    is_required = VALUES(is_required),
    sort_order = VALUES(sort_order),
    visibility_rules_json = VALUES(visibility_rules_json),
    validation_rules_json = VALUES(validation_rules_json);

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
    ('Q16', 'SI', 'Sí', 'Sí', 1, 0),
    ('Q16', 'NO', 'No', 'No', 2, 0),
    ('Q17', 'ALCALDE', 'Alcalde', 'Alcalde', 1, 0),
    ('Q17', 'ASAMBLEISTA', 'Asambleísta', 'Asambleísta', 2, 0),
    ('Q17', 'PRESIDENTE', 'Presidente de la República', 'Presidente de la República', 3, 0),
    ('Q18', 'SI', 'Sí', 'Sí', 1, 0),
    ('Q18', 'SEGUIR_RC', 'No, debe seguir formando parte de la RC', 'No, debe seguir formando parte de la RC', 2, 0),
    ('Q18', 'ALIARSE_OTRO', 'No, debe aliarse con otro partido existente', 'No, debe aliarse con otro partido existente', 3, 0),
    ('Q19', 'SI', 'Sí', 'Sí', 1, 0),
    ('Q19', 'NO', 'No', 'No', 2, 0),
    ('Q20', 'MUY_BUENO', 'Muy bueno', 'Muy bueno', 1, 0),
    ('Q20', 'BUENO', 'Bueno', 'Bueno', 2, 0),
    ('Q20', 'INDIFERENTE', 'Indiferente', 'Indiferente', 3, 0),
    ('Q20', 'MALO', 'Malo', 'Malo', 4, 0),
    ('Q20', 'MUY_MALO', 'Muy malo', 'Muy malo', 5, 0),
    ('Q21', 'SI', 'Sí', 'Sí', 1, 0),
    ('Q21', 'NO', 'No', 'No', 2, 0),
    ('Q22', 'JAIME_ESTRADA', 'Jaime Estrada', 'Jaime Estrada', 1, 0),
    ('Q22', 'JUAN_JOSE_PENA', 'Juan José Peña', 'Juan José Peña', 2, 0),
    ('Q22', 'LEONARDO_ORLANDO', 'Leonardo Orlando', 'Leonardo Orlando', 3, 0),
    ('Q22', 'LUISA_GONZALEZ', 'Luisa González', 'Luisa González', 4, 0),
    ('Q23', 'CORREISTA', 'Correísta', 'Correísta', 1, 0),
    ('Q23', 'ANTICORREISTA', 'Anticorreísta', 'Anticorreísta', 2, 0),
    ('Q23', 'INDIFERENTE', 'Indiferente', 'Indiferente', 3, 0),
    ('Q24', 'NOBOISTA', 'Noboísta', 'Noboísta', 1, 0),
    ('Q24', 'ANTINOBOISTA', 'Antinoboísta', 'Antinoboísta', 2, 0),
    ('Q24', 'INDIFERENTE', 'Indiferente', 'Indiferente', 3, 0),
    ('Q25', 'CENTRO_DEMOCRATICO', 'Centro Democrático', 'Centro Democrático', 1, 0),
    ('Q25', 'UNIDAD_POPULAR', 'Unidad Popular', 'Unidad Popular', 2, 0),
    ('Q25', 'PSP', 'Partido Sociedad Patriótica', 'Partido Sociedad Patriótica', 3, 0),
    ('Q25', 'PUEBLO_IGUALDAD', 'Pueblo, Igualdad', 'Pueblo, Igualdad', 4, 0),
    ('Q25', 'RC5', 'Revolución Ciudadana', 'Revolución Ciudadana', 5, 0),
    ('Q25', 'PSC', 'PSC', 'PSC', 6, 0),
    ('Q25', 'ADN', 'ADN', 'ADN', 7, 0),
    ('Q25', 'AVANZA', 'Avanza', 'Avanza', 8, 0),
    ('Q25', 'ID', 'Izquierda Democrática', 'Izquierda Democrática', 9, 0),
    ('Q25', 'AMIGO', 'Amigo', 'Amigo', 10, 0),
    ('Q25', 'PARTIDO_SOCIALISTA', 'Partido Socialista', 'Partido Socialista', 11, 0),
    ('Q25', 'PACHAKUTIK', 'Pachakutik', 'Pachakutik', 12, 0),
    ('Q25', 'RETO', 'Reto', 'Reto', 13, 0),
    ('Q25', 'SUMA', 'SUMA', 'SUMA', 14, 0),
    ('Q25', 'CONSTRUYE', 'Construye', 'Construye', 15, 0),
    ('Q25', 'CREO', 'CREO', 'CREO', 16, 0),
    ('Q25', 'DEMOCRACIA_SI', 'Democracia Sí', 'Democracia Sí', 17, 0),
    ('Q25', 'NO_SABE', 'No sabe / No contesta', 'No sabe / No contesta', 18, 0),
    ('Q25', 'NULO', 'Nulo', 'Nulo', 19, 0),
    ('Q25', 'BLANCO', 'Blanco', 'Blanco', 20, 0),
    ('Q26', 'JUAN_JOSE_PENA', 'Juan José Peña - Si Podemos', 'Juan José Peña - Si Podemos', 1, 0),
    ('Q26', 'AGUSTIN_CASANOVA', 'Agustín Casanova - Caminantes', 'Agustín Casanova - Caminantes', 2, 0),
    ('Q26', 'ABEL_GOMEZ', 'Abel Gómez - ADN', 'Abel Gómez - ADN', 3, 0),
    ('Q26', 'LEONARDO_RODRIGUEZ', 'Leonardo Rodríguez', 'Leonardo Rodríguez', 4, 0),
    ('Q26', 'NO_SABE', 'No sabe, no ha decidido', 'No sabe, no ha decidido', 5, 0),
    ('Q26', 'NULO', 'Nulo/Ninguno', 'Nulo/Ninguno', 6, 0),
    ('Q27', 'JAIME_ESTRADA', 'Jaime Estrada - Si Podemos', 'Jaime Estrada - Si Podemos', 1, 0),
    ('Q27', 'AGUSTIN_CASANOVA', 'Agustín Casanova - Caminantes', 'Agustín Casanova - Caminantes', 2, 0),
    ('Q27', 'CARLOS_LUIS_ANDRADE', 'Carlos Luis Andrade - ADN', 'Carlos Luis Andrade - ADN', 3, 0),
    ('Q27', 'LEONARDO_ORLANDO', 'Candidato Leonardo Orlando', 'Candidato Leonardo Orlando', 4, 0),
    ('Q27', 'LEONARDO_RODRIGUEZ', 'Leonardo Rodríguez', 'Leonardo Rodríguez', 5, 0),
    ('Q27', 'NO_SABE', 'No sabe, no ha decidido', 'No sabe, no ha decidido', 6, 0),
    ('Q27', 'NULO', 'Nulo/Ninguno', 'Nulo/Ninguno', 7, 0),
    ('Q28', 'LEONARDO_ORLANDO', 'Leonardo Orlando - Si Podemos', 'Leonardo Orlando - Si Podemos', 1, 0),
    ('Q28', 'JAVIER_PINCAY', 'Javier Pincay - Avanza-Machete', 'Javier Pincay - Avanza-Machete', 2, 0),
    ('Q28', 'MARIA_JOSE_FERNANDEZ', 'María José Fernández - ADN', 'María José Fernández - ADN', 3, 0),
    ('Q28', 'LEONEL_MUNOZ', 'Leonel Muñoz', 'Leonel Muñoz', 4, 0),
    ('Q28', 'OTRO', 'Otro, alguien nuevo', 'Otro, alguien nuevo', 5, 1),
    ('Q28', 'NO_SABE', 'No sabe, no ha decidido', 'No sabe, no ha decidido', 6, 0),
    ('Q28', 'NULO', 'Nulo/Ninguno', 'Nulo/Ninguno', 7, 0),
    ('Q29', 'CANDIDATO_SI_PODEMOS', 'Candidato Si Podemos', 'Candidato Si Podemos', 1, 0),
    ('Q29', 'JAVIER_PINCAY', 'Javier Pincay - ADN', 'Javier Pincay - ADN', 2, 0),
    ('Q29', 'CANDIDATO_LEONARDO_ORLANDO', 'Candidato de Leonardo Orlando', 'Candidato de Leonardo Orlando', 3, 0),
    ('Q29', 'CANDIDATO_AGUSTIN_CASANOVA', 'Candidato de Agustín Casanova - Caminantes', 'Candidato de Agustín Casanova - Caminantes', 4, 0),
    ('Q29', 'LEONEL_MUNOZ', 'Leonel Muñoz', 'Leonel Muñoz', 5, 0),
    ('Q29', 'MARIA_JOSE_FERNANDEZ', 'María José Fernández - ADN', 'María José Fernández - ADN', 6, 0),
    ('Q29', 'OTRO', 'Otro, alguien nuevo', 'Otro, alguien nuevo', 7, 1),
    ('Q29', 'NO_SABE', 'No sabe, no ha decidido', 'No sabe, no ha decidido', 8, 0),
    ('Q29', 'NULO', 'Nulo/Ninguno', 'Nulo/Ninguno', 9, 0),
    ('Q43', 'X', 'X (antes Twitter)', 'X (antes Twitter)', 1, 0),
    ('Q43', 'FACEBOOK', 'Facebook', 'Facebook', 2, 0),
    ('Q43', 'INSTAGRAM', 'Instagram', 'Instagram', 3, 0),
    ('Q43', 'TIKTOK', 'TikTok', 'TikTok', 4, 0),
    ('Q43', 'YOUTUBE', 'YouTube', 'YouTube', 5, 0);

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

DROP TEMPORARY TABLE IF EXISTS tmp_deprecated_questions;
CREATE TEMPORARY TABLE tmp_deprecated_questions (
    code VARCHAR(60) NOT NULL PRIMARY KEY,
    fallback_sort_order INT NOT NULL
);

INSERT INTO tmp_deprecated_questions (code, fallback_sort_order) VALUES
    ('Q20A', 901),
    ('Q21A', 902),
    ('Q22A', 903),
    ('Q23_ALCALDE_APOYO', 904),
    ('Q24_ALCALDE_RC_PORTOVIEJO', 905),
    ('Q24_ALCALDE_RC_MANTA', 906),
    ('Q24_ALCALDE_RC_CHONE', 907),
    ('Q24_ALCALDE_RC_EL_CARMEN', 908),
    ('Q24_ALCALDE_RC_JIPIJAPA', 909),
    ('Q24_ALCALDE_RC_MONTECRISTI', 910),
    ('Q24_ALCALDE_RC_PEDERNALES', 911),
    ('Q24_ALCALDE_RC_SUCRE', 912),
    ('Q24_ALCALDE_RC_SANTA_ANA', 913),
    ('Q24_ALCALDE_RC_TOSAGUA', 914),
    ('Q24_ALCALDE_RC_JUNIN', 915),
    ('Q24_ALCALDE_RC_24_DE_MAYO', 916),
    ('Q24_ALCALDE_RC_PICHINCHA', 917),
    ('Q24_ALCALDE_RC_SAN_VICENTE', 918),
    ('Q24_ALCALDE_RC_PAJAN', 919),
    ('Q24_ALCALDE_RC_OLMEDO', 920),
    ('Q24_ALCALDE_RC_FLAVIO_ALFARO', 921),
    ('Q24_ALCALDE_RC_PUERTO_LOPEZ', 922),
    ('Q24_ALCALDE_RC_JARAMIJO', 923),
    ('Q24_ALCALDE_RC_ROCAFUERTE', 924),
    ('Q24_ALCALDE_RC_JAMA', 925),
    ('Q23_PREFECTO_APOYO', 926),
    ('Q24_PREFECTO_RC', 927),
    ('Q28_MANTA', 928),
    ('Q28_EL_CARMEN', 929),
    ('Q28_SUCRE', 930),
    ('Q29_MANTA', 931),
    ('Q29_EL_CARMEN', 932),
    ('Q30', 933);

DELETE q
FROM survey_questions q
INNER JOIN tmp_deprecated_questions t ON t.code = q.code
WHERE q.survey_id = @survey_id
  AND NOT EXISTS (
      SELECT 1
      FROM response_answers a
      WHERE a.question_id = q.id
      LIMIT 1
  );

UPDATE survey_questions q
INNER JOIN tmp_deprecated_questions t ON t.code = q.code
SET q.is_required = 0,
    q.sort_order = t.fallback_sort_order,
    q.visibility_rules_json = '[{"question_code":"__DOC_SYNC__","operator":"equals","value":"1"}]'
WHERE q.survey_id = @survey_id;

DROP TEMPORARY TABLE IF EXISTS tmp_deprecated_questions;
DROP TEMPORARY TABLE IF EXISTS tmp_option_updates;
DROP TEMPORARY TABLE IF EXISTS tmp_question_updates;

COMMIT;
