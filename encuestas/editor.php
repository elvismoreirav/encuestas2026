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

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= e($survey['name']) ?></h2>
            <p><?= e($survey['description'] ?: 'Sin descripción.') ?></p>
            <div class="actions-inline" style="margin-top:14px;">
                <span class="chip chip-muted"><?= e($survey['slug']) ?></span>
                <span class="chip chip-muted"><?= (int) count($survey['sections']) ?> secciones</span>
                <span class="chip chip-muted"><?= (int) count($survey['questions_flat']) ?> preguntas</span>
            </div>
        </div>
        <div class="actions-inline">
            <a class="btn btn-secondary" target="_blank" rel="noreferrer" href="<?= url('public/index.php?survey=' . urlencode($survey['slug'])) ?>">Abrir formulario</a>
            <button class="btn btn-secondary" type="button" data-open-modal="importModal">Carga masiva</button>
            <button class="btn btn-primary" type="button" id="newSectionButton" data-open-modal="sectionModal">Nueva sección</button>
        </div>
    </div>
</section>

<?php if ($survey['sections'] === []): ?>
    <section class="panel">
        <div class="empty-state">Todavía no existen secciones. Cree una o use la carga masiva.</div>
    </section>
<?php else: ?>
    <?php foreach ($survey['sections'] as $section): ?>
        <section class="section-card">
            <header>
                <div>
                    <h3><?= e($section['title']) ?></h3>
                    <p><?= e($section['description'] ?: 'Sin descripción.') ?></p>
                </div>
                <div class="actions-inline">
                    <button class="btn btn-secondary js-edit-section" type="button" data-id="<?= (int) $section['id'] ?>" data-open-modal="sectionModal">Editar sección</button>
                    <button class="btn btn-secondary js-new-question" type="button" data-section-id="<?= (int) $section['id'] ?>" data-open-modal="questionModal">Nueva pregunta</button>
                    <button class="btn btn-danger js-delete-section" type="button" data-id="<?= (int) $section['id'] ?>">Eliminar</button>
                </div>
            </header>
            <div class="question-list">
                <?php if ($section['questions'] === []): ?>
                    <div class="empty-state">No hay preguntas en esta sección.</div>
                <?php else: ?>
                    <?php foreach ($section['questions'] as $question): ?>
                        <article class="question-item">
                            <div class="actions-inline" style="justify-content:space-between;">
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
                                <span class="chip chip-muted"><?= e($question['question_type']) ?></span>
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
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

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

document.getElementById('newSectionButton').addEventListener('click', resetSectionForm);
document.getElementById('question_type').addEventListener('change', updateQuestionTypeFields);
updateQuestionTypeFields();

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
</script>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
