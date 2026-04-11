<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireLogin();
auth()->requireInsightsAccess();

$surveyList = surveys()->listSurveyOptions(auth()->user());
$selectedSurveyId = (int) ($_GET['survey_id'] ?? ($surveyList[0]['id'] ?? 0));

$pageTitle = 'Homologación electoral';
$pageDescription = 'Tablero especializado para homologar papeletas de prefectura y alcaldías bajo bloques comparables.';
$currentPage = 'homologation';
$breadcrumbs = [['title' => 'Homologación electoral']];

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Centro de homologación</h2>
            <p>Unifique papeletas directas de prefectura y alcaldías para leer RC, ADN, Sí Podemos y otras fuerzas bajo un mismo criterio analítico.</p>
        </div>
    </div>
    <form id="homologationFilterForm" class="report-filter-grid">
        <div class="field">
            <label>Encuesta</label>
            <select name="survey_id" id="homologation_survey_id">
                <?php foreach ($surveyList as $survey): ?>
                    <option value="<?= (int) $survey['id'] ?>" <?= $selectedSurveyId === (int) $survey['id'] ? 'selected' : '' ?>>
                        <?= e($survey['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="from" id="homologation_from" value="<?= e($_GET['from'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="to" id="homologation_to" value="<?= e($_GET['to'] ?? '') ?>">
        </div>
        <div class="field" id="homologation_location_field" style="display:none;">
            <label id="homologation_location_label">Ciudad / cantón</label>
            <select name="location" id="homologation_location" disabled>
                <option value="all">Todos</option>
            </select>
        </div>
    </form>
    <div class="report-toolbar">
        <div class="actions-inline">
            <button class="btn btn-primary" type="button" id="loadHomologationButton">Actualizar tablero</button>
            <button class="btn btn-secondary" type="button" id="printHomologationButton">Imprimir</button>
        </div>
        <div class="segmented-actions" id="homologationQuickRangeButtons">
            <button class="segmented-button" type="button" data-range="7">7 días</button>
            <button class="segmented-button" type="button" data-range="30">30 días</button>
            <button class="segmented-button" type="button" data-range="all">Todo</button>
        </div>
    </div>
    <div class="homologation-method-note">
        <span class="chip chip-outline-light">Criterio de lectura</span>
        <p>Cuando la opción revela una marca política se agrupa por bloque homogéneo; si no la revela, se conserva como candidatura individual. Las papeletas de alcaldías se consolidan por familia A, B y C, y prefectura por familia A y B.</p>
    </div>
</section>

<div class="grid-cards" id="homologationSummaryCards"></div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Lectura unificada por dignidad</h2>
            <p>Vista ejecutiva consolidada para proyectar prefectura y alcaldías en una sola lectura ordenada.</p>
        </div>
    </div>
    <div id="homologationOfficeBoards" class="homologation-hero-grid"></div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Gráficos por papeleta homologada</h2>
            <p>Detalle ordenado por familia de papeleta para no perder la comparación fina detrás de la homologación general.</p>
        </div>
    </div>
    <div id="homologationFamilyGrid" class="homologation-family-grid"></div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Matriz operativa homologada</h2>
            <p>Tabla dinámica para revisar dignidad, papeleta, cobertura, liderazgo y distribución homologada bajo el mismo filtro territorial.</p>
        </div>
    </div>
    <div class="table-shell">
        <div id="homologationMatrixTable"></div>
        <div id="homologationMatrixFallback" class="table-wrap" style="display:none;"></div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Mapa de equivalencias</h2>
            <p>Transparencia completa de cómo se agruparon etiquetas históricas y actuales dentro de cada bloque homologado.</p>
        </div>
    </div>
    <div id="homologationEquivalenceGrid" class="homologation-equivalence-grid"></div>
</section>

<script>
let homologationCharts = [];
let homologationMatrixTable;
let lastHomologationPayload = null;

const HOMOLOGATION_FALLBACK_PALETTE = [
    {solid: '#315f85', fill: 'rgba(49, 95, 133, 0.22)'},
    {solid: '#8a5c2a', fill: 'rgba(138, 92, 42, 0.24)'},
    {solid: '#4d7a63', fill: 'rgba(77, 122, 99, 0.24)'},
    {solid: '#7b4f8f', fill: 'rgba(123, 79, 143, 0.24)'},
    {solid: '#9b3d3d', fill: 'rgba(155, 61, 61, 0.22)'},
];

const HOMOLOGATION_COLORS = {
    RC: {solid: '#8f1d3f', fill: 'rgba(143, 29, 63, 0.22)'},
    ADN: {solid: '#245f9b', fill: 'rgba(36, 95, 155, 0.22)'},
    SI_PODEMOS: {solid: '#1e4d39', fill: 'rgba(30, 77, 57, 0.22)'},
    CAMINANTES: {solid: '#b56a1f', fill: 'rgba(181, 106, 31, 0.24)'},
    AVANZA: {solid: '#7a5a2a', fill: 'rgba(122, 90, 42, 0.24)'},
    CONSTRUYE: {solid: '#75559b', fill: 'rgba(117, 85, 155, 0.22)'},
    NO_SABE: {solid: '#6f7a78', fill: 'rgba(111, 122, 120, 0.22)'},
    NULO: {solid: '#9a8360', fill: 'rgba(154, 131, 96, 0.22)'},
    OTRO: {solid: '#9b5b4d', fill: 'rgba(155, 91, 77, 0.22)'},
};

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

function buildEmptyState(message) {
    return `<div class="empty-state">${escapeHtml(message)}</div>`;
}

function formatCount(value) {
    return Number(value || 0).toLocaleString('es-EC');
}

function formatPercentage(value) {
    return `${Number(value || 0).toFixed(1)}%`;
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
        .replace(/^-+|-+$/g, '') || 'bloque';
}

function getFilters() {
    return new URLSearchParams(new FormData(document.getElementById('homologationFilterForm'))).toString();
}

function getHomologationColor(key, index = 0) {
    return HOMOLOGATION_COLORS[key] || HOMOLOGATION_FALLBACK_PALETTE[index % HOMOLOGATION_FALLBACK_PALETTE.length];
}

function setActiveQuickRange(activeButton = null) {
    document.querySelectorAll('#homologationQuickRangeButtons .segmented-button').forEach((button) => {
        button.classList.toggle('active', button === activeButton);
    });
}

function toDateInputValue(date) {
    const adjusted = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
    return adjusted.toISOString().slice(0, 10);
}

function applyQuickRange(range, button) {
    const fromInput = document.getElementById('homologation_from');
    const toInput = document.getElementById('homologation_to');
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
    loadHomologation();
}

function syncUrlState() {
    const url = new URL(window.location.href);
    const params = new URLSearchParams(new FormData(document.getElementById('homologationFilterForm')));

    ['from', 'to'].forEach((key) => {
        if (!String(params.get(key) || '').trim()) {
            params.delete(key);
        }
    });

    if ((params.get('location') || 'all') === 'all') {
        params.delete('location');
    }

    url.search = params.toString();
    window.history.replaceState({}, '', url);
}

function destroyHomologationCharts() {
    homologationCharts.forEach((chart) => chart.destroy());
    homologationCharts = [];
    homologationMatrixTable?.destroy();
    homologationMatrixTable = null;
    const matrixContainer = document.getElementById('homologationMatrixTable');
    const matrixFallback = document.getElementById('homologationMatrixFallback');
    if (matrixContainer) {
        matrixContainer.innerHTML = '';
    }
    if (matrixFallback) {
        matrixFallback.innerHTML = '';
        matrixFallback.style.display = 'none';
    }
}

function resetLocationFilterControl() {
    const field = document.getElementById('homologation_location_field');
    const label = document.getElementById('homologation_location_label');
    const select = document.getElementById('homologation_location');

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
    const field = document.getElementById('homologation_location_field');
    const label = document.getElementById('homologation_location_label');
    const select = document.getElementById('homologation_location');

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
        const suffix = count > 0 ? ` (${count})` : '';
        return `<option value="${escapeHtml(value)}">${escapeHtml(option.label || value)}${suffix}</option>`;
    }).join('');

    select.value = options.some((option) => String(option.value ?? 'all') === String(filter.selected_value ?? 'all'))
        ? String(filter.selected_value ?? 'all')
        : 'all';
    select.disabled = false;
    field.style.display = '';
}

function buildObservedWindow(summary, survey) {
    if (summary.first_submission_at && summary.last_submission_at) {
        return `${formatDate(summary.first_submission_at)} - ${formatDate(summary.last_submission_at)}`;
    }

    return survey?.status_label ? `${survey.status_label} · sin actividad en el filtro` : 'Sin actividad en el filtro';
}

function renderSummaryCards(data) {
    const container = document.getElementById('homologationSummaryCards');
    const summary = data.summary || {};
    const survey = data.survey || {};
    const offices = Array.isArray(data.offices) ? data.offices : [];
    const prefectura = offices.find((office) => office.key === 'prefectura') || null;
    const alcaldias = offices.find((office) => office.key === 'alcaldias') || null;

    container.innerHTML = `
        <article class="card report-summary-card">
            <div class="metric-value">${formatCount(summary.responses)}</div>
            <div class="metric-label">Respuestas analizadas</div>
            <div class="metric-foot">${escapeHtml(survey.name || 'Encuesta')}</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-value">${formatCount(summary.active_families)}</div>
            <div class="metric-label">Papeletas activas</div>
            <div class="metric-foot">${formatCount(summary.families)} familias homologadas detectadas</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-label">Filtro territorial</div>
            <div class="report-summary-main">${escapeHtml(summary.location_label || 'Todos')}</div>
            <div class="metric-foot">${data.location_filter?.enabled ? 'Se aplica al tablero completo' : 'Sin segmentación territorial disponible'}</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-label">Liderazgo prefectura</div>
            <div class="report-summary-main">${escapeHtml(prefectura?.top_option?.label || 'Sin datos')}</div>
            <div class="metric-foot">${prefectura?.top_option ? `${formatPercentage(prefectura.top_option.percentage)} (${formatCount(prefectura.top_option.count)})` : 'Sin lecturas suficientes'}</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-label">Liderazgo alcaldías</div>
            <div class="report-summary-main">${escapeHtml(alcaldias?.top_option?.label || 'Sin datos')}</div>
            <div class="metric-foot">${alcaldias?.top_option ? `${formatPercentage(alcaldias.top_option.percentage)} (${formatCount(alcaldias.top_option.count)})` : 'Sin lecturas suficientes'}</div>
        </article>
        <article class="card report-summary-card">
            <div class="metric-label">Ventana observada</div>
            <div class="report-summary-main">${escapeHtml(buildObservedWindow(summary, survey))}</div>
            <div class="metric-foot">Corte del tablero homologado</div>
        </article>
    `;
}

function renderOptionPills(options = [], limit = 4) {
    return options.slice(0, limit).map((option, index) => {
        const color = getHomologationColor(option.key, index);
        return `
            <span class="homologation-pill" style="--homologation-pill-border:${color.solid}; --homologation-pill-fill:${color.fill};">
                <strong>${escapeHtml(option.label || '')}</strong>
                <span>${escapeHtml(formatPercentage(option.percentage))} (${escapeHtml(formatCount(option.count))})</span>
            </span>
        `;
    }).join('');
}

function renderRankingList(options = []) {
    if (!options.length) {
        return buildEmptyState('Sin respuestas homologadas en este bloque.');
    }

    return `
        <div class="homologation-ranking">
            ${options.map((option, index) => {
                const color = getHomologationColor(option.key, index);
                return `
                    <div class="homologation-ranking-item">
                        <div class="homologation-ranking-head">
                            <strong>${escapeHtml(option.label || '')}</strong>
                            <span>${escapeHtml(formatPercentage(option.percentage))} · ${escapeHtml(formatCount(option.count))}</span>
                        </div>
                        <div class="homologation-progress">
                            <span style="width:${Math.min(Number(option.percentage || 0), 100)}%; background:${color.solid};"></span>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function mountChart(wrapper, chartId, rows) {
    if (!wrapper) {
        return null;
    }

    if (!window.Chart || !rows.length) {
        wrapper.innerHTML = buildEmptyState('Sin datos suficientes para construir el gráfico.');
        return null;
    }

    const minHeight = Math.max(250, rows.length * 52);
    wrapper.style.minHeight = `${minHeight}px`;
    wrapper.style.height = `${minHeight}px`;
    wrapper.innerHTML = `<canvas id="${chartId}"></canvas>`;
    return document.getElementById(chartId);
}

function renderOfficeBoards(data) {
    const container = document.getElementById('homologationOfficeBoards');
    const offices = Array.isArray(data.offices) ? data.offices : [];

    if (!offices.length) {
        container.innerHTML = buildEmptyState('No hay papeletas directas disponibles para construir la homologación electoral.');
        return;
    }

    container.innerHTML = offices.map((office) => `
        <article class="homologation-office-card">
            <div class="homologation-office-head">
                <div>
                    <span class="chip chip-muted">${escapeHtml(office.label || '')}</span>
                    <h3>${escapeHtml(office.label || '')}</h3>
                    <p>${escapeHtml(office.summary || '')}</p>
                </div>
                <div class="homologation-office-metrics">
                    <div>
                        <strong>${formatCount(office.responses)}</strong>
                        <span>participaciones</span>
                    </div>
                    <div>
                        <strong>${formatPercentage(office.average_coverage)}</strong>
                        <span>cobertura media</span>
                    </div>
                </div>
            </div>
            <div class="homologation-chart-wrap" id="office-chart-${escapeHtml(office.key || '')}"></div>
            ${renderRankingList(Array.isArray(office.options) ? office.options : [])}
        </article>
    `).join('');

    offices.forEach((office) => {
        const rows = Array.isArray(office.options) ? office.options : [];
        const wrapper = document.getElementById(`office-chart-${office.key}`);
        const canvas = mountChart(wrapper, `office-canvas-${office.key}`, rows);
        if (!canvas) {
            return;
        }

        homologationCharts.push(new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => shortLabel(row.label, 34)),
                datasets: [{
                    data: rows.map((row) => Number(row.percentage || 0)),
                    backgroundColor: rows.map((row, index) => getHomologationColor(row.key, index).fill),
                    borderColor: rows.map((row, index) => getHomologationColor(row.key, index).solid),
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
                            title: (items) => rows[items[0].dataIndex]?.label || '',
                            label: (context) => {
                                const row = rows[context.dataIndex];
                                return `${formatPercentage(context.parsed.x)} · ${formatCount(row.count)} participaciones`;
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
        }));
    });
}

function renderFamilyGrid(data) {
    const container = document.getElementById('homologationFamilyGrid');
    const families = Array.isArray(data.families) ? data.families : [];

    if (!families.length) {
        container.innerHTML = buildEmptyState('No hay familias de papeleta para desglosar en este momento.');
        return;
    }

    container.innerHTML = families.map((family) => `
        <article class="chart-card report-chart-card homologation-family-card">
            <div class="report-chart-head">
                <div class="report-meta-row">
                    <span class="chip chip-muted">${escapeHtml(family.office_label || '')}</span>
                    <span class="chip chip-muted">${escapeHtml(family.territory_label || '')}</span>
                    <span class="chip chip-muted">${formatCount(family.responses)} resp.</span>
                    <span class="chip chip-muted">${formatPercentage(family.coverage_percentage)} cobertura</span>
                </div>
                <strong>${escapeHtml(family.label || '')}</strong>
                <p>${escapeHtml(family.summary || 'Sin lectura homologada.')}</p>
            </div>
            <div class="homologation-chart-wrap homologation-chart-wrap-compact" id="family-chart-${escapeHtml(family.key || '')}"></div>
            <div class="homologation-family-meta">
                <span><strong>Preguntas:</strong> ${escapeHtml((family.question_codes || []).join(', ') || 'Sin código')}</span>
                <span><strong>Territorio:</strong> ${escapeHtml(family.territory_label || 'Sin territorio')}</span>
            </div>
            <div class="report-chart-footer">${renderOptionPills(Array.isArray(family.options) ? family.options : [], 5)}</div>
        </article>
    `).join('');

    families.forEach((family) => {
        const rows = Array.isArray(family.options) ? family.options : [];
        const wrapper = document.getElementById(`family-chart-${family.key}`);
        const canvas = mountChart(wrapper, `family-canvas-${family.key}`, rows.slice(0, 6));
        if (!canvas) {
            return;
        }

        const chartRows = rows.slice(0, 6);
        homologationCharts.push(new Chart(canvas, {
            type: 'bar',
            data: {
                labels: chartRows.map((row) => shortLabel(row.label, 28)),
                datasets: [{
                    data: chartRows.map((row) => Number(row.percentage || 0)),
                    backgroundColor: chartRows.map((row, index) => getHomologationColor(row.key, index).fill),
                    borderColor: chartRows.map((row, index) => getHomologationColor(row.key, index).solid),
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
                            title: (items) => `${family.label || ''}`,
                            label: (context) => {
                                const row = chartRows[context.dataIndex];
                                return `${row.label}: ${formatPercentage(context.parsed.x)} (${formatCount(row.count)})`;
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
        }));
    });
}

function buildDistributionHtml(options = []) {
    if (!options.length) {
        return '<span class="report-table-muted">Sin lectura homologada.</span>';
    }

    return `<div class="report-breakdown-list">${options.slice(0, 5).map((option, index) => {
        const color = getHomologationColor(option.key, index);
        return `
            <span class="homologation-pill" style="--homologation-pill-border:${color.solid}; --homologation-pill-fill:${color.fill};">
                <strong>${escapeHtml(option.label || '')}</strong>
                <span>${escapeHtml(formatPercentage(option.percentage))} (${escapeHtml(formatCount(option.count))})</span>
            </span>
        `;
    }).join('')}</div>`;
}

function renderMatrixFallback(rows) {
    const fallback = document.getElementById('homologationMatrixFallback');
    if (!fallback) {
        return;
    }

    if (!rows.length) {
        fallback.style.display = 'block';
        fallback.innerHTML = buildEmptyState('No hay filas suficientes para construir la matriz homologada.');
        return;
    }

    fallback.style.display = 'block';
    fallback.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Dignidad</th>
                    <th>Papeleta</th>
                    <th>Territorio</th>
                    <th>Preguntas agrupadas</th>
                    <th>Resp.</th>
                    <th>Cobertura</th>
                    <th>Lidera</th>
                    <th>Distribución homologada</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map((row) => `
                    <tr>
                        <td>${escapeHtml(row.office_label || '')}</td>
                        <td>${escapeHtml(row.label || '')}</td>
                        <td>${escapeHtml(row.territory_label || '')}</td>
                        <td>${escapeHtml(row.question_codes_text || '')}</td>
                        <td>${escapeHtml(formatCount(row.responses))}</td>
                        <td>${escapeHtml(formatPercentage(row.coverage_percentage))}</td>
                        <td>${escapeHtml(row.top_reading || 'Sin lectura')}</td>
                        <td>${buildDistributionHtml(row.options || [])}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderMatrixTable(data) {
    const rows = (Array.isArray(data.families) ? data.families : []).map((family) => ({
        ...family,
        question_codes_text: (family.question_codes || []).join(', '),
        top_reading: family.top_option
            ? `${family.top_option.label} · ${formatPercentage(family.top_option.percentage)} (${formatCount(family.top_option.count)})`
            : 'Sin lectura',
    }));

    if (!window.Tabulator) {
        renderMatrixFallback(rows);
        return;
    }

    if (!rows.length) {
        renderMatrixFallback(rows);
        return;
    }

    homologationMatrixTable = new Tabulator('#homologationMatrixTable', {
        data: rows,
        layout: 'fitColumns',
        responsiveLayout: 'collapse',
        placeholder: 'No hay filas suficientes para construir la matriz homologada.',
        initialSort: [{column: 'sort_order', dir: 'asc'}],
        columns: [
            {
                title: 'Dignidad',
                field: 'office_label',
                minWidth: 150,
            },
            {
                title: 'Papeleta',
                field: 'label',
                minWidth: 220,
                formatter: (cell) => {
                    const row = cell.getRow().getData();
                    return `
                        <div class="report-table-title">
                            <strong>${escapeHtml(cell.getValue())}</strong>
                            <small>${escapeHtml(row.territory_label || 'Sin territorio')}</small>
                        </div>
                    `;
                },
            },
            {
                title: 'Territorio',
                field: 'territory_label',
                minWidth: 140,
            },
            {
                title: 'Preguntas agrupadas',
                field: 'question_codes_text',
                minWidth: 220,
            },
            {
                title: 'Resp.',
                field: 'responses',
                width: 96,
                hozAlign: 'center',
                sorter: 'number',
                formatter: (cell) => `<strong>${formatCount(cell.getValue())}</strong>`,
            },
            {
                title: 'Cobertura',
                field: 'coverage_percentage',
                minWidth: 110,
                hozAlign: 'center',
                sorter: 'number',
                formatter: (cell) => formatPercentage(cell.getValue()),
            },
            {
                title: 'Lidera',
                field: 'top_reading',
                minWidth: 220,
            },
            {
                title: 'Distribución homologada',
                field: 'homologated_summary',
                minWidth: 340,
                formatter: (cell) => buildDistributionHtml(cell.getRow().getData().options || []),
            },
        ],
    });
}

function renderEquivalences(data) {
    const container = document.getElementById('homologationEquivalenceGrid');
    const groups = Array.isArray(data.equivalences) ? data.equivalences : [];

    if (!groups.length) {
        container.innerHTML = buildEmptyState('No hay equivalencias que mostrar para el filtro actual.');
        return;
    }

    container.innerHTML = groups.map((group) => `
        <article class="homologation-equivalence-card">
            <div class="report-chart-head">
                <span class="chip chip-muted">${escapeHtml(group.office_label || '')}</span>
                <strong>${escapeHtml(group.office_label || '')}</strong>
                <p>Alias y etiquetas absorbidas dentro de cada bloque homologado para esta dignidad.</p>
            </div>
            <div class="homologation-equivalence-list">
                ${(group.rows || []).length ? group.rows.map((row, index) => {
                    const color = getHomologationColor(row.key, index);
                    const aliases = Array.isArray(row.aliases) ? row.aliases : [];
                    return `
                        <div class="homologation-equivalence-item">
                            <div class="homologation-equivalence-head">
                                <span class="homologation-swatch" style="background:${color.solid};"></span>
                                <strong>${escapeHtml(row.label || '')}</strong>
                                <span>${escapeHtml(formatPercentage(row.percentage))} · ${escapeHtml(formatCount(row.count))}</span>
                            </div>
                            <div class="homologation-alias-list">
                                ${aliases.length ? aliases.map((alias) => `<span class="chip chip-outline-light">${escapeHtml(alias)}</span>`).join('') : '<span class="chip chip-muted">Sin alias adicionales</span>'}
                            </div>
                        </div>
                    `;
                }).join('') : buildEmptyState('Sin equivalencias activas.')}
            </div>
        </article>
    `).join('');
}

function renderHomologation(data) {
    renderLocationFilterControl(data.location_filter || null);
    renderSummaryCards(data);
    renderOfficeBoards(data);
    renderFamilyGrid(data);
    renderMatrixTable(data);
    renderEquivalences(data);
}

function renderEmptyState(message) {
    destroyHomologationCharts();
    lastHomologationPayload = null;
    resetLocationFilterControl();
    document.getElementById('homologationSummaryCards').innerHTML = `<article class="card report-summary-card"><div class="metric-label">${escapeHtml(message)}</div></article>`;
    document.getElementById('homologationOfficeBoards').innerHTML = buildEmptyState(message);
    document.getElementById('homologationFamilyGrid').innerHTML = buildEmptyState(message);
    document.getElementById('homologationMatrixFallback').style.display = 'block';
    document.getElementById('homologationMatrixFallback').innerHTML = buildEmptyState(message);
    document.getElementById('homologationEquivalenceGrid').innerHTML = buildEmptyState(message);
}

async function loadHomologation() {
    const surveyId = document.getElementById('homologation_survey_id')?.value;
    if (!surveyId) {
        renderEmptyState('No hay encuestas asignadas disponibles para generar la homologación electoral.');
        return;
    }

    const response = await fetch(`<?= url('api/admin/app.php?action=homologation_stats') ?>&${getFilters()}`);
    const result = await response.json();

    if (!response.ok || !result.success) {
        notify('error', 'No se pudo generar la homologación', result.message || 'Intente nuevamente.');
        return;
    }

    destroyHomologationCharts();
    lastHomologationPayload = result.data;
    syncUrlState();
    renderHomologation(result.data);
}

function bootHomologationPage() {
    document.getElementById('loadHomologationButton').addEventListener('click', loadHomologation);
    document.getElementById('printHomologationButton').addEventListener('click', () => window.print());
    document.getElementById('homologation_survey_id').addEventListener('change', () => {
        resetLocationFilterControl();
        loadHomologation();
    });
    document.getElementById('homologation_location').addEventListener('change', loadHomologation);
    document.getElementById('homologation_from').addEventListener('change', () => setActiveQuickRange(null));
    document.getElementById('homologation_to').addEventListener('change', () => setActiveQuickRange(null));

    document.querySelectorAll('#homologationQuickRangeButtons .segmented-button').forEach((button) => {
        button.addEventListener('click', () => applyQuickRange(button.dataset.range, button));
    });

    resetLocationFilterControl();
    loadHomologation();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootHomologationPage, {once: true});
} else {
    bootHomologationPage();
}
</script>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
