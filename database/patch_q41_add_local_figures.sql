-- Agrega filas adicionales a la matriz Q41 usando los nombres entregados
-- en el documento de referencia.
-- El script es idempotente: agrega cada fila solo si todavía no existe.

SET @q41_target_id := COALESCE(
    (
        SELECT q.id
        FROM survey_questions q
        WHERE q.survey_id = 1
          AND q.code = 'Q41'
        ORDER BY q.id
        LIMIT 1
    ),
    (
        SELECT q.id
        FROM survey_questions q
        WHERE q.code = 'Q41'
        ORDER BY q.id
        LIMIT 1
    )
);

SELECT
    @q41_target_id AS q41_target_id,
    IF(
        @q41_target_id IS NULL,
        'No se encontró la pregunta Q41. No se aplicarán cambios.',
        'Q41 localizada. Se agregarán las filas faltantes.'
    ) AS status_message;

UPDATE survey_questions q
SET q.settings_json = JSON_SET(
    q.settings_json,
    '$.matrix.rows',
    JSON_ARRAY_APPEND(
        JSON_EXTRACT(q.settings_json, '$.matrix.rows'),
        '$',
        JSON_OBJECT('code', 'JAVIER_PINCAY', 'label', 'Javier Pincay')
    )
)
WHERE q.id = @q41_target_id
  AND JSON_VALID(q.settings_json)
  AND JSON_TYPE(JSON_EXTRACT(q.settings_json, '$.matrix.rows')) = 'ARRAY'
  AND JSON_SEARCH(q.settings_json, 'one', 'JAVIER_PINCAY', NULL, '$.matrix.rows[*].code') IS NULL;

UPDATE survey_questions q
SET q.settings_json = JSON_SET(
    q.settings_json,
    '$.matrix.rows',
    JSON_ARRAY_APPEND(
        JSON_EXTRACT(q.settings_json, '$.matrix.rows'),
        '$',
        JSON_OBJECT('code', 'MARIO_DE_GENNA', 'label', 'Mario De Genna')
    )
)
WHERE q.id = @q41_target_id
  AND JSON_VALID(q.settings_json)
  AND JSON_TYPE(JSON_EXTRACT(q.settings_json, '$.matrix.rows')) = 'ARRAY'
  AND JSON_SEARCH(q.settings_json, 'one', 'MARIO_DE_GENNA', NULL, '$.matrix.rows[*].code') IS NULL;

UPDATE survey_questions q
SET q.settings_json = JSON_SET(
    q.settings_json,
    '$.matrix.rows',
    JSON_ARRAY_APPEND(
        JSON_EXTRACT(q.settings_json, '$.matrix.rows'),
        '$',
        JSON_OBJECT('code', 'JAIME_ESTRADA_PADRE', 'label', 'Jaime Estrada Padre')
    )
)
WHERE q.id = @q41_target_id
  AND JSON_VALID(q.settings_json)
  AND JSON_TYPE(JSON_EXTRACT(q.settings_json, '$.matrix.rows')) = 'ARRAY'
  AND JSON_SEARCH(q.settings_json, 'one', 'JAIME_ESTRADA_PADRE', NULL, '$.matrix.rows[*].code') IS NULL;

UPDATE survey_questions q
SET q.settings_json = JSON_SET(
    q.settings_json,
    '$.matrix.rows',
    JSON_ARRAY_APPEND(
        JSON_EXTRACT(q.settings_json, '$.matrix.rows'),
        '$',
        JSON_OBJECT('code', 'JAIME_ESTRADA_HIJO', 'label', 'Jaime Estrada hijo')
    )
)
WHERE q.id = @q41_target_id
  AND JSON_VALID(q.settings_json)
  AND JSON_TYPE(JSON_EXTRACT(q.settings_json, '$.matrix.rows')) = 'ARRAY'
  AND JSON_SEARCH(q.settings_json, 'one', 'JAIME_ESTRADA_HIJO', NULL, '$.matrix.rows[*].code') IS NULL;

UPDATE survey_questions q
SET q.settings_json = JSON_SET(
    q.settings_json,
    '$.matrix.rows',
    JSON_ARRAY_APPEND(
        JSON_EXTRACT(q.settings_json, '$.matrix.rows'),
        '$',
        JSON_OBJECT('code', 'ABEL_GOMEZ', 'label', 'Abel Gómez')
    )
)
WHERE q.id = @q41_target_id
  AND JSON_VALID(q.settings_json)
  AND JSON_TYPE(JSON_EXTRACT(q.settings_json, '$.matrix.rows')) = 'ARRAY'
  AND JSON_SEARCH(q.settings_json, 'one', 'ABEL_GOMEZ', NULL, '$.matrix.rows[*].code') IS NULL;

SELECT
    q.id,
    q.survey_id,
    q.code,
    JSON_EXTRACT(q.settings_json, '$.matrix.rows') AS matrix_rows
FROM survey_questions q
WHERE q.id = @q41_target_id;
