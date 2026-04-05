<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireLogin();
auth()->requireManageSurveys();

$authUser = auth()->user();
$surveys = surveys()->listSurveys($authUser);

$pageTitle = 'Encuestas';
$pageDescription = 'Cree, programe y publique múltiples encuestas con control de fechas y acceso al editor estructural.';
$currentPage = 'surveys';
$breadcrumbs = [['title' => 'Encuestas']];

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Portafolio de encuestas</h2>
            <p>Cada encuesta puede manejar fecha de inicio, fecha de cierre, formulario público y estructura independiente.</p>
        </div>
        <button class="btn btn-primary" type="button" data-open-modal="surveyModal" id="createSurveyButton">Nueva encuesta</button>
    </div>
    <?php if ($surveys === []): ?>
        <div class="empty-state">No hay encuestas registradas todavía.</div>
    <?php else: ?>
        <div class="survey-grid">
            <?php foreach ($surveys as $survey): ?>
                <article class="survey-card">
                    <span class="<?= e(str_contains($survey['window_status'], 'active') ? 'chip chip-success' : (str_contains($survey['window_status'], 'scheduled') ? 'chip chip-warning' : 'chip chip-muted')) ?>">
                        <?= e($survey['status_label']) ?>
                    </span>
                    <h3 style="margin-top:14px;"><?= e($survey['name']) ?></h3>
                    <p><?= e($survey['description'] ?: 'Sin descripción registrada.') ?></p>
                    <div class="meta">
                        <span class="chip chip-muted"><?= (int) $survey['section_count'] ?> secciones</span>
                        <span class="chip chip-muted"><?= (int) $survey['question_count'] ?> preguntas</span>
                        <span class="chip chip-muted"><?= (int) $survey['response_count'] ?> respuestas</span>
                        <?php if (auth()->canManageUsers()): ?>
                            <span class="chip chip-muted"><?= (int) ($survey['assigned_user_count'] ?? 0) ?> usuarios asignados</span>
                        <?php endif; ?>
                    </div>
                    <div class="actions-inline">
                        <a class="btn btn-secondary" href="<?= url('encuestas/editor.php?id=' . (int) $survey['id']) ?>">Editar estructura</a>
                        <button class="btn btn-secondary js-edit-survey" type="button" data-id="<?= (int) $survey['id'] ?>" data-open-modal="surveyModal">Editar ficha</button>
                        <button class="btn btn-danger js-delete-survey" type="button" data-id="<?= (int) $survey['id'] ?>">Eliminar</button>
                    </div>
                    <div class="actions-inline" style="margin-top:12px;">
                        <a class="btn btn-link" href="<?= e($survey['public_url']) ?>" target="_blank" rel="noreferrer">Abrir formulario público</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Resumen tabular</h2>
            <p>Vista rápida para operación y trazabilidad.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Encuesta</th>
                <th>Estado</th>
                <th>Inicio</th>
                <th>Cierre</th>
                <th>Respuestas</th>
                <th>Acceso público</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($surveys as $survey): ?>
                <tr>
                    <td>
                        <strong><?= e($survey['name']) ?></strong><br>
                        <small><?= e($survey['slug']) ?></small>
                    </td>
                    <td><?= e($survey['status_label']) ?></td>
                    <td><?= e(Helpers::formatDateTime($survey['start_at'])) ?></td>
                    <td><?= e(Helpers::formatDateTime($survey['end_at'])) ?></td>
                    <td><?= (int) $survey['response_count'] ?></td>
                    <td><a href="<?= e($survey['public_url']) ?>" target="_blank" rel="noreferrer">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal" id="surveyModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3 id="surveyModalTitle">Nueva encuesta</h3>
                <p>Configure la ficha administrativa y la ventana pública.</p>
            </div>
            <button class="btn btn-secondary" type="button" data-close-modal>Cerrar</button>
        </div>
        <form id="surveyForm">
            <div class="modal-body stack">
                <input type="hidden" name="id" id="survey_id">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label>Nombre</label>
                        <input type="text" name="name" id="survey_name" required>
                    </div>
                    <div class="field">
                        <label>Slug público</label>
                        <input type="text" name="slug" id="survey_slug" placeholder="se-genera-si-se-deja-vacio">
                    </div>
                    <div class="field">
                        <label>Estado</label>
                        <select name="status" id="survey_status" required>
                            <option value="draft">Borrador</option>
                            <option value="scheduled">Programada</option>
                            <option value="active">Activa</option>
                            <option value="closed">Cerrada</option>
                            <option value="archived">Archivada</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Formulario público</label>
                        <select name="is_public" id="survey_is_public">
                            <option value="1">Sí, visible</option>
                            <option value="0">No, interno</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Fecha de inicio</label>
                        <input type="datetime-local" name="start_at" id="survey_start_at">
                    </div>
                    <div class="field">
                        <label>Fecha de cierre</label>
                        <input type="datetime-local" name="end_at" id="survey_end_at">
                    </div>
                </div>
                <div class="field">
                    <label>Descripción</label>
                    <textarea name="description" id="survey_description"></textarea>
                </div>
                <div class="field">
                    <label>Título de portada pública</label>
                    <input type="text" name="intro_title" id="survey_intro_title">
                </div>
                <div class="field">
                    <label>Mensaje introductorio</label>
                    <textarea name="intro_text" id="survey_intro_text"></textarea>
                </div>
                <div class="field">
                    <label>Mensaje final</label>
                    <textarea name="thank_you_text" id="survey_thank_you_text"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span class="chip chip-muted">La parametrización detallada se completa en el editor estructural.</span>
                <button class="btn btn-primary" type="submit">Guardar encuesta</button>
            </div>
        </form>
    </div>
</div>

<script>
const surveyData = <?= json_encode($surveys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const surveyForm = document.getElementById('surveyForm');
const surveyModal = document.getElementById('surveyModal');

function toDateTimeLocal(value) {
    if (!value || value === 'Sin registro') return '';
    const [date, time] = value.split(' ');
    return date && time ? `${date}T${time.slice(0, 5)}` : '';
}

function resetSurveyForm() {
    surveyForm.reset();
    document.getElementById('survey_id').value = '';
    document.getElementById('survey_status').value = 'draft';
    document.getElementById('survey_is_public').value = '1';
    document.getElementById('surveyModalTitle').textContent = 'Nueva encuesta';
}

document.getElementById('createSurveyButton').addEventListener('click', resetSurveyForm);

document.querySelectorAll('.js-edit-survey').forEach((button) => {
    button.addEventListener('click', () => {
        const survey = surveyData.find((item) => Number(item.id) === Number(button.dataset.id));
        if (!survey) return;

        document.getElementById('surveyModalTitle').textContent = 'Editar encuesta';
        document.getElementById('survey_id').value = survey.id;
        document.getElementById('survey_name').value = survey.name ?? '';
        document.getElementById('survey_slug').value = survey.slug ?? '';
        document.getElementById('survey_status').value = survey.status ?? 'draft';
        document.getElementById('survey_is_public').value = Number(survey.is_public) ? '1' : '0';
        document.getElementById('survey_start_at').value = toDateTimeLocal(survey.start_at);
        document.getElementById('survey_end_at').value = toDateTimeLocal(survey.end_at);
        document.getElementById('survey_description').value = survey.description ?? '';
        document.getElementById('survey_intro_title').value = survey.intro_title ?? '';
        document.getElementById('survey_intro_text').value = survey.intro_text ?? '';
        document.getElementById('survey_thank_you_text').value = survey.thank_you_text ?? '';
    });
});

surveyForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(surveyForm);
    const isEditing = Boolean(formData.get('id'));
    const response = await fetch('<?= url('api/admin/app.php?action=save_survey') ?>', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': formData.get('<?= e(CSRF_TOKEN_NAME) ?>')},
        body: formData,
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
        window.ShalomApp.notify('error', 'No se pudo guardar la encuesta', result.message || 'Revise la información e intente nuevamente.');
        return;
    }
    window.ShalomApp.queueToast({
        type: 'success',
        title: isEditing ? 'Encuesta actualizada' : 'Encuesta creada',
        message: 'Los cambios quedaron guardados en el panel administrativo.',
    });
    window.location.reload();
});

document.querySelectorAll('.js-delete-survey').forEach((button) => {
    button.addEventListener('click', async () => {
        const confirmed = await window.ShalomApp.confirm({
            title: 'Eliminar encuesta',
            message: 'Esta acción eliminará la encuesta, su estructura y respuestas registradas.',
            confirmText: 'Eliminar encuesta',
            confirmClass: 'btn btn-danger',
        });

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('id', button.dataset.id);
        formData.append('<?= e(CSRF_TOKEN_NAME) ?>', '<?= e(csrf_token()) ?>');

        const response = await fetch('<?= url('api/admin/app.php?action=delete_survey') ?>', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '<?= e(csrf_token()) ?>'},
            body: formData,
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            window.ShalomApp.notify('error', 'No se pudo eliminar la encuesta', result.message || 'Intente nuevamente.');
            return;
        }
        window.ShalomApp.queueToast({
            type: 'success',
            title: 'Encuesta eliminada',
            message: 'La encuesta y su estructura fueron retiradas del panel.',
        });
        window.location.reload();
    });
});
</script>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
