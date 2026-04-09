<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireLogin();
auth()->requireInsightsAccess();

$surveyList = surveys()->listSurveys(auth()->user());
$pageTitle = 'Respuestas';
$pageDescription = 'Consulta el histórico de formularios completados y revisa el detalle de cada captura.';
$currentPage = 'responses';
$breadcrumbs = [['title' => 'Respuestas']];

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Filtros operativos</h2>
            <p>Revise respuestas por encuesta y rango de fechas.</p>
        </div>
    </div>
    <form id="responseFilterForm" class="form-grid-3">
        <div class="field">
            <label>Encuesta</label>
            <select name="survey_id" id="response_survey_id">
                <option value="">Todas</option>
                <?php foreach ($surveyList as $survey): ?>
                    <option value="<?= (int) $survey['id'] ?>" <?= ((int) ($_GET['survey_id'] ?? 0) === (int) $survey['id']) ? 'selected' : '' ?>>
                        <?= e($survey['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="from" id="response_from" value="<?= e($_GET['from'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="to" id="response_to" value="<?= e($_GET['to'] ?? '') ?>">
        </div>
    </form>
</section>

<div class="grid-cards" id="responseSummaryCards">
    <article class="card">
        <div class="metric-value">0</div>
        <div class="metric-label">Respuestas visibles</div>
        <div class="metric-foot">Aplique filtros para actualizar este resumen</div>
    </article>
    <article class="card">
        <div class="metric-value">0</div>
        <div class="metric-label">Encuestas cubiertas</div>
        <div class="metric-foot">Instrumentos presentes en el resultado actual</div>
    </article>
    <article class="card">
        <div class="metric-value">0</div>
        <div class="metric-label">Promedio de preguntas</div>
        <div class="metric-foot">Promedio de respuestas capturadas por formulario</div>
    </article>
    <article class="card">
        <div class="metric-value">-</div>
        <div class="metric-label">Última captura</div>
        <div class="metric-foot">Se actualizará cuando existan registros</div>
    </article>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Listado</h2>
            <p>Tabla enriquecida para revisión, exportación y trazabilidad.</p>
        </div>
    </div>
    <div class="report-toolbar">
        <div class="actions-inline">
            <button class="btn btn-primary" type="button" id="loadResponsesButton">Buscar respuestas</button>
            <button class="btn btn-link" type="button" id="clearResponseFiltersButton">Limpiar filtros</button>
        </div>
        <div class="segmented-actions" id="responseQuickRangeButtons">
            <button class="segmented-button" type="button" data-range="7">7 días</button>
            <button class="segmented-button" type="button" data-range="30">30 días</button>
            <button class="segmented-button" type="button" data-range="all">Todo</button>
        </div>
    </div>
    <div class="table-shell">
        <div id="responsesTable"></div>
        <div id="responsesFallback" class="table-wrap" style="display:none;"></div>
    </div>
</section>

<div class="modal" id="responseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Detalle de respuesta</h3>
                <p id="responseModalMeta">Cargando información...</p>
            </div>
            <button class="btn btn-secondary" type="button" data-close-modal>Cerrar</button>
        </div>
        <div class="modal-body">
            <div id="responseModalBody" class="stack"></div>
        </div>
    </div>
</div>

<script>
const responsesTableContainer = document.getElementById('responsesTable');
const responsesFallback = document.getElementById('responsesFallback');
const responseModal = document.getElementById('responseModal');
const responseModalMeta = document.getElementById('responseModalMeta');
const responseModalBody = document.getElementById('responseModalBody');
let responsesTable;

function formatDateTime(value) {
    if (window.ShalomApp?.formatDateTime) {
        return window.ShalomApp.formatDateTime(value);
    }

    if (!value) {
        return 'Sin fecha';
    }

    return new Date(value.replace(' ', 'T')).toLocaleString('es-EC', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDate(value) {
    if (!value) {
        return 'Sin fecha';
    }

    return new Date(String(value).replace(' ', 'T')).toLocaleDateString('es-EC', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
}

function notify(type, title, message) {
    if (window.ShalomApp?.notify) {
        window.ShalomApp.notify(type, title, message);
        return;
    }

    window.alert([title, message].filter(Boolean).join('\n'));
}

function getResponseFilters() {
    const form = document.getElementById('responseFilterForm');
    return new URLSearchParams(new FormData(form)).toString();
}

function toDateInputValue(date) {
    const adjusted = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
    return adjusted.toISOString().slice(0, 10);
}

function setActiveResponseQuickRange(activeButton = null) {
    document.querySelectorAll('#responseQuickRangeButtons .segmented-button').forEach((button) => {
        button.classList.toggle('active', button === activeButton);
    });
}

function applyResponseQuickRange(range, button) {
    const fromInput = document.getElementById('response_from');
    const toInput = document.getElementById('response_to');
    const today = new Date();

    if (range === 'all') {
        fromInput.value = '';
        toInput.value = '';
    } else {
        const days = Number(range);
        const fromDate = new Date(today);
        fromDate.setDate(today.getDate() - Math.max(days - 1, 0));
        fromInput.value = toDateInputValue(fromDate);
        toInput.value = toDateInputValue(today);
    }

    setActiveResponseQuickRange(button);
    loadResponses();
}

function renderResponseSummary(rows) {
    const container = document.getElementById('responseSummaryCards');
    if (!container) {
        return;
    }

    const total = rows.length;
    const surveys = new Set(rows.map((row) => row.survey_name)).size;
    const averageAnswers = total ? (rows.reduce((sum, row) => sum + Number(row.answer_count || 0), 0) / total).toFixed(1) : '0.0';
    const latestRow = rows.reduce((latest, row) => {
        if (!latest) {
            return row;
        }

        return new Date(String(row.submitted_at).replace(' ', 'T')) > new Date(String(latest.submitted_at).replace(' ', 'T')) ? row : latest;
    }, null);

    container.innerHTML = `
        <article class="card">
            <div class="metric-value">${total}</div>
            <div class="metric-label">Respuestas visibles</div>
            <div class="metric-foot">Registros devueltos por el filtro actual</div>
        </article>
        <article class="card">
            <div class="metric-value">${surveys}</div>
            <div class="metric-label">Encuestas cubiertas</div>
            <div class="metric-foot">Instrumentos presentes en la muestra filtrada</div>
        </article>
        <article class="card">
            <div class="metric-value">${averageAnswers}</div>
            <div class="metric-label">Promedio de preguntas</div>
            <div class="metric-foot">Respuestas registradas por formulario</div>
        </article>
        <article class="card">
            <div class="metric-value">${latestRow ? formatDate(latestRow.submitted_at) : '-'}</div>
            <div class="metric-label">Última captura</div>
            <div class="metric-foot">${latestRow ? `${escapeHtml(latestRow.survey_name)} · ${formatDateTime(latestRow.submitted_at)}` : 'No hay registros para mostrar'}</div>
        </article>
    `;
}

function renderFallbackTable(rows) {
    responsesFallback.style.display = 'block';
    if (!rows.length) {
        responsesFallback.innerHTML = '<div class="empty-state">No se encontraron respuestas con los filtros seleccionados.</div>';
        return;
    }

    responsesFallback.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Encuesta</th>
                    <th>Fecha</th>
                    <th>Preguntas</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map((row) => `
                    <tr>
                        <td>${row.response_uuid}</td>
                        <td>${row.survey_name}</td>
                        <td>${formatDateTime(row.submitted_at)}</td>
                        <td>${row.answer_count}</td>
                        <td><button class="btn btn-secondary js-view-response" data-id="${row.id}">Ver</button></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function handleResponseActionClick(event) {
    const button = event.target.closest('.js-view-response');
    if (!button) {
        return;
    }

    loadResponseDetail(button.dataset.id);
}

async function loadResponses() {
    const response = await fetch(`<?= url('api/admin/app.php?action=responses') ?>&${getResponseFilters()}`);
    const result = await response.json();

    if (!response.ok || !result.success) {
        notify('error', 'No se pudieron cargar las respuestas', result.message || 'Intente nuevamente.');
        return;
    }

    const rows = result.data;
    renderResponseSummary(rows);

    if (window.Tabulator) {
        responsesFallback.style.display = 'none';
        if (responsesTable) {
            responsesTable.replaceData(rows);
            return;
        }

        responsesTable = new Tabulator(responsesTableContainer, {
            data: rows,
            layout: 'fitColumns',
            responsiveLayout: 'collapse',
            placeholder: 'No se encontraron respuestas con los filtros seleccionados.',
            columns: [
                {
                    title: 'Código',
                    field: 'response_uuid',
                    minWidth: 260,
                    formatter: (cell) => `
                        <div class="response-code">
                            <strong>${cell.getValue()}</strong>
                            <span class="response-subtle">ID interno #${cell.getRow().getData().id}</span>
                        </div>
                    `,
                },
                {
                    title: 'Encuesta',
                    field: 'survey_name',
                    minWidth: 240,
                    formatter: (cell) => `
                        <div class="response-main">
                            <strong>${cell.getValue()}</strong>
                        </div>
                    `,
                },
                {
                    title: 'Fecha',
                    field: 'submitted_at',
                    minWidth: 190,
                    formatter: (cell) => `
                        <div class="response-main response-date">
                            <strong>${formatDateTime(cell.getValue())}</strong>
                        </div>
                    `,
                },
                {
                    title: 'Preguntas',
                    field: 'answer_count',
                    hozAlign: 'center',
                    formatter: (cell) => `
                        <div class="response-center">
                            <span class="response-count">${cell.getValue()}</span>
                        </div>
                    `,
                },
                {
                    title: 'Detalle',
                    headerSort: false,
                    hozAlign: 'center',
                    formatter: (cell) => `
                        <div class="response-action">
                            <button class="btn btn-secondary js-view-response" type="button" data-id="${cell.getRow().getData().id}">Ver</button>
                        </div>
                    `,
                    cellClick: (_event, cell) => loadResponseDetail(cell.getRow().getData().id),
                },
            ],
        });
        return;
    }

    renderFallbackTable(rows);
}

function renderAnswer(answer) {
    if (answer.answer_type === 'matrix') {
        const rows = answer.answer_json || {};
        return `
            <div class="stack">
                ${Object.entries(rows).map(([rowCode, dimensions]) => `
                    <div class="question-item">
                        <strong>${rowCode}</strong>
                        <div class="question-meta">
                            ${Object.entries(dimensions || {}).map(([dimensionCode, value]) => `<span class="chip chip-muted">${dimensionCode}: ${value}</span>`).join('')}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    if (Array.isArray(answer.options) && answer.options.length) {
        return answer.options.map((option) => `<span class="chip chip-muted">${option.option_label}</span>`).join('');
    }

    return `<p>${answer.answer_text || 'Sin valor registrado'}</p>`;
}

function accessEventLabel(eventType) {
    return eventType === 'submit' ? 'Envío' : 'Ingreso';
}

function renderCaptureSummary(detail) {
    const summary = detail.capture_summary || {};
    const accessLogs = Array.isArray(detail.access_logs) ? detail.access_logs : [];

    return `
        <article class="question-item capture-summary-card">
            <h4>Origen de captura</h4>
            <div class="question-meta">
                <span class="chip chip-muted">IP: ${escapeHtml(summary.ip_address || 'Sin registro')}</span>
                <span class="chip chip-muted">Dispositivo: ${escapeHtml(summary.device_type || 'unknown')}</span>
                <span class="chip chip-muted">Sistema: ${escapeHtml(summary.device_os || 'Sin registro')}</span>
                <span class="chip chip-muted">Navegador: ${escapeHtml(summary.browser || 'Sin registro')}</span>
                <span class="chip chip-muted">Pantalla: ${escapeHtml(summary.screen_resolution || 'Sin registro')}</span>
                <span class="chip chip-muted">Idioma: ${escapeHtml(summary.locale || 'Sin registro')}</span>
            </div>
            <div class="stack" style="margin-top:14px;">
                <div class="response-subtle">
                    Inicio: ${formatDateTime(summary.started_at)}<br>
                    Envío: ${formatDateTime(summary.submitted_at)}<br>
                    Referencia: ${escapeHtml(summary.referrer || 'Acceso directo')}<br>
                    Sesión: ${escapeHtml(summary.session_token || 'Sin token')}
                </div>
                ${accessLogs.length ? `
                    <div class="access-log-list">
                        ${accessLogs.map((log) => `
                            <div class="access-log-item">
                                <strong>${accessEventLabel(log.event_type)}</strong>
                                <span>${formatDateTime(log.occurred_at)}</span>
                                <span>${escapeHtml(log.device_type || 'unknown')}${log.device_os ? ` · ${escapeHtml(log.device_os)}` : ''}${log.browser ? ` · ${escapeHtml(log.browser)}` : ''}</span>
                                <span>${escapeHtml(log.ip_address || 'Sin IP')}</span>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        </article>
    `;
}

async function loadResponseDetail(id) {
    const response = await fetch(`<?= url('api/admin/app.php?action=response_detail') ?>&id=${id}`);
    const result = await response.json();
    if (!response.ok || !result.success) {
        notify('error', 'No se pudo cargar el detalle', result.message || 'Intente nuevamente.');
        return;
    }

    const detail = result.data;
    window.ShalomApp?.openModal(responseModal);
    responseModalMeta.textContent = `${detail.survey_name} | ${formatDateTime(detail.submitted_at)}`;
    responseModalBody.innerHTML = `
        ${renderCaptureSummary(detail)}
        ${detail.answers.map((answer) => `
        <article class="question-item">
            <h4>${answer.question_code}. ${answer.question_prompt}</h4>
            ${renderAnswer(answer)}
        </article>
    `).join('')}
    `;
}

function bootResponsesPage() {
    document.getElementById('loadResponsesButton').addEventListener('click', loadResponses);
    document.getElementById('clearResponseFiltersButton').addEventListener('click', () => {
        document.getElementById('response_survey_id').value = '';
        document.getElementById('response_from').value = '';
        document.getElementById('response_to').value = '';
        setActiveResponseQuickRange(null);
        loadResponses();
    });
    document.querySelectorAll('#responseQuickRangeButtons .segmented-button').forEach((button) => {
        button.addEventListener('click', () => applyResponseQuickRange(button.dataset.range || 'all', button));
    });
    responsesFallback.addEventListener('click', handleResponseActionClick);
    loadResponses();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootResponsesPage, {once: true});
} else {
    bootResponsesPage();
}
</script>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
