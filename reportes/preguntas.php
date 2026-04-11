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
$selectedQuestionCode = strtoupper(trim((string) ($_GET['question_code'] ?? '')));

$pageTitle = 'Análisis por pregunta';
$pageDescription = 'Buscador minimalista para seleccionar una pregunta y generar su análisis estadístico individual.';
$currentPage = 'question-analysis';
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => url('reportes/index.php')],
    ['title' => 'Análisis por pregunta'],
];

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Explorador por pregunta</h2>
            <p>Seleccione la encuesta, escriba una pregunta y genere el análisis solo cuando lo necesite.</p>
        </div>
    </div>
    <?php if ($surveyList === []): ?>
        <div class="empty-state">No hay encuestas disponibles para su usuario en este momento.</div>
    <?php else: ?>
        <form id="questionExplorerForm" class="report-filter-grid question-explorer-form">
            <input type="hidden" name="report_scope" id="question_report_scope" value="<?= e($selectedReportScope) ?>">
            <input type="hidden" name="question_code" id="question_code" value="<?= e($selectedQuestionCode) ?>">
            <div class="field">
                <label for="question_survey_id">Encuesta</label>
                <select name="survey_id" id="question_survey_id">
                    <?php foreach ($surveyList as $survey): ?>
                        <option value="<?= (int) $survey['id'] ?>" <?= $selectedSurveyId === (int) $survey['id'] ? 'selected' : '' ?>>
                            <?= e($survey['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="question_from">Desde</label>
                <input type="date" name="from" id="question_from" value="<?= e($_GET['from'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="question_to">Hasta</label>
                <input type="date" name="to" id="question_to" value="<?= e($_GET['to'] ?? '') ?>">
            </div>
            <div class="field question-search-field">
                <label for="questionSearchInput">Buscar pregunta</label>
                <input
                    type="search"
                    id="questionSearchInput"
                    placeholder="Código o fragmento del texto"
                    autocomplete="off"
                    spellcheck="false"
                >
                <small id="questionSearchMeta">Cargando catálogo de preguntas...</small>
                <div id="questionSearchResults" class="question-search-results" hidden></div>
            </div>
        </form>
        <div class="report-toolbar">
            <div class="actions-inline">
                <button class="btn btn-primary" type="button" id="generateQuestionAnalysisButton">Generar análisis</button>
                <button class="btn btn-secondary" type="button" id="clearQuestionSelectionButton">Limpiar selección</button>
            </div>
            <div class="segmented-actions" id="questionReportScopeButtons">
                <button class="segmented-button <?= $selectedReportScope === 'primary' ? 'active' : '' ?>" type="button" data-scope="primary">Dashboard principal</button>
                <button class="segmented-button <?= $selectedReportScope === 'special' ? 'active' : '' ?>" type="button" data-scope="special">Reporte aparte</button>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($surveyList !== []): ?>
    <section class="panel" id="questionAnalysisIntro">
        <div class="panel-header">
            <div>
                <h2>Selección bajo demanda</h2>
                <p id="questionAnalysisIntroText">Escriba el código o una parte del texto de la pregunta para abrir el análisis individual.</p>
            </div>
        </div>
        <div class="empty-state" id="questionAnalysisEmptyState">No se muestra contenido estadístico hasta que seleccione una pregunta.</div>
    </section>

    <div id="questionAnalysisContent" style="display:none;">
        <div class="grid-cards" id="questionSummaryCards"></div>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Hallazgos de la pregunta</h2>
                    <p>Lectura rápida del comportamiento, cobertura y perfil predominante.</p>
                </div>
            </div>
            <div id="questionHighlights" class="insights-grid"></div>
        </section>

        <section class="report-chart-grid">
            <article id="questionPrimaryAnalysisCard" class="chart-card report-chart-card"></article>
            <article id="questionSecondaryAnalysisCard" class="chart-card report-chart-card"></article>
        </section>

        <section class="panel" id="questionSegmentsPanel" style="display:none;">
            <div class="panel-header">
                <div>
                    <h2>Perfil de quienes respondieron</h2>
                    <p>Concentración por territorio, ubicación local y edad dentro de esta pregunta.</p>
                </div>
            </div>
            <div id="questionSegmentsGrid" class="question-segment-grid"></div>
        </section>
    </div>

    <script>
    let questionCatalog = [];
    let questionCatalogLoaded = false;
    let questionDistributionChart = null;

    const QUESTION_COLORS = [
        {solid: '#1e4d39', fill: 'rgba(30, 77, 57, 0.22)'},
        {solid: '#d6c29a', fill: 'rgba(214, 194, 154, 0.44)'},
        {solid: '#315f85', fill: 'rgba(49, 95, 133, 0.22)'},
        {solid: '#8aa37f', fill: 'rgba(138, 163, 127, 0.26)'},
        {solid: '#b56a1f', fill: 'rgba(181, 106, 31, 0.24)'},
        {solid: '#7b4f8f', fill: 'rgba(123, 79, 143, 0.22)'},
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

    function formatCount(value) {
        return Number(value || 0).toLocaleString('es-EC');
    }

    function formatPercentage(value) {
        return `${Number(value || 0).toFixed(1)}%`;
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

    function normalizeText(value = '') {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getForm() {
        return document.getElementById('questionExplorerForm');
    }

    function getSelectedQuestionCode() {
        return String(document.getElementById('question_code')?.value || '').trim().toUpperCase();
    }

    function setSelectedQuestionCode(value = '') {
        document.getElementById('question_code').value = String(value || '').trim().toUpperCase();
    }

    function setActiveScope(scope = 'primary') {
        document.querySelectorAll('#questionReportScopeButtons .segmented-button').forEach((button) => {
            button.classList.toggle('active', button.dataset.scope === scope);
        });
    }

    function syncQuestionUrlState() {
        const form = getForm();
        if (!form) {
            return;
        }

        const url = new URL(window.location.href);
        const params = new URLSearchParams(new FormData(form));

        ['from', 'to', 'question_code'].forEach((key) => {
            if (!String(params.get(key) || '').trim()) {
                params.delete(key);
            }
        });

        if ((params.get('report_scope') || 'primary') === 'primary') {
            params.delete('report_scope');
        }

        url.search = params.toString();
        window.history.replaceState({}, '', url);
    }

    function destroyQuestionChart() {
        questionDistributionChart?.destroy();
        questionDistributionChart = null;
    }

    function setLoadingState(isLoading) {
        const button = document.getElementById('generateQuestionAnalysisButton');
        if (!button) {
            return;
        }

        button.disabled = isLoading;
        button.textContent = isLoading ? 'Generando...' : 'Generar análisis';
    }

    function renderIntro(message) {
        destroyQuestionChart();
        document.getElementById('questionAnalysisContent').style.display = 'none';
        document.getElementById('questionSegmentsPanel').style.display = 'none';
        document.getElementById('questionSummaryCards').innerHTML = '';
        document.getElementById('questionHighlights').innerHTML = '';
        document.getElementById('questionPrimaryAnalysisCard').innerHTML = '';
        document.getElementById('questionSecondaryAnalysisCard').innerHTML = '';
        document.getElementById('questionSegmentsGrid').innerHTML = '';
        document.getElementById('questionAnalysisIntro').style.display = 'block';
        document.getElementById('questionAnalysisEmptyState').innerHTML = `<div>${escapeHtml(message)}</div>`;
    }

    function buildCatalogUrl() {
        const params = new URLSearchParams({
            survey_id: String(document.getElementById('question_survey_id').value || ''),
            report_scope: String(document.getElementById('question_report_scope').value || 'primary'),
        });

        return `<?= url('api/admin/app.php?action=question_catalog') ?>&${params.toString()}`;
    }

    function buildAnalysisUrl() {
        const params = new URLSearchParams(new FormData(getForm()));
        return `<?= url('api/admin/app.php?action=question_analysis') ?>&${params.toString()}`;
    }

    function buildQuestionLabel(question) {
        return `${question.code}. ${question.title}`;
    }

    function setSearchMeta(message) {
        const meta = document.getElementById('questionSearchMeta');
        if (meta) {
            meta.textContent = message;
        }
    }

    function hideSearchResults() {
        const container = document.getElementById('questionSearchResults');
        if (!container) {
            return;
        }
        container.hidden = true;
        container.innerHTML = '';
    }

    function findQuestionInCatalog(code = '') {
        const normalizedCode = String(code || '').trim().toUpperCase();
        return questionCatalog.find((question) => String(question.code || '').trim().toUpperCase() === normalizedCode) || null;
    }

    function renderSearchResults(searchTerm = '') {
        const container = document.getElementById('questionSearchResults');
        if (!container) {
            return;
        }

        const normalizedTerm = normalizeText(searchTerm);
        if (!normalizedTerm) {
            hideSearchResults();
            return;
        }

        const results = questionCatalog.filter((question) => {
            const haystack = normalizeText([
                question.code || '',
                question.title || '',
                question.section_title || '',
                question.type_label || '',
            ].join(' '));
            return haystack.includes(normalizedTerm);
        }).slice(0, 12);

        if (!results.length) {
            container.hidden = false;
            container.innerHTML = '<div class="question-search-empty">No hay preguntas que coincidan con la búsqueda.</div>';
            return;
        }

        container.hidden = false;
        container.innerHTML = results.map((question) => `
            <button
                type="button"
                class="question-search-result"
                data-question-code="${escapeHtml(question.code)}"
            >
                <strong>${escapeHtml(buildQuestionLabel(question))}</strong>
                <span>${escapeHtml(question.section_title || 'Sin sección')} · ${escapeHtml(question.type_label || 'Sin tipo')}</span>
            </button>
        `).join('');

        container.querySelectorAll('[data-question-code]').forEach((button) => {
            button.addEventListener('click', () => {
                const question = findQuestionInCatalog(button.dataset.questionCode || '');
                if (question) {
                    selectQuestion(question, true);
                }
            });
        });
    }

    function selectQuestion(question, autoAnalyze = false) {
        if (!question) {
            return;
        }

        setSelectedQuestionCode(question.code || '');
        document.getElementById('questionSearchInput').value = buildQuestionLabel(question);
        hideSearchResults();
        syncQuestionUrlState();

        if (autoAnalyze) {
            loadQuestionAnalysis();
        }
    }

    function clearQuestionSelection() {
        setSelectedQuestionCode('');
        document.getElementById('questionSearchInput').value = '';
        hideSearchResults();
        syncQuestionUrlState();
        renderIntro('No se muestra contenido estadístico hasta que seleccione una pregunta.');
    }

    async function loadQuestionCatalog() {
        questionCatalogLoaded = false;
        questionCatalog = [];
        setSearchMeta('Actualizando catálogo de preguntas...');
        hideSearchResults();

        const response = await fetch(buildCatalogUrl());
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'No se pudo cargar el catálogo de preguntas.');
        }

        questionCatalog = Array.isArray(result.data?.questions) ? result.data.questions : [];
        questionCatalogLoaded = true;
        setSearchMeta(`${formatCount(questionCatalog.length)} pregunta(s) disponibles en ${result.data?.report_scope_label || 'esta vista'}.`);

        const selectedQuestion = findQuestionInCatalog(getSelectedQuestionCode());
        if (selectedQuestion) {
            document.getElementById('questionSearchInput').value = buildQuestionLabel(selectedQuestion);
        } else if (getSelectedQuestionCode()) {
            clearQuestionSelection();
            notify('warning', 'Pregunta fuera de alcance', 'La pregunta seleccionada ya no pertenece a la vista activa.');
        }
    }

    function renderSummaryCards(data) {
        const summary = data.summary || {};
        const question = data.question || {};
        const cards = [
            {
                value: formatCount(summary.survey_responses),
                label: 'Respuestas del rango',
                foot: 'Base total disponible para esta lectura.',
            },
            {
                value: formatCount(summary.question_responses),
                label: 'Respuestas de la pregunta',
                foot: 'Registros que sí contestaron esta pregunta.',
            },
            {
                value: formatPercentage(summary.coverage_percentage),
                label: 'Cobertura',
                foot: `${escapeHtml(question.type_label || 'Pregunta')} en ${escapeHtml(question.section_title || 'Sin sección')}.`,
            },
            {
                value: `${escapeHtml(data.report_scope_label || 'Dashboard principal')}`,
                label: 'Vista analizada',
                foot: `${escapeHtml(question.code || '')} · ${escapeHtml(question.title || '')}`,
            },
        ];

        document.getElementById('questionSummaryCards').innerHTML = cards.map((card) => `
            <article class="card question-summary-card">
                <div class="metric-value">${card.value}</div>
                <div class="metric-label">${card.label}</div>
                <div class="metric-foot">${card.foot}</div>
            </article>
        `).join('');
    }

    function renderHighlights(data) {
        const highlights = Array.isArray(data.highlights) ? data.highlights : [];
        document.getElementById('questionHighlights').innerHTML = highlights.map((item) => `
            <article class="insight-card insight-${escapeHtml(item.tone || 'primary')}">
                <span class="chip chip-muted">${escapeHtml(item.title || 'Hallazgo')}</span>
                <div class="insight-value">${escapeHtml(item.value || 'Sin dato')}</div>
                <p>${escapeHtml(item.description || '')}</p>
            </article>
        `).join('');
    }

    function renderChoiceAnalysis(data) {
        const distribution = data.distribution || {};
        const options = Array.isArray(distribution.options) ? distribution.options : [];
        const topOption = distribution.top_option || null;
        const secondaryText = distribution.runner_up
            ? `Segunda opción: ${distribution.runner_up.label} con ${formatPercentage(distribution.runner_up.percentage)}.`
            : 'No hay una segunda opción con peso estadístico dentro del rango actual.';

        document.getElementById('questionPrimaryAnalysisCard').innerHTML = `
            <div class="report-chart-head">
                <span class="chip chip-muted">Distribución</span>
                <strong>${escapeHtml(data.question?.code || '')}. ${escapeHtml(data.question?.title || '')}</strong>
                <p>${escapeHtml(distribution.summary || 'Sin distribución disponible.')}</p>
            </div>
            <div class="report-canvas-wrap">
                <canvas id="questionDistributionChart" height="250"></canvas>
            </div>
        `;

        document.getElementById('questionSecondaryAnalysisCard').innerHTML = `
            <div class="report-chart-head">
                <span class="chip chip-muted">Detalle</span>
                <strong>Opciones y fuerza relativa</strong>
                <p>${escapeHtml(secondaryText)}</p>
            </div>
            <div class="question-option-list">
                ${options.map((option) => `
                    <div class="question-option-row">
                        <div>
                            <strong>${escapeHtml(option.label || 'Sin etiqueta')}</strong>
                            <small>${formatCount(option.count)} registro(s)</small>
                        </div>
                        <div class="question-option-metric">${formatPercentage(option.percentage)}</div>
                    </div>
                `).join('')}
            </div>
            ${topOption ? `
                <div class="question-analysis-note">
                    La opción líder es <strong>${escapeHtml(topOption.label || '')}</strong> con ${formatPercentage(topOption.percentage)}.
                    ${distribution.margin_percentage !== null && distribution.margin_percentage !== undefined
                        ? `La ventaja sobre la segunda opción es de ${formatPercentage(distribution.margin_percentage)}.`
                        : ''}
                </div>
            ` : ''}
        `;

        destroyQuestionChart();
        const chartElement = document.getElementById('questionDistributionChart');
        if (!chartElement || !window.Chart || !options.length) {
            return;
        }

        questionDistributionChart = new Chart(chartElement, {
            type: 'bar',
            data: {
                labels: options.map((option) => option.label || 'Sin etiqueta'),
                datasets: [{
                    label: 'Porcentaje',
                    data: options.map((option, index) => ({
                        x: option.label || 'Sin etiqueta',
                        y: Number(option.percentage || 0),
                        count: Number(option.count || 0),
                        backgroundColor: QUESTION_COLORS[index % QUESTION_COLORS.length].fill,
                        borderColor: QUESTION_COLORS[index % QUESTION_COLORS.length].solid,
                    })),
                    borderWidth: 1.5,
                    borderRadius: 10,
                }],
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                parsing: false,
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        callbacks: {
                            label: (context) => `${formatPercentage(context.raw?.y)} · ${formatCount(context.raw?.count)} registro(s)`,
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => `${value}%`,
                        },
                    },
                },
            },
        });
    }

    function renderTextAnalysis(data) {
        const distribution = data.distribution || {};
        const topAnswers = Array.isArray(distribution.top_answers) ? distribution.top_answers : [];
        const keywords = Array.isArray(distribution.keywords) ? distribution.keywords : [];
        const samples = Array.isArray(distribution.samples) ? distribution.samples : [];

        document.getElementById('questionPrimaryAnalysisCard').innerHTML = `
            <div class="report-chart-head">
                <span class="chip chip-muted">Texto abierto</span>
                <strong>Términos y repeticiones</strong>
                <p>${escapeHtml(distribution.summary || 'Sin lectura textual disponible.')}</p>
            </div>
            <div class="question-tag-cloud">
                ${keywords.length ? keywords.map((keyword) => `
                    <span class="question-tag-pill">${escapeHtml(keyword.word || '')} · ${formatCount(keyword.count)}</span>
                `).join('') : '<div class="empty-state">No hay palabras clave para mostrar.</div>'}
            </div>
        `;

        document.getElementById('questionSecondaryAnalysisCard').innerHTML = `
            <div class="report-chart-head">
                <span class="chip chip-muted">Detalle</span>
                <strong>Respuestas destacadas</strong>
                <p>Se muestran los textos más repetidos y algunas muestras recientes.</p>
            </div>
            <div class="question-text-block">
                <strong>Respuestas más repetidas</strong>
                <div class="question-option-list">
                    ${topAnswers.length ? topAnswers.map((answer) => `
                        <div class="question-option-row">
                            <div><strong>${escapeHtml(answer.label || '')}</strong></div>
                            <div class="question-option-metric">${formatCount(answer.count)}</div>
                        </div>
                    `).join('') : '<div class="empty-state">Las respuestas abiertas no tienen repeticiones relevantes.</div>'}
                </div>
            </div>
            <div class="question-text-block">
                <strong>Muestras</strong>
                <div class="question-sample-list">
                    ${samples.length ? samples.map((sample) => `<blockquote>${escapeHtml(sample)}</blockquote>`).join('') : '<div class="empty-state">No hay muestras disponibles.</div>'}
                </div>
            </div>
        `;

        destroyQuestionChart();
    }

    function renderMatrixAnalysis(data) {
        const rows = Array.isArray(data.distribution?.rows) ? data.distribution.rows : [];

        document.getElementById('questionPrimaryAnalysisCard').innerHTML = `
            <div class="report-chart-head">
                <span class="chip chip-muted">Matriz</span>
                <strong>Actividad por fila</strong>
                <p>${escapeHtml(data.distribution?.summary || 'Sin lectura matricial disponible.')}</p>
            </div>
            <div class="question-matrix-list">
                ${rows.length ? rows.map((row) => `
                    <div class="question-matrix-card">
                        <strong>${escapeHtml(row.label || row.code || 'Fila')}</strong>
                        <div class="question-matrix-dimensions">
                            ${(row.dimensions || []).slice(0, 3).map((dimension) => `
                                <span>${escapeHtml(dimension.label || dimension.code || 'Dimensión')} · ${formatCount(dimension.total)}</span>
                            `).join('')}
                        </div>
                    </div>
                `).join('') : '<div class="empty-state">No hay filas con actividad en esta matriz.</div>'}
            </div>
        `;

        document.getElementById('questionSecondaryAnalysisCard').innerHTML = `
            <div class="report-chart-head">
                <span class="chip chip-muted">Detalle</span>
                <strong>Lectura por dimensión</strong>
                <p>Cada bloque muestra la distribución interna de respuestas por dimensión.</p>
            </div>
            <div class="question-matrix-detail">
                ${rows.length ? rows.map((row) => `
                    <div class="question-matrix-card">
                        <strong>${escapeHtml(row.label || row.code || 'Fila')}</strong>
                        ${(row.dimensions || []).map((dimension) => `
                            <div class="question-matrix-dimension">
                                <header>
                                    <span>${escapeHtml(dimension.label || dimension.code || 'Dimensión')}</span>
                                    <small>${formatCount(dimension.total)} registro(s)</small>
                                </header>
                                <div class="question-option-list">
                                    ${(dimension.options || []).map((option) => `
                                        <div class="question-option-row">
                                            <div><strong>${escapeHtml(option.label || option.code || 'Valor')}</strong></div>
                                            <div class="question-option-metric">${formatPercentage(option.percentage)}</div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `).join('') : '<div class="empty-state">No hay detalle matricial para mostrar.</div>'}
            </div>
        `;

        destroyQuestionChart();
    }

    function renderSegments(data) {
        const segments = Object.values(data.demographics || {}).filter((segment) => segment && Array.isArray(segment.rows) && segment.rows.length);
        const panel = document.getElementById('questionSegmentsPanel');
        const grid = document.getElementById('questionSegmentsGrid');

        if (!segments.length) {
            panel.style.display = 'none';
            grid.innerHTML = '';
            return;
        }

        panel.style.display = 'block';
        grid.innerHTML = segments.map((segment) => `
            <article class="chart-card report-chart-card question-segment-card">
                <div class="report-chart-head">
                    <span class="chip chip-muted">${escapeHtml(segment.label || 'Perfil')}</span>
                    <strong>${escapeHtml(segment.top_label || 'Sin predominio')}</strong>
                    <p>${escapeHtml(segment.question_title || 'Sin pregunta asociada')}</p>
                </div>
                <div class="question-segment-meta">
                    ${segment.top_percentage !== null && segment.top_percentage !== undefined
                        ? `${formatPercentage(segment.top_percentage)} · ${formatCount(segment.top_count)} registro(s)`
                        : 'Sin registros suficientes.'}
                </div>
                <div class="question-segment-bars">
                    ${(segment.rows || []).slice(0, 6).map((row) => `
                        <div class="question-segment-row">
                            <div class="question-segment-labels">
                                <strong>${escapeHtml(row.label || 'Sin etiqueta')}</strong>
                                <span>${formatCount(row.count)} · ${formatPercentage(row.percentage)}</span>
                            </div>
                            <div class="question-segment-track">
                                <span style="width:${Math.max(4, Number(row.percentage || 0))}%"></span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </article>
        `).join('');
    }

    function renderAnalysis(data) {
        document.getElementById('questionAnalysisIntro').style.display = 'none';
        document.getElementById('questionAnalysisContent').style.display = 'block';

        renderSummaryCards(data);
        renderHighlights(data);

        if ((data.distribution?.kind || '') === 'choice') {
            renderChoiceAnalysis(data);
        } else if ((data.distribution?.kind || '') === 'text') {
            renderTextAnalysis(data);
        } else if ((data.distribution?.kind || '') === 'matrix') {
            renderMatrixAnalysis(data);
        } else {
            destroyQuestionChart();
            document.getElementById('questionPrimaryAnalysisCard').innerHTML = '<div class="empty-state">La pregunta no tiene respuestas suficientes dentro del filtro actual.</div>';
            document.getElementById('questionSecondaryAnalysisCard').innerHTML = `
                <div class="report-chart-head">
                    <span class="chip chip-muted">Estado</span>
                    <strong>Sin actividad</strong>
                    <p>No hay base estadística para construir la lectura de esta pregunta en el rango actual.</p>
                </div>
            `;
        }

        renderSegments(data);
    }

    async function loadQuestionAnalysis() {
        if (!questionCatalogLoaded) {
            notify('warning', 'Catálogo pendiente', 'Espere a que termine la carga del catálogo de preguntas.');
            return;
        }

        const questionCode = getSelectedQuestionCode();
        if (!questionCode) {
            notify('warning', 'Seleccione una pregunta', 'Primero elija una pregunta desde el buscador.');
            return;
        }

        setLoadingState(true);
        syncQuestionUrlState();

        try {
            const response = await fetch(buildAnalysisUrl());
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'No se pudo generar el análisis de la pregunta.');
            }

            renderAnalysis(result.data || {});
        } catch (error) {
            renderIntro(error.message || 'No fue posible generar el análisis en este momento.');
            notify('error', 'Error de análisis', error.message || 'Intente nuevamente.');
        } finally {
            setLoadingState(false);
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        const searchInput = document.getElementById('questionSearchInput');
        const surveyInput = document.getElementById('question_survey_id');
        const clearButton = document.getElementById('clearQuestionSelectionButton');
        const generateButton = document.getElementById('generateQuestionAnalysisButton');

        try {
            await loadQuestionCatalog();
        } catch (error) {
            setSearchMeta('No fue posible cargar el catálogo de preguntas.');
            notify('error', 'Error de catálogo', error.message || 'Intente nuevamente.');
            return;
        }

        const initialQuestion = findQuestionInCatalog(getSelectedQuestionCode());
        if (initialQuestion) {
            document.getElementById('questionSearchInput').value = buildQuestionLabel(initialQuestion);
            loadQuestionAnalysis();
        }

        searchInput?.addEventListener('input', (event) => {
            const typedValue = String(event.target.value || '');
            const selectedQuestion = findQuestionInCatalog(getSelectedQuestionCode());
            if (selectedQuestion && typedValue !== buildQuestionLabel(selectedQuestion)) {
                setSelectedQuestionCode('');
                syncQuestionUrlState();
            }
            renderSearchResults(typedValue);
        });

        searchInput?.addEventListener('focus', () => {
            renderSearchResults(searchInput.value || '');
        });

        searchInput?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            const selectedQuestion = findQuestionInCatalog(getSelectedQuestionCode());
            if (selectedQuestion) {
                loadQuestionAnalysis();
                return;
            }

            const exactMatch = questionCatalog.find((question) => normalizeText(buildQuestionLabel(question)) === normalizeText(searchInput.value || ''));
            if (exactMatch) {
                selectQuestion(exactMatch, true);
                return;
            }

            const firstResult = questionCatalog.find((question) => normalizeText([
                question.code || '',
                question.title || '',
                question.section_title || '',
            ].join(' ')).includes(normalizeText(searchInput.value || '')));
            if (firstResult) {
                selectQuestion(firstResult, true);
            }
        });

        document.addEventListener('click', (event) => {
            const results = document.getElementById('questionSearchResults');
            if (!results?.contains(event.target) && event.target !== searchInput) {
                hideSearchResults();
            }
        });

        surveyInput?.addEventListener('change', async () => {
            clearQuestionSelection();
            try {
                await loadQuestionCatalog();
            } catch (error) {
                notify('error', 'Error de catálogo', error.message || 'No se pudo actualizar el catálogo.');
            }
        });

        ['question_from', 'question_to'].forEach((id) => {
            document.getElementById(id)?.addEventListener('change', syncQuestionUrlState);
        });

        document.querySelectorAll('#questionReportScopeButtons .segmented-button').forEach((button) => {
            button.addEventListener('click', async () => {
                document.getElementById('question_report_scope').value = button.dataset.scope || 'primary';
                setActiveScope(button.dataset.scope || 'primary');
                clearQuestionSelection();
                try {
                    await loadQuestionCatalog();
                } catch (error) {
                    notify('error', 'Error de catálogo', error.message || 'No se pudo actualizar el catálogo.');
                }
            });
        });

        clearButton?.addEventListener('click', clearQuestionSelection);
        generateButton?.addEventListener('click', loadQuestionAnalysis);
        setActiveScope(document.getElementById('question_report_scope')?.value || 'primary');
    });
    </script>
<?php endif; ?>

<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
