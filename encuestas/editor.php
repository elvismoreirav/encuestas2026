<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireLogin();
auth()->requireManageSurveys();

$surveyId = (int) ($_GET['id'] ?? 0);
$survey = surveys()->getSurvey($surveyId, auth()->user());

if (!$survey) {
    flash('error', 'La encuesta solicitada no existe o no está asignada a su usuario.');
    redirect('encuestas/index.php');
}

$pageTitle = 'Editor de encuesta';
$pageDescription = 'Gestione secciones, preguntas, lógica condicional y carga masiva.';
$currentPage = 'surveys';
$breadcrumbs = [
    ['title' => 'Encuestas', 'url' => url('encuestas/index.php')],
    ['title' => $survey['name']],
];
$sectionCount = count($survey['sections']);
$questionCount = count($survey['questions_flat']);
$requiredCount = count(array_filter($survey['questions_flat'], static fn(array $question): bool => (bool) $question['is_required']));
$conditionalCount = count(array_filter($survey['questions_flat'], static fn(array $question): bool => $question['visibility_rules'] !== []));
$questionTypes = [
    'single_choice' => 'Selección única',
    'multiple_choice' => 'Selección múltiple',
    'rating' => 'Escala / valoración',
    'text' => 'Texto corto',
    'textarea' => 'Texto largo',
    'matrix' => 'Matriz',
];
$sectionSummaries = array_map(static function (array $section): array {
    $questions = $section['questions'] ?? [];
    return [
        'id' => (int) $section['id'],
        'title' => (string) $section['title'],
        'question_count' => count($questions),
        'required_count' => count(array_filter($questions, static fn(array $question): bool => (bool) $question['is_required'])),
        'conditional_count' => count(array_filter($questions, static fn(array $question): bool => $question['visibility_rules'] !== [])),
    ];
}, $survey['sections']);

function editor_question_interaction_hint(array $question): string
{
    $type = (string) ($question['question_type'] ?? '');

    if ($type === 'rating') {
        return 'Seleccione una valoración';
    }

    if ($type === 'multiple_choice') {
        return 'Puede marcar varias opciones';
    }

    if ($type === 'single_choice') {
        return 'Seleccione una sola opción';
    }

    if ($type === 'matrix') {
        $rows = is_array($question['settings']['matrix']['rows'] ?? null) ? $question['settings']['matrix']['rows'] : [];
        return $rows !== [] ? 'Complete ' . count($rows) . ' filas' : 'Complete la matriz';
    }

    if ($type === 'textarea') {
        return 'Escriba una respuesta extendida';
    }

    return 'Ingrese su respuesta';
}

function editor_question_structure_hint(array $question): string
{
    $type = (string) ($question['question_type'] ?? '');

    if ($type === 'matrix') {
        $dimensions = is_array($question['settings']['matrix']['dimensions'] ?? null) ? $question['settings']['matrix']['dimensions'] : [];
        return $dimensions !== [] ? count($dimensions) . ' columnas de evaluación' : 'Formato matricial';
    }

    $options = is_array($question['options'] ?? null) ? $question['options'] : [];
    if ($options !== []) {
        return count($options) . ' opciones disponibles';
    }

    return !empty($question['placeholder']) ? 'Placeholder configurado' : ((bool) ($question['is_required'] ?? false) ? 'Respuesta requerida' : 'Campo opcional');
}

function editor_question_preview_state(array $question): array
{
    $type = (string) ($question['question_type'] ?? '');
    $options = is_array($question['options'] ?? null) ? $question['options'] : [];

    if (in_array($type, ['single_choice', 'multiple_choice', 'rating'], true)) {
        $optionCount = count($options);

        if ($optionCount === 0) {
            return [
                'label' => 'Sin opciones',
                'class' => 'chip chip-warning',
                'message' => 'Esta pregunta todavía no tiene respuestas configuradas.',
            ];
        }

        return [
            'label' => 'Vista previa lista',
            'class' => 'chip chip-muted',
            'message' => $optionCount . ' ' . ($optionCount === 1 ? 'opción configurada para validación rápida.' : 'opciones configuradas para validación rápida.'),
        ];
    }

    if ($type === 'matrix') {
        $matrix = is_array($question['settings']['matrix'] ?? null) ? $question['settings']['matrix'] : [];
        $rows = is_array($matrix['rows'] ?? null) ? $matrix['rows'] : [];
        $dimensions = is_array($matrix['dimensions'] ?? null) ? $matrix['dimensions'] : [];
        $configuredDimensions = count(array_filter($dimensions, static function (array $dimension): bool {
            return is_array($dimension['options'] ?? null) && $dimension['options'] !== [];
        }));
        $missingDimensions = count($dimensions) - $configuredDimensions;

        if ($rows === [] || $dimensions === [] || $configuredDimensions !== count($dimensions)) {
            return [
                'label' => 'Matriz incompleta',
                'class' => 'chip chip-warning',
                'message' => $rows === [] || $dimensions === []
                    ? 'Agregue filas, columnas y opciones para revisar esta matriz sin abrir el modal.'
                    : 'Hay ' . $missingDimensions . ' ' . ($missingDimensions === 1 ? 'columna sin opciones configuradas.' : 'columnas sin opciones configuradas.'),
            ];
        }

        return [
            'label' => 'Matriz lista',
            'class' => 'chip chip-muted',
            'message' => count($rows) . ' filas y ' . count($dimensions) . ' columnas configuradas para validación rápida.',
        ];
    }

    if (!empty($question['placeholder'])) {
        return [
            'label' => 'Placeholder listo',
            'class' => 'chip chip-muted',
            'message' => 'El campo abierto ya tiene una guía visible para captura.',
        ];
    }

    return [
        'label' => 'Campo abierto',
        'class' => 'chip chip-muted',
        'message' => 'Puede validar el enunciado y la captura esperada desde esta tarjeta.',
    ];
}

function editor_format_percentage(float $value): string
{
    return number_format($value, 1, ',', '.') . '%';
}

function editor_question_activity_state(array $question): array
{
    $activity = is_array($question['activity'] ?? null) ? $question['activity'] : [];
    $responses = (int) ($activity['responses'] ?? 0);
    $coveragePercentage = (float) ($activity['coverage_percentage'] ?? 0);
    $firstSubmissionAt = $activity['first_submission_at'] ?? null;
    $lastSubmissionAt = $activity['last_submission_at'] ?? null;
    $hasActivity = $responses > 0;

    return [
        'responses' => $responses,
        'coverage_percentage' => $coveragePercentage,
        'status_label' => $hasActivity ? 'Con respuestas' : 'Sin respuestas',
        'status_class' => $hasActivity ? 'chip chip-success' : 'chip chip-warning',
        'responses_label' => $responses . ' ' . ($responses === 1 ? 'respuesta' : 'respuestas'),
        'coverage_label' => 'Cobertura ' . editor_format_percentage($coveragePercentage),
        'first_submission_label' => Helpers::formatDateTime(is_string($firstSubmissionAt) ? $firstSubmissionAt : null),
        'last_submission_label' => Helpers::formatDateTime(is_string($lastSubmissionAt) ? $lastSubmissionAt : null),
        'summary' => $hasActivity
            ? $responses . ' ' . ($responses === 1 ? 'registro coincide con esta pregunta.' : 'registros coinciden con esta pregunta.')
            : 'Esta pregunta todavía no registra respuestas enviadas.',
    ];
}

function editor_render_question_preview(array $question): string
{
    $type = (string) ($question['question_type'] ?? '');
    $options = is_array($question['options'] ?? null) ? $question['options'] : [];
    $placeholder = trim((string) ($question['placeholder'] ?? ''));
    $matrix = is_array($question['settings']['matrix'] ?? null) ? $question['settings']['matrix'] : [];
    $rows = is_array($matrix['rows'] ?? null) ? $matrix['rows'] : [];
    $dimensions = is_array($matrix['dimensions'] ?? null) ? $matrix['dimensions'] : [];
    $ratingAccents = ['#027a48', '#2a6b4f', '#8b7b52', '#b54708', '#b42318'];

    ob_start();
    ?>
    <?php if (in_array($type, ['single_choice', 'multiple_choice', 'rating'], true)): ?>
        <?php if ($options === []): ?>
            <div class="editor-preview-empty">
                <strong>Faltan opciones por cargar</strong>
                <p>Agregue al menos una opción de respuesta para habilitar la vista previa completa de esta pregunta.</p>
            </div>
        <?php else: ?>
            <div class="<?= e($type === 'rating' ? 'rating-grid' : 'option-grid columns-2') ?>" aria-hidden="true">
                <?php foreach ($options as $index => $option): ?>
                    <?php
                    $classes = ['choice-card', 'editor-preview-choice-card'];
                    if ($type === 'multiple_choice') {
                        $classes[] = 'checkbox-card';
                    }
                    if ($type === 'rating') {
                        $classes[] = 'rating-card';
                    }

                    $optionCode = trim((string) ($option['code'] ?? ''));
                    $optionLabel = trim((string) ($option['label'] ?? ''));
                    $captionBits = [];
                    if ($optionCode !== '') {
                        $captionBits[] = 'Código ' . $optionCode;
                    }
                    if (!empty($option['is_other'])) {
                        $captionBits[] = 'Opción abierta';
                    }
                    $style = $type === 'rating'
                        ? ' style="--rating-accent:' . e($ratingAccents[$index % count($ratingAccents)]) . ';"'
                        : '';
                    ?>
                    <label class="<?= e(implode(' ', $classes)) ?>"<?= $style ?>>
                        <input class="choice-input" type="<?= $type === 'multiple_choice' ? 'checkbox' : 'radio' ?>" disabled>
                        <span class="choice-indicator" aria-hidden="true"></span>
                        <span class="choice-body">
                            <?php if ($type === 'rating'): ?>
                                <span class="rating-scale">Escala <?= $index + 1 ?></span>
                            <?php endif; ?>
                            <span class="choice-label"><?= e($optionLabel !== '' ? $optionLabel : ($optionCode !== '' ? $optionCode : 'Sin etiqueta')) ?></span>
                            <?php if ($captionBits !== []): ?>
                                <span class="choice-caption"><?= e(implode(' · ', $captionBits)) ?></span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($type === 'matrix'): ?>
        <?php if ($rows === [] || $dimensions === []): ?>
            <div class="editor-preview-empty">
                <strong>La matriz aún no está lista</strong>
                <p>Configure filas y columnas para revisar esta pregunta desde el editor.</p>
            </div>
        <?php else: ?>
            <?php
            $incompleteDimensions = array_values(array_filter($dimensions, static function (array $dimension): bool {
                return !is_array($dimension['options'] ?? null) || $dimension['options'] === [];
            }));
            ?>
            <div class="editor-preview-matrix-shell">
                <div class="matrix-helper">
                    <span class="chip chip-muted"><?= count($rows) ?> <?= count($rows) === 1 ? 'fila' : 'filas' ?> configuradas</span>
                    <span class="chip chip-muted"><?= count($dimensions) ?> <?= count($dimensions) === 1 ? 'columna' : 'columnas' ?> configuradas</span>
                    <span class="chip <?= $incompleteDimensions === [] ? 'chip-muted' : 'chip-warning' ?>">
                        <?= $incompleteDimensions === [] ? 'Todas las columnas tienen respuestas' : count($incompleteDimensions) . ' columnas sin respuestas' ?>
                    </span>
                </div>
                <?php if ($incompleteDimensions !== []): ?>
                    <div class="editor-preview-empty editor-preview-empty-compact">
                        <strong>Columnas pendientes de configurar</strong>
                        <p><?= e(implode(', ', array_map(static function (array $dimension): string {
                            return trim((string) ($dimension['label'] ?? $dimension['code'] ?? 'Dimensión'));
                        }, $incompleteDimensions))) ?></p>
                    </div>
                <?php endif; ?>
                <div class="editor-preview-matrix-rows">
                    <?php foreach ($rows as $rowIndex => $row): ?>
                        <?php
                        $rowLabel = trim((string) ($row['label'] ?? $row['code'] ?? 'Fila'));
                        $rowCode = trim((string) ($row['code'] ?? ''));
                        ?>
                        <section class="editor-preview-matrix-row">
                            <header class="editor-preview-matrix-row-head">
                                <div class="editor-preview-matrix-row-copy">
                                    <div class="editor-preview-matrix-row-title">
                                        <span class="matrix-order"><?= str_pad((string) ($rowIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                        <strong><?= e($rowLabel !== '' ? $rowLabel : 'Fila') ?></strong>
                                    </div>
                                    <?php if ($rowCode !== ''): ?>
                                        <small class="editor-preview-matrix-code">Código <?= e($rowCode) ?></small>
                                    <?php endif; ?>
                                </div>
                                <span class="chip chip-muted"><?= count($dimensions) ?> <?= count($dimensions) === 1 ? 'dimensión' : 'dimensiones' ?></span>
                            </header>
                            <div class="editor-preview-matrix-dimensions">
                                <?php foreach ($dimensions as $dimension): ?>
                                    <?php
                                    $dimensionOptions = is_array($dimension['options'] ?? null) ? $dimension['options'] : [];
                                    $dimensionLabel = trim((string) ($dimension['label'] ?? $dimension['code'] ?? 'Dimensión'));
                                    $dimensionCode = trim((string) ($dimension['code'] ?? ''));
                                    ?>
                                    <section class="editor-preview-matrix-dimension<?= $dimensionOptions === [] ? ' is-missing' : '' ?>">
                                        <header>
                                            <div class="editor-preview-matrix-head">
                                                <strong><?= e($dimensionLabel !== '' ? $dimensionLabel : 'Dimensión') ?></strong>
                                                <small>
                                                    <?= e($dimensionCode !== '' ? 'Código ' . $dimensionCode . ' · ' : '') ?>
                                                    <?= count($dimensionOptions) ?> <?= count($dimensionOptions) === 1 ? 'opción' : 'opciones' ?>
                                                </small>
                                            </div>
                                        </header>
                                        <?php if ($dimensionOptions === []): ?>
                                            <span class="matrix-cell-error">Sin opciones configuradas en esta columna.</span>
                                        <?php else: ?>
                                            <div class="matrix-options editor-preview-matrix-options">
                                                <?php foreach ($dimensionOptions as $optionIndex => $option): ?>
                                                    <?php
                                                    $optionCode = trim((string) ($option['code'] ?? ''));
                                                    $optionLabel = trim((string) ($option['label'] ?? ''));
                                                    $optionBadge = $optionCode !== '' ? $optionCode : chr(65 + ($optionIndex % 26));
                                                    ?>
                                                    <div class="matrix-option editor-preview-matrix-option">
                                                        <span class="matrix-option-badge"><?= e($optionBadge) ?></span>
                                                        <span class="matrix-option-label"><?= e($optionLabel !== '' ? $optionLabel : ($optionCode !== '' ? $optionCode : 'Sin etiqueta')) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="editor-question-preview-field">
            <label class="editor-question-preview-label"><?= $type === 'textarea' ? 'Respuesta abierta' : 'Respuesta esperada' ?></label>
            <?php if ($type === 'textarea'): ?>
                <textarea class="public-control public-textarea" disabled placeholder="<?= e($placeholder !== '' ? $placeholder : 'El encuestado escribirá aquí su respuesta.') ?>"></textarea>
            <?php else: ?>
                <input class="public-control" type="text" disabled placeholder="<?= e($placeholder !== '' ? $placeholder : 'El encuestado escribirá aquí su respuesta.') ?>">
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php

    return trim((string) ob_get_clean());
}

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= e($survey['name']) ?></h2>
            <p><?= e($survey['description'] ?: 'Sin descripción.') ?></p>
            <div class="actions-inline" style="margin-top:14px;">
                <span class="chip chip-muted"><?= e($survey['slug']) ?></span>
                <span class="chip chip-muted"><?= $sectionCount ?> secciones</span>
                <span class="chip chip-muted"><?= $questionCount ?> preguntas</span>
            </div>
        </div>
        <div class="actions-inline">
            <a class="btn btn-secondary" target="_blank" rel="noreferrer" href="<?= url('public/index.php?survey=' . urlencode($survey['slug'])) ?>">Abrir formulario</a>
            <button class="btn btn-secondary" type="button" data-open-modal="importModal">Carga masiva</button>
            <button class="btn btn-primary" type="button" id="newSectionButton" data-open-modal="sectionModal">Nueva sección</button>
        </div>
    </div>
</section>

<div class="grid-cards">
    <article class="card">
        <div class="metric-value"><?= $sectionCount ?></div>
        <div class="metric-label">Secciones activas</div>
        <div class="metric-foot">Bloques de navegación del instrumento</div>
    </article>
    <article class="card">
        <div class="metric-value"><?= $questionCount ?></div>
        <div class="metric-label">Preguntas configuradas</div>
        <div class="metric-foot">Estructura total disponible en esta encuesta</div>
    </article>
    <article class="card">
        <div class="metric-value"><?= $requiredCount ?></div>
        <div class="metric-label">Obligatorias</div>
        <div class="metric-foot">Preguntas que el formulario exige responder</div>
    </article>
    <article class="card">
        <div class="metric-value"><?= $conditionalCount ?></div>
        <div class="metric-label">Condicionales</div>
        <div class="metric-foot">Visibilidad dependiente de otra respuesta</div>
    </article>
</div>

<section class="panel panel-muted">
    <div class="panel-header">
        <div>
            <h2>Explorador de estructura</h2>
            <p>Busque por código o enunciado, filtre por tipo y navegue entre secciones sin perder contexto.</p>
        </div>
        <div class="actions-inline">
            <button class="btn btn-secondary btn-compact" type="button" id="expandSectionsButton">Expandir todo</button>
            <button class="btn btn-secondary btn-compact" type="button" id="collapseSectionsButton">Contraer todo</button>
        </div>
    </div>
    <div class="admin-toolbar">
        <div class="field">
            <label for="questionSearchInput">Buscar pregunta</label>
            <input type="search" id="questionSearchInput" placeholder="Código, enunciado o texto de ayuda">
        </div>
        <div class="field">
            <label for="questionTypeFilter">Tipo</label>
            <select id="questionTypeFilter">
                <option value="all">Todos</option>
                <?php foreach ($questionTypes as $questionTypeValue => $questionTypeLabel): ?>
                    <option value="<?= e($questionTypeValue) ?>"><?= e($questionTypeLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="toggle-chip">
            <input type="checkbox" id="conditionalOnlyToggle">
            <span>Mostrar solo preguntas condicionales</span>
        </label>
        <div class="toolbar-meta" id="editorResultsMeta">
            Mostrando <strong><?= $questionCount ?></strong> preguntas en <strong><?= $sectionCount ?></strong> secciones.
        </div>
    </div>
</section>

<div class="editor-shell">
    <div class="editor-main">
        <?php if ($survey['sections'] === []): ?>
            <section class="panel">
                <div class="empty-state">Todavía no existen secciones. Cree una o use la carga masiva.</div>
            </section>
        <?php else: ?>
            <div id="editorFilterEmptyState" class="empty-state" style="display:none;">No hay preguntas que coincidan con los filtros aplicados.</div>
            <?php foreach ($survey['sections'] as $section): ?>
                <?php
                $sectionQuestions = $section['questions'] ?? [];
                $sectionRequiredCount = count(array_filter($sectionQuestions, static fn(array $question): bool => (bool) $question['is_required']));
                $sectionConditionalCount = count(array_filter($sectionQuestions, static fn(array $question): bool => $question['visibility_rules'] !== []));
                ?>
                <section class="section-card editor-section-card" id="section-<?= (int) $section['id'] ?>" data-section-card>
                    <header class="section-card-header">
                        <div>
                            <h3><?= e($section['title']) ?></h3>
                            <p><?= e($section['description'] ?: 'Sin descripción.') ?></p>
                            <div class="section-card-meta">
                                <span class="chip chip-muted"><?= count($sectionQuestions) ?> preguntas</span>
                                <span class="chip chip-muted"><?= $sectionRequiredCount ?> obligatorias</span>
                                <?php if ($sectionConditionalCount > 0): ?>
                                    <span class="chip chip-warning"><?= $sectionConditionalCount ?> condicionales</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="actions-inline">
                            <button class="btn btn-secondary btn-compact js-toggle-section" type="button" data-section-toggle="section-body-<?= (int) $section['id'] ?>">Contraer</button>
                            <button class="btn btn-secondary js-edit-section" type="button" data-id="<?= (int) $section['id'] ?>" data-open-modal="sectionModal">Editar sección</button>
                            <button class="btn btn-secondary js-new-question" type="button" data-section-id="<?= (int) $section['id'] ?>" data-open-modal="questionModal">Nueva pregunta</button>
                            <button class="btn btn-danger js-delete-section" type="button" data-id="<?= (int) $section['id'] ?>">Eliminar</button>
                        </div>
                    </header>
                    <div class="question-list" id="section-body-<?= (int) $section['id'] ?>" data-section-body>
                        <?php if ($sectionQuestions === []): ?>
                            <div class="empty-state">No hay preguntas en esta sección.</div>
                        <?php else: ?>
                            <?php foreach ($sectionQuestions as $question): ?>
                                <?php
                                $searchChunks = [
                                    $question['code'],
                                    $question['prompt'],
                                    $question['help_text'] ?? '',
                                    implode(' ', array_map(static fn(array $option): string => (string) ($option['label'] ?? ''), $question['options'] ?? [])),
                                ];
                                $previewState = editor_question_preview_state($question);
                                $activityState = editor_question_activity_state($question);
                                ?>
                                <article
                                    class="question-item"
                                    data-question-item
                                    data-question-search="<?= e(strtolower(trim(implode(' ', array_filter($searchChunks, static fn($value): bool => $value !== null && $value !== ''))))) ?>"
                                    data-question-type="<?= e($question['question_type']) ?>"
                                    data-question-conditional="<?= $question['visibility_rules'] !== [] ? '1' : '0' ?>"
                                >
                                    <div class="question-item-head">
                                        <div>
                                            <h4><?= e($question['code']) ?>. <?= e($question['prompt']) ?></h4>
                                            <?php if ($question['help_text']): ?>
                                                <p><?= e($question['help_text']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="actions-inline">
                                            <button class="btn btn-secondary js-edit-question" type="button" data-id="<?= (int) $question['id'] ?>" data-open-modal="questionModal">Editar</button>
                                            <button class="btn btn-danger js-delete-question" type="button" data-id="<?= (int) $question['id'] ?>">Eliminar</button>
                                        </div>
                                    </div>
                                    <div class="question-meta">
                                        <span class="chip chip-muted"><?= e($questionTypes[$question['question_type']] ?? $question['question_type']) ?></span>
                                        <span class="chip chip-muted"><?= $question['is_required'] ? 'Obligatoria' : 'Opcional' ?></span>
                                        <span class="chip chip-muted">Orden <?= (int) $question['sort_order'] ?></span>
                                        <?php if ($question['visibility_rules'] !== []): ?>
                                            <span class="chip chip-warning">Condicional</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($question['options'] !== []): ?>
                                        <div class="question-meta">
                                            <?php foreach (array_slice($question['options'], 0, 4) as $option): ?>
                                                <span class="chip chip-muted"><?= e($option['label']) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($question['options']) > 4): ?>
                                                <span class="chip chip-muted">+<?= count($question['options']) - 4 ?> opciones</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="question-preview-actions">
                                        <div class="question-support question-preview-summary">
                                            <span class="question-support-item"><?= e(editor_question_interaction_hint($question)) ?></span>
                                            <span class="question-support-item"><?= e(editor_question_structure_hint($question)) ?></span>
                                            <span class="<?= e($previewState['class']) ?>"><?= e($previewState['label']) ?></span>
                                            <span class="<?= e($activityState['status_class']) ?>"><?= e($activityState['status_label']) ?></span>
                                            <span class="chip chip-muted"><?= e($activityState['responses_label']) ?></span>
                                        </div>
                                        <button
                                            class="btn btn-secondary btn-compact js-toggle-question-preview"
                                            type="button"
                                            data-question-preview-toggle="question-preview-<?= (int) $question['id'] ?>"
                                            aria-expanded="false"
                                        >
                                            Ver vista previa
                                        </button>
                                    </div>
                                    <div class="editor-question-preview" id="question-preview-<?= (int) $question['id'] ?>" hidden>
                                        <div class="editor-question-preview-head">
                                            <div class="editor-question-preview-copy">
                                                <div class="question-kicker">Vista previa</div>
                                                <strong><?= e($question['code']) ?>. <?= e($question['prompt']) ?></strong>
                                                <p><?= e($previewState['message']) ?></p>
                                                <?php if ($question['help_text']): ?>
                                                    <p><?= e($question['help_text']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="<?= e($previewState['class']) ?>"><?= e($previewState['label']) ?></span>
                                        </div>
                                        <div class="editor-question-activity">
                                            <div class="editor-question-activity-head">
                                                <strong>Actividad capturada</strong>
                                                <span class="<?= e($activityState['status_class']) ?>"><?= e($activityState['status_label']) ?></span>
                                            </div>
                                            <div class="question-meta">
                                                <span class="chip chip-muted"><?= e($activityState['responses_label']) ?></span>
                                                <span class="chip chip-muted"><?= e($activityState['coverage_label']) ?></span>
                                                <span class="chip chip-muted">Primera <?= e($activityState['first_submission_label']) ?></span>
                                                <span class="chip chip-muted">Última <?= e($activityState['last_submission_label']) ?></span>
                                            </div>
                                            <p><?= e($activityState['summary']) ?></p>
                                        </div>
                                        <?= editor_render_question_preview($question) ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if ($survey['sections'] !== []): ?>
        <aside class="editor-sidebar">
            <div class="panel editor-side-panel">
                <div class="panel-header">
                    <div>
                        <h2>Mapa de secciones</h2>
                        <p>Salte entre bloques y revise su peso dentro del instrumento.</p>
                    </div>
                </div>
                <nav class="section-nav">
                    <?php foreach ($sectionSummaries as $index => $sectionSummary): ?>
                        <a class="section-nav-link" href="#section-<?= $sectionSummary['id'] ?>">
                            <span class="section-nav-order"><?= $index + 1 ?></span>
                            <span class="section-nav-copy">
                                <strong><?= e($sectionSummary['title']) ?></strong>
                                <small><?= $sectionSummary['question_count'] ?> preguntas · <?= $sectionSummary['required_count'] ?> obligatorias<?= $sectionSummary['conditional_count'] > 0 ? ' · ' . $sectionSummary['conditional_count'] . ' condicionales' : '' ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="panel panel-muted editor-side-panel">
                <div class="stack">
                    <div>
                        <strong>Flujo sugerido</strong>
                        <p>Primero valide el orden de secciones, luego revise preguntas condicionales y finalmente haga una prueba en el formulario público.</p>
                    </div>
                    <div class="actions-inline">
                        <button class="btn btn-secondary" type="button" data-open-modal="importModal">Carga masiva</button>
                        <a class="btn btn-link" target="_blank" rel="noreferrer" href="<?= url('public/index.php?survey=' . urlencode($survey['slug'])) ?>">Probar encuesta</a>
                    </div>
                </div>
            </div>
        </aside>
    <?php endif; ?>
</div>

<div class="modal" id="sectionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3 id="sectionModalTitle">Nueva sección</h3>
                <p>Organice el formulario en bloques administrables.</p>
            </div>
            <button class="btn btn-secondary" type="button" data-close-modal>Cerrar</button>
        </div>
        <form id="sectionForm">
            <div class="modal-body stack">
                <input type="hidden" name="id" id="section_id">
                <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label>Título</label>
                        <input type="text" name="title" id="section_title" required>
                    </div>
                    <div class="field">
                        <label>Orden</label>
                        <input type="number" name="sort_order" id="section_sort_order" min="1" value="1" required>
                    </div>
                </div>
                <div class="field">
                    <label>Descripción</label>
                    <textarea name="description" id="section_description"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span class="chip chip-muted">Las preguntas se muestran al público siguiendo este orden.</span>
                <button class="btn btn-primary" type="submit">Guardar sección</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="questionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3 id="questionModalTitle">Nueva pregunta</h3>
                <p>Defina tipo, opciones, lógica condicional y configuración avanzada.</p>
            </div>
            <button class="btn btn-secondary" type="button" data-close-modal>Cerrar</button>
        </div>
        <form id="questionForm">
            <div class="modal-body stack">
                <input type="hidden" name="id" id="question_id">
                <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <div class="form-grid-3">
                    <div class="field">
                        <label>Sección</label>
                        <select name="section_id" id="question_section_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($survey['sections'] as $section): ?>
                                <option value="<?= (int) $section['id'] ?>"><?= e($section['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Código</label>
                        <input type="text" name="code" id="question_code" required placeholder="Q1">
                    </div>
                    <div class="field">
                        <label>Orden</label>
                        <input type="number" name="sort_order" id="question_sort_order" min="1" value="1" required>
                    </div>
                </div>
                <div class="field">
                    <label>Enunciado</label>
                    <textarea name="prompt" id="question_prompt" required></textarea>
                </div>
                <div class="field">
                    <label>Texto de ayuda</label>
                    <input type="text" name="help_text" id="question_help_text">
                </div>
                <div class="form-grid-3">
                    <div class="field">
                        <label>Tipo</label>
                        <select name="question_type" id="question_type" required>
                            <option value="single_choice">Selección única</option>
                            <option value="multiple_choice">Selección múltiple</option>
                            <option value="rating">Escala / valoración</option>
                            <option value="text">Texto corto</option>
                            <option value="textarea">Texto largo</option>
                            <option value="matrix">Matriz</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Placeholder</label>
                        <input type="text" name="placeholder" id="question_placeholder">
                    </div>
                    <div class="field">
                        <label>Obligatoria</label>
                        <select name="is_required" id="question_required">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>
                <div class="field" id="optionsField">
                    <label>Opciones</label>
                    <textarea name="options" id="question_options" placeholder="CODIGO|Etiqueta&#10;SI|Sí&#10;NO|No"></textarea>
                    <small>Una opción por línea. Formato recomendado: <code>CODIGO|Etiqueta</code>.</small>
                </div>
                <div class="field" id="matrixField">
                    <label>Configuración matriz (JSON)</label>
                    <textarea name="matrix_config" id="question_matrix_config" placeholder='{"rows":[{"code":"CANDIDATO","label":"Candidato"}],"dimensions":[{"code":"DIM","label":"Dimensión","options":[{"code":"SI","label":"Sí"}]}]}'></textarea>
                </div>
                <div class="form-grid-3">
                    <div class="field">
                        <label>Mostrar si responde</label>
                        <input type="text" name="visibility_question_code" id="question_visibility_question_code" placeholder="Q16">
                    </div>
                    <div class="field">
                        <label>Operador</label>
                        <select name="visibility_operator" id="question_visibility_operator">
                            <option value="equals">Igual a</option>
                            <option value="not_equals">Distinto de</option>
                            <option value="contains">Contiene</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Valor esperado</label>
                        <input type="text" name="visibility_value" id="question_visibility_value" placeholder="SI">
                    </div>
                </div>
                <div class="field">
                    <label>Configuración avanzada (JSON)</label>
                    <textarea name="settings_json" id="question_settings_json" placeholder='{"layout":"grid","maxSelections":3}'></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span class="chip chip-muted">La lógica condicional se basa en códigos de pregunta y códigos de opción.</span>
                <button class="btn btn-primary" type="submit">Guardar pregunta</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="importModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Carga masiva</h3>
                <p>Importe preguntas por JSON estructurado o CSV para acelerar la parametrización.</p>
            </div>
            <button class="btn btn-secondary" type="button" data-close-modal>Cerrar</button>
        </div>
        <form id="importForm">
            <div class="modal-body stack">
                <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label>Formato</label>
                        <select name="format" id="import_format">
                            <option value="json">JSON</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Referencia CSV</label>
                        <input value="section_title,section_description,code,prompt,question_type,is_required,options,visibility_question_code,visibility_operator,visibility_value" disabled>
                    </div>
                </div>
                <div class="field">
                    <label>Contenido a importar</label>
                    <textarea name="payload" id="import_payload" style="min-height:300px;" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span class="chip chip-muted">Puede importar por secciones completas y repetir el proceso cuantas veces necesite.</span>
                <button class="btn btn-primary" type="submit">Procesar importación</button>
            </div>
        </form>
    </div>
</div>

<script>
const survey = <?= json_encode($survey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const sectionForm = document.getElementById('sectionForm');
const questionForm = document.getElementById('questionForm');
const importForm = document.getElementById('importForm');
const sectionMap = new Map(survey.sections.map((section) => [Number(section.id), section]));
const questionMap = new Map(survey.questions_flat.map((question) => [Number(question.id), question]));

function resetSectionForm() {
    sectionForm.reset();
    document.getElementById('section_id').value = '';
    document.getElementById('section_sort_order').value = survey.sections.length + 1;
    document.getElementById('sectionModalTitle').textContent = 'Nueva sección';
}

function resetQuestionForm(sectionId = '') {
    questionForm.reset();
    document.getElementById('question_id').value = '';
    document.getElementById('question_section_id').value = sectionId;
    document.getElementById('question_required').value = '1';
    document.getElementById('question_sort_order').value = survey.questions_flat.length + 1;
    document.getElementById('questionModalTitle').textContent = 'Nueva pregunta';
    updateQuestionTypeFields();
}

function optionsToText(options) {
    return (options || []).map((option) => `${option.code}|${option.label}`).join('\n');
}

function updateQuestionTypeFields() {
    const type = document.getElementById('question_type').value;
    document.getElementById('optionsField').style.display = ['single_choice', 'multiple_choice', 'rating'].includes(type) ? 'grid' : 'none';
    document.getElementById('matrixField').style.display = type === 'matrix' ? 'grid' : 'none';
}

function syncSectionToggleButton(button, body) {
    if (!button || !body) {
        return;
    }

    button.textContent = body.hidden ? 'Expandir' : 'Contraer';
}

function syncQuestionPreviewButton(button, panel) {
    if (!button || !panel) {
        return;
    }

    const expanded = !panel.hidden;
    button.textContent = expanded ? 'Ocultar vista previa' : 'Ver vista previa';
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}

function setSectionsCollapsed(collapsed) {
    document.querySelectorAll('[data-section-body]').forEach((body) => {
        body.hidden = collapsed;
    });

    document.querySelectorAll('.js-toggle-section').forEach((button) => {
        const body = document.getElementById(button.dataset.sectionToggle || '');
        syncSectionToggleButton(button, body);
    });
}

function filterEditorQuestions() {
    const searchValue = (document.getElementById('questionSearchInput')?.value || '').trim().toLowerCase();
    const typeValue = document.getElementById('questionTypeFilter')?.value || 'all';
    const conditionalOnly = !!document.getElementById('conditionalOnlyToggle')?.checked;
    const hasActiveFilter = searchValue !== '' || typeValue !== 'all' || conditionalOnly;
    let visibleQuestions = 0;
    let visibleSections = 0;

    document.querySelectorAll('[data-section-card]').forEach((sectionCard) => {
        const body = sectionCard.querySelector('[data-section-body]');
        const items = Array.from(sectionCard.querySelectorAll('[data-question-item]'));
        let matchesInSection = 0;

        items.forEach((item) => {
            const haystack = item.dataset.questionSearch || '';
            const matchesSearch = searchValue === '' || haystack.includes(searchValue);
            const matchesType = typeValue === 'all' || item.dataset.questionType === typeValue;
            const matchesConditional = !conditionalOnly || item.dataset.questionConditional === '1';
            const visible = matchesSearch && matchesType && matchesConditional;
            item.hidden = !visible;

            if (visible) {
                matchesInSection += 1;
            }
        });

        const sectionVisible = matchesInSection > 0 || (!hasActiveFilter && items.length === 0);
        sectionCard.hidden = !sectionVisible;
        if (sectionVisible) {
            visibleSections += 1;
        }

        if (matchesInSection > 0) {
            visibleQuestions += matchesInSection;

            if (hasActiveFilter && body) {
                body.hidden = false;
            }
        }

        sectionCard.querySelectorAll('.js-toggle-section').forEach((button) => {
            syncSectionToggleButton(button, body);
        });
    });

    const meta = document.getElementById('editorResultsMeta');
    if (meta) {
        meta.innerHTML = `Mostrando <strong>${visibleQuestions}</strong> preguntas en <strong>${visibleSections}</strong> secciones.`;
    }

    const emptyState = document.getElementById('editorFilterEmptyState');
    if (emptyState) {
        emptyState.style.display = hasActiveFilter && visibleQuestions === 0 ? 'block' : 'none';
    }
}

document.getElementById('newSectionButton').addEventListener('click', resetSectionForm);
document.getElementById('question_type').addEventListener('change', updateQuestionTypeFields);
document.getElementById('expandSectionsButton')?.addEventListener('click', () => setSectionsCollapsed(false));
document.getElementById('collapseSectionsButton')?.addEventListener('click', () => setSectionsCollapsed(true));
document.getElementById('questionSearchInput')?.addEventListener('input', filterEditorQuestions);
document.getElementById('questionTypeFilter')?.addEventListener('change', filterEditorQuestions);
document.getElementById('conditionalOnlyToggle')?.addEventListener('change', filterEditorQuestions);
updateQuestionTypeFields();

document.querySelectorAll('.js-toggle-section').forEach((button) => {
    button.addEventListener('click', () => {
        const body = document.getElementById(button.dataset.sectionToggle || '');
        if (!body) {
            return;
        }

        body.hidden = !body.hidden;
        syncSectionToggleButton(button, body);
    });
});

document.querySelectorAll('.js-toggle-question-preview').forEach((button) => {
    button.addEventListener('click', () => {
        const panel = document.getElementById(button.dataset.questionPreviewToggle || '');
        if (!panel) {
            return;
        }

        panel.hidden = !panel.hidden;
        syncQuestionPreviewButton(button, panel);
    });

    const panel = document.getElementById(button.dataset.questionPreviewToggle || '');
    syncQuestionPreviewButton(button, panel);
});

document.querySelectorAll('.js-edit-section').forEach((button) => {
    button.addEventListener('click', () => {
        const section = sectionMap.get(Number(button.dataset.id));
        if (!section) return;
        document.getElementById('sectionModalTitle').textContent = 'Editar sección';
        document.getElementById('section_id').value = section.id;
        document.getElementById('section_title').value = section.title ?? '';
        document.getElementById('section_sort_order').value = section.sort_order ?? 1;
        document.getElementById('section_description').value = section.description ?? '';
    });
});

document.querySelectorAll('.js-new-question').forEach((button) => {
    button.addEventListener('click', () => resetQuestionForm(button.dataset.sectionId));
});

document.querySelectorAll('.js-edit-question').forEach((button) => {
    button.addEventListener('click', () => {
        const question = questionMap.get(Number(button.dataset.id));
        if (!question) return;

        document.getElementById('questionModalTitle').textContent = 'Editar pregunta';
        document.getElementById('question_id').value = question.id;
        document.getElementById('question_section_id').value = question.section_id;
        document.getElementById('question_code').value = question.code ?? '';
        document.getElementById('question_sort_order').value = question.sort_order ?? 1;
        document.getElementById('question_prompt').value = question.prompt ?? '';
        document.getElementById('question_help_text').value = question.help_text ?? '';
        document.getElementById('question_type').value = question.question_type ?? 'single_choice';
        document.getElementById('question_placeholder').value = question.placeholder ?? '';
        document.getElementById('question_required').value = question.is_required ? '1' : '0';
        document.getElementById('question_options').value = optionsToText(question.options);
        document.getElementById('question_settings_json').value = JSON.stringify({...question.settings, matrix: undefined}, null, 2).replace('{}', '');
        document.getElementById('question_matrix_config').value = question.settings?.matrix ? JSON.stringify(question.settings.matrix, null, 2) : '';
        const rule = Array.isArray(question.visibility_rules) && question.visibility_rules.length ? question.visibility_rules[0] : {};
        document.getElementById('question_visibility_question_code').value = rule.question_code ?? '';
        document.getElementById('question_visibility_operator').value = rule.operator ?? 'equals';
        document.getElementById('question_visibility_value').value = rule.value ?? '';
        updateQuestionTypeFields();
    });
});

sectionForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(sectionForm);
    const isEditing = Boolean(formData.get('id'));
    const response = await fetch('<?= url('api/admin/app.php?action=save_section') ?>', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': formData.get('<?= e(CSRF_TOKEN_NAME) ?>')},
        body: formData,
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
        window.ShalomApp.notify('error', 'No se pudo guardar la sección', result.message || 'Revise la información e intente nuevamente.');
        return;
    }
    window.ShalomApp.queueToast({
        type: 'success',
        title: isEditing ? 'Sección actualizada' : 'Sección creada',
        message: 'La estructura de la encuesta fue actualizada correctamente.',
    });
    window.location.reload();
});

questionForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(questionForm);
    const isEditing = Boolean(formData.get('id'));
    const response = await fetch('<?= url('api/admin/app.php?action=save_question') ?>', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': formData.get('<?= e(CSRF_TOKEN_NAME) ?>')},
        body: formData,
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
        window.ShalomApp.notify('error', 'No se pudo guardar la pregunta', result.message || 'Revise la configuración e intente nuevamente.');
        return;
    }
    window.ShalomApp.queueToast({
        type: 'success',
        title: isEditing ? 'Pregunta actualizada' : 'Pregunta creada',
        message: 'La pregunta quedó disponible en la encuesta.',
    });
    window.location.reload();
});

importForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(importForm);
    const response = await fetch('<?= url('api/admin/app.php?action=import_questions') ?>', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': formData.get('<?= e(CSRF_TOKEN_NAME) ?>')},
        body: formData,
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
        window.ShalomApp.notify('error', 'No se pudo procesar la carga masiva', result.message || 'Revise el archivo e intente nuevamente.');
        return;
    }
    window.ShalomApp.queueToast({
        type: 'success',
        title: 'Carga masiva completada',
        message: `Secciones: ${result.counts.sections}, preguntas: ${result.counts.questions}, opciones: ${result.counts.options}.`,
    });
    window.location.reload();
});

document.querySelectorAll('.js-delete-section').forEach((button) => {
    button.addEventListener('click', async () => {
        const confirmed = await window.ShalomApp.confirm({
            title: 'Eliminar sección',
            message: 'Se eliminará la sección con todas sus preguntas asociadas.',
            confirmText: 'Eliminar sección',
            confirmClass: 'btn btn-danger',
        });
        if (!confirmed) return;
        const formData = new FormData();
        formData.append('id', button.dataset.id);
        formData.append('<?= e(CSRF_TOKEN_NAME) ?>', '<?= e(csrf_token()) ?>');
        const response = await fetch('<?= url('api/admin/app.php?action=delete_section') ?>', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '<?= e(csrf_token()) ?>'},
            body: formData,
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            window.ShalomApp.notify('error', 'No se pudo eliminar la sección', result.message || 'Intente nuevamente.');
            return;
        }
        window.ShalomApp.queueToast({
            type: 'success',
            title: 'Sección eliminada',
            message: 'La sección fue retirada de la estructura de la encuesta.',
        });
        window.location.reload();
    });
});

document.querySelectorAll('.js-delete-question').forEach((button) => {
    button.addEventListener('click', async () => {
        const confirmed = await window.ShalomApp.confirm({
            title: 'Eliminar pregunta',
            message: 'Se eliminará la pregunta seleccionada de la encuesta.',
            confirmText: 'Eliminar pregunta',
            confirmClass: 'btn btn-danger',
        });
        if (!confirmed) return;
        const formData = new FormData();
        formData.append('id', button.dataset.id);
        formData.append('<?= e(CSRF_TOKEN_NAME) ?>', '<?= e(csrf_token()) ?>');
        const response = await fetch('<?= url('api/admin/app.php?action=delete_question') ?>', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '<?= e(csrf_token()) ?>'},
            body: formData,
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            window.ShalomApp.notify('error', 'No se pudo eliminar la pregunta', result.message || 'Intente nuevamente.');
            return;
        }
        window.ShalomApp.queueToast({
            type: 'success',
            title: 'Pregunta eliminada',
            message: 'La pregunta ya no forma parte de la encuesta.',
        });
        window.location.reload();
    });
});

filterEditorQuestions();
</script>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
