<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

$allSurveys = array_values(array_filter(surveys()->listSurveys(), static fn(array $survey): bool => (bool) $survey['is_public']));
$selectedSlug = trim((string) ($_GET['survey'] ?? ''));
$selectedSurvey = null;
$notFoundPublicSurvey = false;

if ($selectedSlug !== '') {
    $selectedSurvey = surveys()->getSurveyBySlug($selectedSlug);
    if ($selectedSurvey && !(bool) $selectedSurvey['is_public']) {
        $selectedSurvey = null;
    }
    $notFoundPublicSurvey = $selectedSurvey === null;
}

$canRespond = $selectedSurvey && (bool) $selectedSurvey['is_public'] && in_array($selectedSurvey['window_status'], ['active', 'closing_soon'], true);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($selectedSurvey['name'] ?? 'Encuestas públicas') ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script defer src="<?= asset('js/app.js') ?>"></script>
</head>
<body class="public-shell">
    <section class="public-hero">
        <div class="public-container">
            <?php if (!$selectedSurvey): ?>
                <div class="hero-card">
                    <span class="chip chip-muted">Encuestas públicas</span>
                    <div>
                        <h1>Seleccione una encuesta disponible</h1>
                        <p>El sistema permite múltiples encuestas activas, con ventanas de inicio y cierre, trazabilidad administrativa y experiencia pública optimizada.</p>
                    </div>
                    <?php if ($notFoundPublicSurvey): ?>
                        <div class="alert alert-danger">La encuesta solicitada no existe o no está publicada.</div>
                    <?php endif; ?>
                    <?php if ($allSurveys === []): ?>
                        <div class="empty-state">No existen encuestas públicas disponibles en este momento.</div>
                    <?php else: ?>
                        <div class="survey-grid">
                            <?php foreach ($allSurveys as $survey): ?>
                                <article class="survey-card">
                                    <span class="<?= e(str_contains($survey['window_status'], 'active') ? 'chip chip-success' : 'chip chip-warning') ?>">
                                        <?= e($survey['status_label']) ?>
                                    </span>
                                    <h3 style="margin-top:14px;"><?= e($survey['name']) ?></h3>
                                    <p><?= e($survey['description']) ?></p>
                                    <div class="actions-inline">
                                        <span class="chip chip-muted">Inicio: <?= e(Helpers::formatDateTime($survey['start_at'])) ?></span>
                                        <span class="chip chip-muted">Cierre: <?= e(Helpers::formatDateTime($survey['end_at'])) ?></span>
                                    </div>
                                    <div class="actions-inline" style="margin-top:14px;">
                                        <a class="btn btn-primary" href="<?= url('public/index.php?survey=' . urlencode($survey['slug'])) ?>">
                                            <?= in_array($survey['window_status'], ['active', 'closing_soon'], true) ? 'Responder ahora' : 'Ver detalle' ?>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="hero-card">
                    <div class="hero-meta">
                        <span class="<?= e($canRespond ? 'chip chip-success' : 'chip chip-warning') ?>"><?= e($selectedSurvey['status_label']) ?></span>
                        <span class="chip chip-muted">Inicio: <?= e(Helpers::formatDateTime($selectedSurvey['start_at'])) ?></span>
                        <span class="chip chip-muted">Cierre: <?= e(Helpers::formatDateTime($selectedSurvey['end_at'])) ?></span>
                    </div>
                    <div>
                        <h1><?= e($selectedSurvey['intro_title'] ?: $selectedSurvey['name']) ?></h1>
                        <p><?= e($selectedSurvey['intro_text'] ?: $selectedSurvey['description']) ?></p>
                    </div>
                    <?php if ($canRespond): ?>
                        <div class="survey-stepper" id="surveyStepper"></div>
                    <?php else: ?>
                        <div class="alert alert-danger">La encuesta no está disponible para respuestas en este momento.</div>
                    <?php endif; ?>
                </div>
                <?php if ($canRespond): ?>
                    <div id="surveyApp" class="stack" style="margin-top:20px;"></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

<?php if ($canRespond): ?>
<script>
const surveyData = <?= json_encode($selectedSurvey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const surveyApp = document.getElementById('surveyApp');
const surveyStepper = document.getElementById('surveyStepper');
const questionLookup = Object.fromEntries((surveyData.questions_flat || []).map((question) => [question.code, question]));
const visibilityDrivers = new Set(
    (surveyData.questions_flat || []).flatMap((question) =>
        (Array.isArray(question.visibility_rules) ? question.visibility_rules : []).map((rule) => rule.question_code)
    )
);
let currentStep = 0;
let answers = {};
let invalidQuestions = new Set();
let questionErrors = {};
const startedAt = new Date().toISOString();
const surveySessionToken = ensureSurveySessionToken();
let accessTracked = false;
let formMessage = defaultFormMessage();

function defaultFormMessage() {
    if (surveyData.window_status === 'closing_soon') {
        return {
            type: 'warning',
            title: 'Encuesta próxima a cerrar',
            message: 'Complete el formulario ahora para asegurar que su respuesta quede registrada dentro del periodo habilitado.',
        };
    }

    return null;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[character]));
}

function generateSessionToken() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `sess-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
}

function ensureSurveySessionToken() {
    const storageKey = `shalom-survey-session:${surveyData.slug}`;

    try {
        const existing = window.sessionStorage.getItem(storageKey);
        if (existing) {
            return existing;
        }

        const token = generateSessionToken();
        window.sessionStorage.setItem(storageKey, token);
        return token;
    } catch (error) {
        return generateSessionToken();
    }
}

function inferDeviceType() {
    const userAgent = navigator.userAgent || '';
    if (/ipad|tablet|playbook|silk|kindle|android(?!.*mobile)/i.test(userAgent)) {
        return 'tablet';
    }
    if (/mobi|iphone|ipod|android.*mobile|windows phone|blackberry/i.test(userAgent)) {
        return 'mobile';
    }
    return window.innerWidth <= 768 ? 'mobile' : 'desktop';
}

function buildClientMetadata() {
    return {
        sessionToken: surveySessionToken,
        userAgent: navigator.userAgent,
        deviceType: inferDeviceType(),
        screenResolution: `${window.screen.width}x${window.screen.height}`,
        viewport: `${window.innerWidth}x${window.innerHeight}`,
        locale: navigator.language,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        platform: navigator.userAgentData?.platform || navigator.platform || '',
        referrer: document.referrer || '',
    };
}

async function trackSurveyAccess() {
    if (accessTracked) {
        return;
    }

    accessTracked = true;

    try {
        await fetch('<?= url('api/public/app.php?action=track') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            keepalive: true,
            body: JSON.stringify({
                survey: surveyData.slug,
                metadata: buildClientMetadata(),
            }),
        });
    } catch (error) {
        // Tracking failures should not block the survey.
    }
}

function getVisibleSections() {
    return surveyData.sections
        .map((section) => ({
            ...section,
            questions: section.questions.filter((question) => isQuestionVisible(question)),
        }))
        .filter((section) => section.questions.length > 0);
}

function isQuestionVisible(question) {
    const rules = Array.isArray(question.visibility_rules) ? question.visibility_rules : [];
    if (!rules.length) return true;
    return rules.every((rule) => {
        const actual = answers[rule.question_code] ?? null;
        if (rule.operator === 'not_equals') return actual !== rule.value;
        if (rule.operator === 'contains') return Array.isArray(actual) && actual.includes(rule.value);
        return Array.isArray(actual) ? actual.includes(rule.value) : String(actual ?? '') === String(rule.value ?? '');
    });
}

function shouldRefreshForVisibility(questionCode) {
    return visibilityDrivers.has(questionCode);
}

function setFormMessage(type, title, message) {
    formMessage = {type, title, message};
    updateFormMessage();
}

function updateFormMessage() {
    const topContainer = document.getElementById('surveyMessage');
    if (topContainer) {
        topContainer.innerHTML = renderFormMessage();
    }

    const floatingContainer = document.getElementById('surveyFloatingMessage');
    if (floatingContainer) {
        floatingContainer.innerHTML = renderFormMessage();
    }
}

function renderFormMessage() {
    if (!formMessage) {
        return '';
    }

    const icons = {
        danger: '!',
        warning: '!',
        success: '✓',
    };

    return `
        <div class="form-alert form-alert-${escapeHtml(formMessage.type)}" role="alert">
            <div class="form-alert-icon">${icons[formMessage.type] || 'i'}</div>
            <div>
                <strong>${escapeHtml(formMessage.title)}</strong>
                <p>${escapeHtml(formMessage.message)}</p>
                ${Array.isArray(formMessage.details) && formMessage.details.length ? `
                    <ul class="form-alert-list">
                        ${formMessage.details.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}
                    </ul>
                ` : ''}
            </div>
        </div>
    `;
}

function questionTitle(code) {
    const question = questionLookup[code];
    if (!question) {
        return code;
    }

    return `${question.code}. ${question.prompt}`;
}

function buildMissingQuestionDetails(codes) {
    return codes.map((code) => questionTitle(code));
}

function renderSectionBanner(section, sections) {
    return `
        <section class="section-banner">
            <div class="hero-meta">
                <span class="chip chip-muted">Paso ${currentStep + 1} de ${sections.length}</span>
                <span class="chip chip-muted">${section.questions.length} preguntas visibles</span>
            </div>
            <div>
                <h2>${escapeHtml(section.title)}</h2>
                <p>${escapeHtml(section.description || 'Responda esta sección para continuar con el levantamiento.')}</p>
            </div>
        </section>
    `;
}

function renderStepper(sections) {
    surveyStepper.innerHTML = sections.map((section, index) => `
        <div class="survey-step ${index === currentStep ? 'active' : ''}">
            <strong>Paso ${index + 1}</strong><br>
            <span>${escapeHtml(section.title)}</span>
        </div>
    `).join('');
}

function clearQuestionState(questionCode) {
    if (!invalidQuestions.has(questionCode)) {
        return;
    }

    invalidQuestions.delete(questionCode);
    delete questionErrors[questionCode];
    const card = document.querySelector(`[data-question="${questionCode}"]`);
    card?.classList.remove('is-invalid');
    card?.querySelector('.question-error')?.remove();

    if (invalidQuestions.size === 0 && formMessage?.type === 'danger') {
        formMessage = defaultFormMessage();
        updateFormMessage();
    }
}

function renderChoiceCard(question, option, config = {}) {
    const type = config.type || 'radio';
    const selected = !!config.selected;
    const classes = ['choice-card'];

    if (type === 'checkbox') {
        classes.push('checkbox-card');
    }

    if (config.variant === 'rating') {
        classes.push('rating-card');
    }

    return `
        <label class="${classes.join(' ')}">
            <input
                class="choice-input"
                type="${type}"
                name="${escapeHtml(question.code)}"
                value="${escapeHtml(option.code)}"
                data-question-code="${escapeHtml(question.code)}"
                ${selected ? 'checked' : ''}
            >
            <span class="choice-indicator" aria-hidden="true"></span>
            <span class="choice-body">
                ${config.variant === 'rating' ? `<span class="rating-scale">Escala ${config.index + 1}</span>` : ''}
                <span class="choice-label">${escapeHtml(option.label)}</span>
                ${config.caption ? `<span class="choice-caption">${escapeHtml(config.caption)}</span>` : ''}
            </span>
        </label>
    `;
}

function renderSingleChoice(question) {
    const containerClass = question.question_type === 'rating' ? 'rating-grid' : 'option-grid columns-2';
    return `
        <div class="${containerClass}">
            ${question.options.map((option, index) => `
                ${renderChoiceCard(question, option, {
                    selected: answers[question.code] === option.code,
                    variant: question.question_type === 'rating' ? 'rating' : 'choice',
                    index
                })}
            `).join('')}
        </div>
    `;
}

function renderMultipleChoice(question) {
    const current = Array.isArray(answers[question.code]) ? answers[question.code] : [];
    return `
        <div class="option-grid columns-2">
            ${question.options.map((option) => `
                ${renderChoiceCard(question, option, {
                    type: 'checkbox',
                    selected: current.includes(option.code)
                })}
            `).join('')}
        </div>
    `;
}

function renderMatrix(question) {
    const matrix = question.settings?.matrix || {rows: [], dimensions: []};
    const gridStyle = `style="grid-template-columns: 220px repeat(${matrix.dimensions.length}, minmax(0, 1fr));"`;

    return `
        <div class="matrix-shell">
            <div class="matrix-helper">
                <span class="chip chip-muted">1 fila = 1 personaje político</span>
                <span class="chip chip-muted">Estructura tipo matriz como referencia de encuesta Manabí</span>
            </div>
            <div class="matrix-head-grid" ${gridStyle}>
                <div class="matrix-head">Personaje</div>
                ${matrix.dimensions.map((dimension) => `<div class="matrix-head">${escapeHtml(dimension.label)}</div>`).join('')}
            </div>
            <div class="matrix-rows">
                ${matrix.rows.map((row, rowIndex) => `
                    <div class="matrix-table-row" ${gridStyle}>
                        <div class="matrix-candidate">
                            <span class="matrix-order">${String(rowIndex + 1).padStart(2, '0')}</span>
                            <strong>${escapeHtml(row.label)}</strong>
                        </div>
                        ${matrix.dimensions.map((dimension) => `
                            <div class="matrix-cell">
                                <span class="matrix-cell-label">${escapeHtml(dimension.label)}</span>
                                <div class="matrix-options">
                                    ${(dimension.options || []).map((option, optionIndex) => `
                                        <label class="matrix-option">
                                            <input
                                                class="choice-input"
                                                type="radio"
                                                name="${escapeHtml(question.code)}__${escapeHtml(row.code)}__${escapeHtml(dimension.code)}"
                                                value="${escapeHtml(option.code)}"
                                                data-question-code="${escapeHtml(question.code)}"
                                                data-matrix-question="${escapeHtml(question.code)}"
                                                data-row="${escapeHtml(row.code)}"
                                                data-dimension="${escapeHtml(dimension.code)}"
                                                ${answers[question.code]?.[row.code]?.[dimension.code] === option.code ? 'checked' : ''}
                                            >
                                            <span class="matrix-option-badge">${String.fromCharCode(65 + optionIndex)}</span>
                                            <span class="matrix-option-label">${escapeHtml(option.label)}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function renderQuestion(question) {
    let body = '';

    if (question.question_type === 'single_choice' || question.question_type === 'rating') {
        body = renderSingleChoice(question);
    } else if (question.question_type === 'multiple_choice') {
        body = renderMultipleChoice(question);
    } else if (question.question_type === 'matrix') {
        body = renderMatrix(question);
    } else {
        const tag = question.question_type === 'textarea' ? 'textarea' : 'input';
        const value = answers[question.code] ?? '';
        if (tag === 'textarea') {
            body = `<textarea class="public-control public-textarea" name="${escapeHtml(question.code)}" placeholder="${escapeHtml(question.placeholder || '')}">${escapeHtml(value)}</textarea>`;
        } else {
            body = `<input class="public-control" type="text" name="${escapeHtml(question.code)}" value="${escapeHtml(value)}" placeholder="${escapeHtml(question.placeholder || '')}">`;
        }
    }

    return `
        <article class="question-card ${invalidQuestions.has(question.code) ? 'is-invalid' : ''}" data-question="${question.code}">
            <div class="question-head">
                <div>
                    <div class="question-kicker">${escapeHtml(question.code)}</div>
                    <h3>${escapeHtml(question.prompt)}</h3>
                    ${question.help_text ? `<p>${escapeHtml(question.help_text)}</p>` : ''}
                </div>
                <span class="question-badge ${question.is_required ? 'question-badge-required' : 'question-badge-optional'}">
                    ${question.is_required ? 'Obligatoria' : 'Opcional'}
                </span>
            </div>
            ${body}
            ${invalidQuestions.has(question.code) ? `<div class="question-error">${escapeHtml(questionErrors[question.code] || 'Debe completar esta pregunta para continuar.')}</div>` : ''}
        </article>
    `;
}

function validateStep(section) {
    const nextInvalidQuestions = new Set();
    const nextQuestionErrors = {};
    let firstInvalid = null;

    for (const question of section.questions) {
        if (!question.is_required) continue;

        const value = answers[question.code];
        let valid = true;

        if (question.question_type === 'multiple_choice') {
            valid = Array.isArray(value) && value.length > 0;
        } else if (question.question_type === 'matrix') {
            const rows = question.settings?.matrix?.rows || [];
            const dimensions = question.settings?.matrix?.dimensions || [];
            valid = rows.every((row) => dimensions.every((dimension) => !!answers[question.code]?.[row.code]?.[dimension.code]));
        } else {
            valid = value !== undefined && value !== null && String(value).trim() !== '';
        }

        if (!valid) {
            nextInvalidQuestions.add(question.code);
            nextQuestionErrors[question.code] = 'Esta pregunta es obligatoria y aún no ha sido respondida.';
            if (!firstInvalid) {
                firstInvalid = question.code;
            }
        }
    }

    invalidQuestions = nextInvalidQuestions;
    questionErrors = nextQuestionErrors;

    if (firstInvalid) {
        formMessage = {
            type: 'danger',
            title: 'Faltan respuestas obligatorias',
            message: 'Revise las tarjetas marcadas y complete la sección antes de continuar.',
            details: buildMissingQuestionDetails(Array.from(nextInvalidQuestions)),
        };
        renderForm();
        requestAnimationFrame(() => {
            document.querySelector(`[data-question="${firstInvalid}"]`)?.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
        return false;
    }

    formMessage = defaultFormMessage();
    updateFormMessage();
    return true;
}

function renderForm() {
    const sections = getVisibleSections();
    if (!sections.length) {
        surveyApp.innerHTML = '<div class="empty-state">No hay preguntas visibles para esta encuesta en este momento.</div>';
        return;
    }

    currentStep = Math.max(0, Math.min(currentStep, sections.length - 1));
    const section = sections[currentStep];
    renderStepper(sections);

    surveyApp.innerHTML = `
        <form class="survey-form" id="publicSurveyForm">
            <div id="surveyMessage">${renderFormMessage()}</div>
            ${renderSectionBanner(section, sections)}
            ${section.questions.map((question) => renderQuestion(question)).join('')}
            <div class="form-actions">
                <button class="btn btn-secondary" type="button" id="prevButton" ${currentStep === 0 ? 'disabled' : ''}>Atrás</button>
                ${currentStep < sections.length - 1
                    ? '<button class="btn btn-primary" type="button" id="nextButton">Siguiente</button>'
                    : '<button class="btn btn-primary" type="submit" id="submitButton">Enviar encuesta</button>'
                }
            </div>
        </form>
        <div id="surveyFloatingMessage" class="form-status-dock">${renderFormMessage()}</div>
    `;

    bindFormEvents(section);
}

function bindFormEvents(section) {
    const form = document.getElementById('publicSurveyForm');

    form.querySelectorAll('input[type="radio"]:not([data-matrix-question])').forEach((input) => {
        input.addEventListener('change', () => {
            answers[input.name] = input.value;
            clearQuestionState(input.dataset.questionCode || input.name);
            if (shouldRefreshForVisibility(input.name)) {
                renderForm();
            }
        });
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
        input.addEventListener('change', () => {
            const selected = Array.from(form.querySelectorAll(`input[name="${input.name}"]:checked`)).map((element) => element.value);
            answers[input.name] = selected;
            clearQuestionState(input.dataset.questionCode || input.name);
            if (shouldRefreshForVisibility(input.name)) {
                renderForm();
            }
        });
    });

    form.querySelectorAll('input[type="text"], textarea').forEach((input) => {
        input.addEventListener('input', () => {
            answers[input.name] = input.value;
            clearQuestionState(input.name);
        });
    });

    form.querySelectorAll('select.public-select').forEach((input) => {
        input.addEventListener('change', () => {
            answers[input.name] = input.value;
            clearQuestionState(input.name);
        });
    });

    form.querySelectorAll('input[type="radio"][data-matrix-question]').forEach((input) => {
        input.addEventListener('change', () => {
            const questionCode = input.dataset.matrixQuestion;
            const rowCode = input.dataset.row;
            const dimensionCode = input.dataset.dimension;
            answers[questionCode] = answers[questionCode] || {};
            answers[questionCode][rowCode] = answers[questionCode][rowCode] || {};
            answers[questionCode][rowCode][dimensionCode] = input.value;
            clearQuestionState(questionCode);
        });
    });

    document.getElementById('prevButton')?.addEventListener('click', () => {
        currentStep -= 1;
        renderForm();
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    document.getElementById('nextButton')?.addEventListener('click', () => {
        if (!validateStep(section)) return;
        currentStep += 1;
        renderForm();
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validateStep(section)) return;

        try {
            const response = await fetch('<?= url('api/public/app.php?action=submit') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    survey: surveyData.slug,
                    started_at: startedAt,
                    answers,
                    metadata: buildClientMetadata(),
                }),
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                if (result.errors) {
                    invalidQuestions = new Set(Object.keys(result.errors));
                    questionErrors = Object.fromEntries(
                        Object.entries(result.errors).map(([code, message]) => [code, message || 'Esta pregunta es obligatoria.'])
                    );
                    formMessage = {
                        type: 'danger',
                        title: 'No fue posible registrar la encuesta',
                        message: result.message || 'Existen respuestas obligatorias pendientes.',
                        details: buildMissingQuestionDetails(Object.keys(result.errors)),
                    };
                    renderForm();
                    const firstError = Object.keys(result.errors)[0];
                    requestAnimationFrame(() => {
                        document.querySelector(`[data-question="${firstError}"]`)?.scrollIntoView({behavior: 'smooth', block: 'center'});
                    });
                    return;
                }

                setFormMessage('danger', 'No fue posible registrar la encuesta', result.message || 'Intente nuevamente en unos segundos.');
                return;
            }

            surveyApp.innerHTML = `
                <div class="hero-card">
                    <span class="chip chip-success">Respuesta registrada</span>
                    <div>
                        <h2>Gracias por participar</h2>
                        <p>${escapeHtml(result.message)}</p>
                    </div>
                    <div class="actions-inline">
                        <a class="btn btn-secondary" href="<?= url('public/index.php') ?>">Ver otras encuestas</a>
                    </div>
                </div>
            `;
            window.scrollTo({top: 0, behavior: 'smooth'});
        } catch (error) {
            setFormMessage('danger', 'No se pudo enviar la encuesta', 'Verifique su conexión y vuelva a intentarlo.');
        }
    });
}

trackSurveyAccess();
renderForm();
</script>
<?php endif; ?>
</body>
</html>
