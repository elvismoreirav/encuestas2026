<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireLogin();
auth()->requireInsightsAccess();

$surveyList = surveys()->listSurveyOptions(auth()->user());
$selectedSurveyId = (int) ($_GET['survey_id'] ?? ($surveyList[0]['id'] ?? 0));
$selectedReportScope = strtolower(trim((string) ($_GET['report_scope'] ?? ''))) === 'special' ? 'special' : 'primary';

$pageTitle = 'Reportes';
$pageDescription = 'Analítica ejecutiva de encuestas con métricas, comparativos y hallazgos por pregunta.';
$currentPage = 'reports';
$breadcrumbs = [['title' => 'Reportes']];

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Centro analítico</h2>
            <p>Explore tendencias, coberturas y señales estadísticas para lectura ejecutiva y revisión detallada.</p>
        </div>
    </div>
    <form id="statsFilterForm" class="report-filter-grid">
        <input type="hidden" name="report_scope" id="stats_report_scope" value="<?= e($selectedReportScope) ?>">
        <div class="field">
            <label>Encuesta</label>
            <select name="survey_id" id="stats_survey_id">
                <?php foreach ($surveyList as $survey): ?>
                    <option value="<?= (int) $survey['id'] ?>" <?= $selectedSurveyId === (int) $survey['id'] ? 'selected' : '' ?>>
                        <?= e($survey['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="from" id="stats_from" value="<?= e($_GET['from'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="to" id="stats_to" value="<?= e($_GET['to'] ?? '') ?>">
        </div>
        <div class="field" id="stats_location_field" style="display:none;">
            <label id="stats_location_label">Ciudad / cantón</label>
            <select name="location" id="stats_location" disabled>
                <option value="all">Todos</option>
            </select>
        </div>
    </form>
    <div class="report-toolbar">
        <div class="actions-inline">
            <button class="btn btn-primary" type="button" id="loadStatsButton">Actualizar reporte</button>
            <button class="btn btn-secondary" type="button" id="exportStatsButton">Descargar resumen</button>
            <button class="btn btn-secondary" type="button" id="printReportButton">Imprimir</button>
        </div>
        <div class="segmented-actions" id="reportScopeButtons">
            <button class="segmented-button <?= $selectedReportScope === 'primary' ? 'active' : '' ?>" type="button" data-scope="primary">Dashboard principal</button>
            <button class="segmented-button <?= $selectedReportScope === 'special' ? 'active' : '' ?>" type="button" data-scope="special">Reporte aparte</button>
        </div>
        <div class="segmented-actions" id="quickRangeButtons">
            <button class="segmented-button" type="button" data-range="7">7 días</button>
            <button class="segmented-button" type="button" data-range="30">30 días</button>
            <button class="segmented-button" type="button" data-range="all">Todo</button>
        </div>
    </div>
</section>

<div class="grid-cards" id="statsSummaryCards"></div>

<section class="stats-grid">
    <article class="chart-card report-chart-card">
        <div class="report-chart-head">
            <span class="chip chip-muted">Territorio</span>
            <strong>Distribución por ciudad / cantón</strong>
            <p id="locationChartDescription">Participación registrada por ciudad o cantón dentro del rango aplicado.</p>
        </div>
        <div class="report-canvas-wrap" id="locationChartWrap">
            <canvas id="locationChart" height="240"></canvas>
        </div>
    </article>
    <article class="chart-card report-chart-card">
        <div class="report-chart-head">
            <span class="chip chip-muted">Visión temporal</span>
            <strong>Tendencia de respuestas</strong>
            <p>Evolución diaria del levantamiento dentro del filtro aplicado.</p>
        </div>
        <div class="report-canvas-wrap" id="trendChartWrap">
            <canvas id="trendChart" height="240"></canvas>
        </div>
    </article>
    <article class="chart-card report-chart-card">
        <div class="report-chart-head">
            <span class="chip chip-muted">Cobertura</span>
            <strong>Preguntas con mayor respuesta</strong>
            <p>Top de cobertura para identificar qué parte del instrumento responde mejor.</p>
        </div>
        <div class="report-canvas-wrap" id="coverageChartWrap">
            <canvas id="coverageChart" height="240"></canvas>
        </div>
    </article>
    <article class="chart-card report-chart-card">
        <div class="report-chart-head">
            <span class="chip chip-muted">Secciones</span>
            <strong>Desempeño por bloque</strong>
            <p>Cobertura media por sección para detectar áreas fuertes y débiles.</p>
        </div>
        <div class="report-canvas-wrap" id="sectionChartWrap">
            <canvas id="sectionChart" height="240"></canvas>
        </div>
    </article>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Hallazgos ejecutivos</h2>
            <p>Lectura rápida de la muestra, el periodo y los focos de mayor relevancia.</p>
        </div>
    </div>
    <div id="statsHighlights" class="insights-grid"></div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Gráficos por pregunta</h2>
            <p>Distribuciones comparables por pregunta cerrada, con porcentajes y cobertura.</p>
        </div>
    </div>
    <div id="questionCharts" class="report-chart-grid"></div>
</section>

<section class="panel" id="matrixPanel" style="display:none;">
    <div class="panel-header">
        <div>
            <h2>Matrices comparativas</h2>
            <p>Comparativos 100% apilados para percepción, intención y asociación por personaje o fila de matriz.</p>
        </div>
    </div>
    <div id="matrixInsights" class="stack"></div>
</section>

<section class="panel" id="textPanel" style="display:none;">
    <div class="panel-header">
        <div>
            <h2>Texto abierto</h2>
            <p>Palabras clave y respuestas destacadas para análisis cualitativo rápido.</p>
        </div>
    </div>
    <div id="textInsights" class="text-insight-grid"></div>
</section>

<script>
let locationChart;
let trendChart;
let coverageChart;
let sectionChart;
let dynamicCharts = [];
let lastStatsPayload = null;

const REPORT_PALETTE = [
    {solid: '#1e4d39', fill: 'rgba(30, 77, 57, 0.22)'},
    {solid: '#d6c29a', fill: 'rgba(214, 194, 154, 0.45)'},
    {solid: '#8aa37f', fill: 'rgba(138, 163, 127, 0.32)'},
    {solid: '#315f85', fill: 'rgba(49, 95, 133, 0.24)'},
    {solid: '#b56a1f', fill: 'rgba(181, 106, 31, 0.24)'},
    {solid: '#7b4f8f', fill: 'rgba(123, 79, 143, 0.24)'},
];

function notify(type, title, message) {
    if (window.ShalomApp?.notify) {
        window.ShalomApp.notify(type, title, message);
        return;
    }

    window.alert([title, message].filter(Boolean).join('\n'));
}

function escapeHtml(value = '') {
    return String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character] || character));
}

function formatDate(value) {
    if (!value) {
        return 'Sin registro';
    }

    return new Date(String(value).replace(' ', 'T')).toLocaleDateString('es-EC', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
}

function formatDateTime(value) {
    if (window.ShalomApp?.formatDateTime) {
        return window.ShalomApp.formatDateTime(value);
    }

    if (!value) {
        return 'Sin registro';
    }

    return new Date(String(value).replace(' ', 'T')).toLocaleString('es-EC', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatPercentage(value) {
    return `${Number(value || 0).toFixed(1)}%`;
}

function shortLabel(value, limit = 34) {
    const normalized = String(value ?? '');
    return normalized.length > limit ? `${normalized.slice(0, limit - 1)}…` : normalized;
}

function slugify(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'reporte';
}

function getStatsFilters() {
    return new URLSearchParams(new FormData(document.getElementById('statsFilterForm'))).toString();
}

function getSeriesColor(index) {
    return REPORT_PALETTE[index % REPORT_PALETTE.length];
}

function setActiveQuickRange(activeButton = null) {
    document.querySelectorAll('#quickRangeButtons .segmented-button').forEach((button) => {
        button.classList.toggle('active', button === activeButton);
    });
}

function setActiveReportScope(scope = 'primary') {
    document.querySelectorAll('#reportScopeButtons .segmented-button').forEach((button) => {
        button.classList.toggle('active', button.dataset.scope === scope);
    });
}

function syncReportUrlState() {
    const url = new URL(window.location.href);
    const params = new URLSearchParams(new FormData(document.getElementById('statsFilterForm')));

    ['from', 'to'].forEach((key) => {
        if (!String(params.get(key) || '').trim()) {
            params.delete(key);
        }
    });

    if ((params.get('location') || 'all') === 'all') {
        params.delete('location');
    }

    if ((params.get('report_scope') || 'primary') === 'primary') {
        params.delete('report_scope');
    }

    url.search = params.toString();
    window.history.replaceState({}, '', url);
}

function toDateInputValue(date) {
    const adjusted = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
    return adjusted.toISOString().slice(0, 10);
}

function applyQuickRange(range, button) {
    const fromInput = document.getElementById('stats_from');
    const toInput = document.getElementById('stats_to');
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

    setActiveQuickRange(button);
    loadStats();
}

function destroyCharts() {
    locationChart?.destroy();
    trendChart?.destroy();
    coverageChart?.destroy();
    sectionChart?.destroy();
    dynamicCharts.forEach((chart) => chart.destroy());
    dynamicCharts = [];
}

function buildEmptyState(message) {
    return `<div class="empty-state">${escapeHtml(message)}</div>`;
}

function resetLocationFilterControl() {
    const field = document.getElementById('stats_location_field');
    const label = document.getElementById('stats_location_label');
    const select = document.getElementById('stats_location');

    if (label) {
        label.textContent = 'Ciudad / cantón';
    }

    if (select) {
        select.innerHTML = '<option value="all">Todos</option>';
        select.value = 'all';
        select.disabled = true;
    }

    if (field) {
        field.style.display = 'none';
    }
}

function renderLocationFilterControl(filter) {
    const field = document.getElementById('stats_location_field');
    const label = document.getElementById('stats_location_label');
    const select = document.getElementById('stats_location');

    if (!field || !label || !select) {
        return;
    }

    if (!filter?.enabled) {
        resetLocationFilterControl();
        return;
    }

    label.textContent = filter.question_title || 'Ciudad / cantón';
    const options = Array.isArray(filter.options) && filter.options.length
        ? filter.options
        : [{value: 'all', label: filter.all_label || 'Todos', count: 0}];

    select.innerHTML = options.map((option) => {
        const value = String(option.value ?? 'all');
        const count = Number(option.count || 0);
        const countSuffix = count > 0 ? ` (${count})` : '';
        return `<option value="${escapeHtml(value)}">${escapeHtml(option.label || value)}${countSuffix}</option>`;
    }).join('');

    select.value = options.some((option) => String(option.value ?? 'all') === String(filter.selected_value ?? 'all'))
        ? String(filter.selected_value ?? 'all')
        : 'all';
    select.disabled = false;
    field.style.display = '';
}

function renderSummaryCards(summary, survey, locationFilter) {
    const observedWindow = summary.first_submission_at && summary.last_submission_at
        ? `${formatDate(summary.first_submission_at)} - ${formatDate(summary.last_submission_at)}`
        : 'Sin respuestas dentro del filtro actual';
    const hasLocationFilter = !!locationFilter?.enabled;
    const locationCard = hasLocationFilter
        ? `
            <article class="card report-summary-card">
                <div class="metric-label">${escapeHtml(locationFilter.question_title || 'Ciudad / cantón')}</div>
                <div class="report-summary-main">${escapeHtml(locationFilter.selected_label || 'Todos')}</div>
                <div class="metric-foot">${locationFilter.selected_value === 'all'
                    ? `${Number(locationFilter.active_option_count || 0)} ubicaciones con respuestas en el rango`
                    : `Filtro territorial activo · ${Number(locationFilter.active_option_count || 0)} ubicaciones con registros`}</div>
            </article>
        `
        : '';

    document.getElementById('statsSummaryCards').innerHTML = `
        <article class="card report-summary-card">
            <div class="metric-value">${summary.responses}</div>
            <div class="metric-label">Respuestas analizadas</div>
            <div class="metric-foot">${survey.name}</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-value">${summary.active_days}</div>
            <div class="metric-label">Días con actividad</div>
            <div class="metric-foot">${summary.responses ? 'Ventana efectiva con registros' : 'Sin actividad en el rango'}</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-value">${Number(summary.average_per_day || 0).toFixed(1)}</div>
            <div class="metric-label">Promedio diario</div>
            <div class="metric-foot">Respuestas por día con actividad</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-value">${summary.questions}</div>
            <div class="metric-label">Preguntas parametrizadas</div>
            <div class="metric-foot">${summary.sections} secciones en el instrumento</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-label">Vista activa</div>
            <div class="report-summary-main">${escapeHtml(summary.report_scope_label || 'Dashboard principal')}</div>
            <div class="metric-foot">${summary.report_scope === 'special'
                ? 'Preguntas etiquetadas fuera del tablero principal'
                : 'Preguntas estándar visibles en el tablero principal'}</div>
        </article>
        ${locationCard}
        <article class="card report-summary-card">
            <div class="metric-label">Ventana observada</div>
            <div class="report-summary-main">${escapeHtml(observedWindow)}</div>
            <div class="metric-foot">${escapeHtml(survey.status_label)} · Inicio ${escapeHtml(formatDateTime(survey.start_at))}</div>
        </article>
    `;
}

function renderHighlights(highlights) {
    const container = document.getElementById('statsHighlights');

    if (!highlights.length) {
        container.innerHTML = buildEmptyState('Todavía no existen suficientes respuestas para construir hallazgos ejecutivos.');
        return;
    }

    container.innerHTML = highlights.map((item) => `
        <article class="insight-card insight-${escapeHtml(item.tone || 'neutral')}">
            <span class="chip chip-muted">${escapeHtml(item.title)}</span>
            <div class="insight-value">${escapeHtml(item.value)}</div>
            <p>${escapeHtml(item.description || '')}</p>
        </article>
    `).join('');
}

function mountCanvas(wrapperId, canvasId) {
    const wrapper = document.getElementById(wrapperId);
    wrapper.innerHTML = `<canvas id="${canvasId}"></canvas>`;
    return document.getElementById(canvasId);
}

function renderLocationChart(data) {
    const wrapper = document.getElementById('locationChartWrap');
    const description = document.getElementById('locationChartDescription');
    const filter = data.location_filter || {};
    const rows = Array.isArray(data.location_distribution) ? data.location_distribution : [];

    if (description) {
        description.textContent = filter.enabled
            ? (filter.selected_value === 'all'
                ? 'Participación registrada por ciudad o cantón dentro del rango aplicado.'
                : `Distribución territorial del rango actual. El resto del reporte está filtrado por ${filter.selected_label}.`)
            : 'La encuesta actual no expone una primera pregunta utilizable como filtro territorial.';
    }

    if (!filter.enabled) {
        wrapper.innerHTML = buildEmptyState('La encuesta actual no tiene una primera pregunta disponible para segmentación por ciudad.');
        return;
    }

    if (!rows.length || !window.Chart) {
        wrapper.innerHTML = buildEmptyState('La distribución por ciudad o cantón aparecerá cuando existan respuestas dentro del rango seleccionado.');
        return;
    }

    const useHorizontal = rows.length > 6;
    const selectedValue = filter.selected_value && filter.selected_value !== 'all' ? filter.selected_value : null;
    const colors = rows.map((row, index) => {
        if (selectedValue && row.value === selectedValue) {
            return {
                solid: '#1e4d39',
                fill: 'rgba(30, 77, 57, 0.34)',
            };
        }

        return getSeriesColor(index + 2);
    });

    const canvas = mountCanvas('locationChartWrap', 'locationChart');
    locationChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((item) => shortLabel(item.label, useHorizontal ? 34 : 18)),
            datasets: [{
                data: rows.map((item) => Number(item.percentage || 0)),
                backgroundColor: colors.map((color) => color.fill),
                borderColor: colors.map((color) => color.solid),
                borderWidth: 1,
                borderRadius: 12,
            }],
        },
        options: {
            indexAxis: useHorizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false},
                tooltip: {
                    callbacks: {
                        title: (items) => rows[items[0].dataIndex]?.label || '',
                        label: (context) => {
                            const row = rows[context.dataIndex];
                            const value = useHorizontal ? context.parsed.x : context.parsed.y;
                            return `${formatPercentage(value)} · ${row.count} respuestas`;
                        },
                    },
                },
            },
            scales: {
                [useHorizontal ? 'x' : 'y']: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: (value) => `${value}%`,
                    },
                },
            },
        },
    });
}

function renderTrendChart(data) {
    const wrapper = document.getElementById('trendChartWrap');
    if (!data.time_series.length || !window.Chart) {
        wrapper.innerHTML = buildEmptyState('La tendencia se mostrará aquí cuando existan registros dentro del filtro.');
        return;
    }

    const canvas = mountCanvas('trendChartWrap', 'trendChart');
    trendChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.time_series.map((item) => formatDate(item.label)),
            datasets: [{
                label: 'Respuestas',
                data: data.time_series.map((item) => item.value),
                fill: true,
                borderColor: '#1e4d39',
                backgroundColor: 'rgba(30, 77, 57, 0.14)',
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 5,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false},
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {precision: 0},
                },
            },
        },
    });
}

function renderCoverageChart(data) {
    const wrapper = document.getElementById('coverageChartWrap');
    const coverageRows = (data.coverage || []).slice(0, 10);

    if (!coverageRows.length || !coverageRows.some((item) => Number(item.responses || 0) > 0) || !window.Chart) {
        wrapper.innerHTML = buildEmptyState('La cobertura por pregunta aparecerá cuando existan respuestas válidas.');
        return;
    }

    const canvas = mountCanvas('coverageChartWrap', 'coverageChart');
    coverageChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: coverageRows.map((item) => item.code),
            datasets: [{
                data: coverageRows.map((item) => Number(item.coverage_percentage || 0)),
                backgroundColor: coverageRows.map((_, index) => getSeriesColor(index).fill),
                borderColor: coverageRows.map((_, index) => getSeriesColor(index).solid),
                borderWidth: 1,
                borderRadius: 12,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false},
                tooltip: {
                    callbacks: {
                        title: (items) => {
                            const row = coverageRows[items[0].dataIndex];
                            return `${row.code}. ${row.title}`;
                        },
                        label: (context) => {
                            const row = coverageRows[context.dataIndex];
                            return `${formatPercentage(context.parsed.x)} · ${row.responses} respuestas · ${row.section_title}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: (value) => `${value}%`,
                    },
                },
            },
        },
    });
}

function renderSectionChart(data) {
    const wrapper = document.getElementById('sectionChartWrap');
    const sections = [...(data.section_stats || [])].sort((left, right) => right.average_coverage - left.average_coverage);

    if (!sections.length || !sections.some((item) => Number(item.response_sum || 0) > 0) || !window.Chart) {
        wrapper.innerHTML = buildEmptyState('El comparativo por secciones se mostrará cuando existan preguntas y respuestas.');
        return;
    }

    const canvas = mountCanvas('sectionChartWrap', 'sectionChart');
    sectionChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: sections.map((item) => shortLabel(item.title, 28)),
            datasets: [{
                data: sections.map((item) => Number(item.average_coverage || 0)),
                backgroundColor: sections.map((_, index) => getSeriesColor(index + 1).fill),
                borderColor: sections.map((_, index) => getSeriesColor(index + 1).solid),
                borderWidth: 1,
                borderRadius: 12,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false},
                tooltip: {
                    callbacks: {
                        title: (items) => sections[items[0].dataIndex].title,
                        label: (context) => {
                            const section = sections[context.dataIndex];
                            return `${formatPercentage(context.parsed.y)} promedio · ${section.question_count} preguntas`;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: (value) => `${value}%`,
                    },
                },
            },
        },
    });
}

function renderOptionPills(options, limit = 3) {
    return options.slice(0, limit).map((option) => `
        <span class="report-stat-pill">
            <strong>${escapeHtml(option.label)}</strong>
            <span>${formatPercentage(option.percentage)}</span>
        </span>
    `).join('');
}

function renderQuestionCharts(data) {
    const container = document.getElementById('questionCharts');
    const choiceQuestions = Object.values(data.question_stats || {}).filter((question) => question.type === 'choice' && Array.isArray(question.options) && question.options.length);

    if (!choiceQuestions.length) {
        container.innerHTML = buildEmptyState('No hay preguntas cerradas con respuestas válidas para construir gráficos.');
        return;
    }

    container.innerHTML = '';

    choiceQuestions.forEach((question) => {
        const article = document.createElement('article');
        article.className = 'chart-card report-chart-card';
        article.innerHTML = `
            <div class="report-chart-head">
                <div class="report-meta-row">
                    <span class="chip chip-muted">${escapeHtml(question.section_title)}</span>
                    <span class="chip chip-muted">${question.responses} respuestas</span>
                    <span class="chip chip-muted">${formatPercentage(question.coverage_percentage)} cobertura</span>
                </div>
                <strong>${escapeHtml(question.code)}. ${escapeHtml(question.title)}</strong>
                <p>Distribución porcentual de respuestas válidas para esta pregunta.</p>
            </div>
            <div class="report-canvas-wrap">
                <canvas></canvas>
            </div>
            <div class="report-chart-footer">${renderOptionPills(question.options)}</div>
        `;

        container.appendChild(article);

        if (!window.Chart) {
            article.querySelector('.report-canvas-wrap').innerHTML = buildEmptyState('Chart.js no está disponible en este momento.');
            return;
        }

        const chartCanvas = article.querySelector('canvas');
        const useHorizontal = question.options.length > 4;
        const colors = question.options.map((_, index) => getSeriesColor(index));
        const dataValues = question.options.map((option) => Number(option.percentage || 0));

        dynamicCharts.push(new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: question.options.map((option) => shortLabel(option.label, useHorizontal ? 40 : 20)),
                datasets: [{
                    data: dataValues,
                    backgroundColor: colors.map((color) => color.fill),
                    borderColor: colors.map((color) => color.solid),
                    borderWidth: 1,
                    borderRadius: 12,
                }],
            },
            options: {
                indexAxis: useHorizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        callbacks: {
                            title: (items) => `${question.code}. ${question.title}`,
                            label: (context) => {
                                const option = question.options[context.dataIndex];
                                const value = useHorizontal ? context.parsed.x : context.parsed.y;
                                return `${option.label}: ${formatPercentage(value)} (${option.count})`;
                            },
                        },
                    },
                },
                scales: {
                    [useHorizontal ? 'x' : 'y']: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: (value) => `${value}%`,
                        },
                    },
                },
            },
        }));
    });
}

function getMatrixDimensionOptions(detail, dimension) {
    if (Array.isArray(dimension.options) && dimension.options.length) {
        return dimension.options;
    }

    const optionCodes = {};
    Object.values(detail.matrix || {}).forEach((rowDimensions) => {
        Object.keys((rowDimensions || {})[dimension.code] || {}).forEach((optionCode) => {
            optionCodes[optionCode] = true;
        });
    });

    return Object.keys(optionCodes).map((optionCode) => ({
        code: optionCode,
        label: optionCode,
    }));
}

function renderMatrixInsights(data) {
    const panel = document.getElementById('matrixPanel');
    const container = document.getElementById('matrixInsights');
    const matrixQuestions = Object.values(data.question_stats || {}).filter((question) => question.type === 'matrix' && question.matrix_meta);

    if (!matrixQuestions.length) {
        panel.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    panel.style.display = '';
    container.innerHTML = '';

    matrixQuestions.forEach((question) => {
        const report = document.createElement('article');
        report.className = 'matrix-report';
        report.innerHTML = `
            <div class="report-chart-head">
                <div class="report-meta-row">
                    <span class="chip chip-muted">${escapeHtml(question.section_title)}</span>
                    <span class="chip chip-muted">${question.responses} registros</span>
                    <span class="chip chip-muted">${formatPercentage(question.coverage_percentage)} cobertura</span>
                </div>
                <strong>${escapeHtml(question.code)}. ${escapeHtml(question.title)}</strong>
                <p>Comparativo 100% apilado por dimensión para facilitar lectura entre personajes, opciones o filas de matriz.</p>
            </div>
            <div class="matrix-report-grid"></div>
        `;

        const grid = report.querySelector('.matrix-report-grid');
        const rows = Array.isArray(question.matrix_meta.rows) ? question.matrix_meta.rows : [];
        const dimensions = Array.isArray(question.matrix_meta.dimensions) ? question.matrix_meta.dimensions : [];

        dimensions.forEach((dimension) => {
            const options = getMatrixDimensionOptions(question, dimension);
            const datasets = options.map((option, index) => {
                const color = getSeriesColor(index);
                return {
                    label: option.label,
                    data: rows.map((row) => {
                        const distribution = (((question.matrix || {})[row.code] || {})[dimension.code]) || {};
                        const total = Object.values(distribution).reduce((sum, value) => sum + Number(value || 0), 0);
                        const optionValue = Number(distribution[option.code] || 0);
                        return total > 0 ? Number(((optionValue * 100) / total).toFixed(1)) : 0;
                    }),
                    backgroundColor: color.fill,
                    borderColor: color.solid,
                    borderWidth: 1,
                };
            }).filter((dataset) => dataset.data.some((value) => value > 0));

            const card = document.createElement('article');
            card.className = 'chart-card report-chart-card';
            card.innerHTML = `
                <div class="report-chart-head">
                    <span class="chip chip-muted">Dimensión</span>
                    <strong>${escapeHtml(dimension.label)}</strong>
                    <p>Distribución porcentual comparada por fila de la matriz.</p>
                </div>
                <div class="report-canvas-wrap"></div>
            `;

            if (!datasets.length || !window.Chart) {
                card.querySelector('.report-canvas-wrap').innerHTML = buildEmptyState('Sin datos suficientes para esta dimensión.');
                grid.appendChild(card);
                return;
            }

            const wrapper = card.querySelector('.report-canvas-wrap');
            wrapper.innerHTML = '<canvas></canvas>';
            const canvas = wrapper.querySelector('canvas');

            dynamicCharts.push(new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: rows.map((row) => shortLabel(row.label, 28)),
                    datasets,
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: (items) => rows[items[0].dataIndex]?.label || '',
                                label: (context) => `${context.dataset.label}: ${formatPercentage(context.parsed.x)}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: (value) => `${value}%`,
                            },
                        },
                        y: {
                            stacked: true,
                        },
                    },
                },
            }));

            grid.appendChild(card);
        });

        if (!grid.children.length) {
            grid.innerHTML = buildEmptyState('No hay suficientes datos para construir comparativos de matriz.');
        }

        container.appendChild(report);
    });
}

function renderTextInsights(data) {
    const panel = document.getElementById('textPanel');
    const container = document.getElementById('textInsights');
    const textQuestions = Object.values(data.question_stats || {}).filter((question) => question.type === 'text');

    if (!textQuestions.length) {
        panel.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    panel.style.display = '';
    container.innerHTML = textQuestions.map((question) => `
        <article class="text-insight-card">
            <div class="report-chart-head">
                <div class="report-meta-row">
                    <span class="chip chip-muted">${escapeHtml(question.section_title)}</span>
                    <span class="chip chip-muted">${question.responses} respuestas</span>
                    <span class="chip chip-muted">${formatPercentage(question.coverage_percentage)} cobertura</span>
                </div>
                <strong>${escapeHtml(question.code)}. ${escapeHtml(question.title)}</strong>
                <p>Señales cualitativas para lectura rápida del contenido abierto.</p>
            </div>
            <div>
                <h4>Palabras clave</h4>
                <div class="keyword-cloud">
                    ${(question.keywords || []).length
                        ? question.keywords.map((keyword) => `<span class="chip chip-warning">${escapeHtml(keyword.word)} (${keyword.count})</span>`).join('')
                        : '<span class="chip chip-muted">Sin palabras frecuentes</span>'}
                </div>
            </div>
            <div>
                <h4>Respuestas destacadas</h4>
                <div class="sample-list">
                    ${(question.samples || []).length
                        ? question.samples.map((sample) => `<blockquote class="sample-quote">${escapeHtml(sample)}</blockquote>`).join('')
                        : '<div class="empty-state">No hay muestras abiertas suficientes.</div>'}
                </div>
            </div>
        </article>
    `).join('');
}

function renderReport(data) {
    renderLocationFilterControl(data.location_filter || null);
    renderSummaryCards(data.summary, data.survey, data.location_filter || null);
    renderHighlights(data.highlights || []);
    renderLocationChart(data);
    renderTrendChart(data);
    renderCoverageChart(data);
    renderSectionChart(data);
    renderQuestionCharts(data);
    renderMatrixInsights(data);
    renderTextInsights(data);
}

function downloadStats() {
    if (!lastStatsPayload) {
        notify('warning', 'Reporte no disponible', 'Actualice el reporte antes de descargar el resumen.');
        return;
    }

    const surveyName = document.getElementById('stats_survey_id').selectedOptions[0]?.textContent || 'encuesta';
    const scopeSuffix = lastStatsPayload?.report_scope === 'special' ? '-reporte-aparte' : '-reporte-principal';
    const blob = new Blob([JSON.stringify(lastStatsPayload, null, 2)], {type: 'application/json;charset=utf-8'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${slugify(surveyName)}${scopeSuffix}.json`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);
}

function renderReportEmptyState(message) {
    destroyCharts();
    lastStatsPayload = null;
    resetLocationFilterControl();
    document.getElementById('statsSummaryCards').innerHTML = `<article class="card report-summary-card"><div class="metric-label">${escapeHtml(message)}</div></article>`;
    document.getElementById('statsHighlights').innerHTML = buildEmptyState(message);
    document.getElementById('questionCharts').innerHTML = buildEmptyState(message);
    document.getElementById('matrixPanel').style.display = 'none';
    document.getElementById('textPanel').style.display = 'none';
    document.getElementById('locationChartWrap').innerHTML = buildEmptyState(message);
    document.getElementById('trendChartWrap').innerHTML = buildEmptyState(message);
    document.getElementById('coverageChartWrap').innerHTML = buildEmptyState(message);
    document.getElementById('sectionChartWrap').innerHTML = buildEmptyState(message);
}

async function loadStats() {
    const surveyId = document.getElementById('stats_survey_id')?.value;
    if (!surveyId) {
        renderReportEmptyState('No hay encuestas asignadas disponibles para generar reportes.');
        return;
    }

    const response = await fetch(`<?= url('api/admin/app.php?action=stats') ?>&${getStatsFilters()}`);
    const result = await response.json();

    if (!response.ok || !result.success) {
        notify('error', 'No se pudo generar el reporte', result.message || 'Intente nuevamente.');
        return;
    }

    destroyCharts();
    lastStatsPayload = result.data;
    setActiveReportScope(result.data.report_scope || document.getElementById('stats_report_scope')?.value || 'primary');
    syncReportUrlState();
    renderReport(lastStatsPayload);
}

function bootReportsPage() {
    document.getElementById('loadStatsButton').addEventListener('click', loadStats);
    document.getElementById('exportStatsButton').addEventListener('click', downloadStats);
    document.getElementById('printReportButton').addEventListener('click', () => window.print());
    document.getElementById('stats_survey_id').addEventListener('change', () => {
        resetLocationFilterControl();
        loadStats();
    });
    document.getElementById('stats_location').addEventListener('change', loadStats);

    document.querySelectorAll('#quickRangeButtons .segmented-button').forEach((button) => {
        button.addEventListener('click', () => applyQuickRange(button.dataset.range, button));
    });
    document.querySelectorAll('#reportScopeButtons .segmented-button').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('stats_report_scope').value = button.dataset.scope || 'primary';
            setActiveReportScope(button.dataset.scope || 'primary');
            resetLocationFilterControl();
            loadStats();
        });
    });

    document.getElementById('stats_from').addEventListener('change', () => setActiveQuickRange(null));
    document.getElementById('stats_to').addEventListener('change', () => setActiveQuickRange(null));

    setActiveReportScope(document.getElementById('stats_report_scope')?.value || 'primary');
    resetLocationFilterControl();
    loadStats();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootReportsPage, {once: true});
} else {
    bootReportsPage();
}
</script>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
