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
$landingTitle = count($allSurveys) === 1 ? 'Encuesta disponible' : 'Seleccione una encuesta';
$selectedIntroText = trim((string) ($selectedSurvey['intro_text'] ?? ''));
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
                <div class="hero-card public-landing-card">
                    <div class="public-landing-header">
                        <div class="public-brand">
                            <span class="chip chip-muted">Encuestas públicas</span>
                            <img src="<?= asset('img/shalom-wordmark.svg') ?>" alt="Shalom">
                        </div>
                        <div class="public-landing-copy">
                            <div>
                                <h1 class="public-landing-title"><?= e($landingTitle) ?></h1>
                            </div>
                        </div>
                    </div>
                    <?php if ($notFoundPublicSurvey): ?>
                        <div class="alert alert-danger">La encuesta solicitada no existe o no está publicada.</div>
                    <?php endif; ?>
                    <?php if ($allSurveys === []): ?>
                        <div class="empty-state">No existen encuestas públicas disponibles en este momento.</div>
                    <?php else: ?>
                        <div class="survey-grid">
                            <?php foreach ($allSurveys as $survey): ?>
                                <article class="survey-card survey-card-public">
                                    <div class="survey-card-header">
                                        <span class="<?= e(str_contains($survey['window_status'], 'active') ? 'chip chip-success' : 'chip chip-warning') ?>">
                                            <?= e($survey['status_label']) ?>
                                        </span>
                                    </div>
                                    <div class="survey-card-body">
                                        <h2><?= e($survey['name']) ?></h2>
                                    </div>
                                    <div class="survey-meta-grid">
                                        <article class="survey-meta-item">
                                            <span>Inicio</span>
                                            <strong><?= e(Helpers::formatDateTime($survey['start_at'])) ?></strong>
                                        </article>
                                        <article class="survey-meta-item">
                                            <span>Cierre</span>
                                            <strong><?= e(Helpers::formatDateTime($survey['end_at'])) ?></strong>
                                        </article>
                                    </div>
                                    <div class="survey-card-footer">
                                        <div class="survey-structure">
                                            <span class="chip chip-muted"><?= (int) $survey['section_count'] ?> secciones</span>
                                            <span class="chip chip-muted"><?= (int) $survey['question_count'] ?> preguntas</span>
                                        </div>
                                        <a class="btn btn-primary" href="<?= url('public/index.php?survey=' . urlencode($survey['slug'])) ?>">
                                            <?= in_array($survey['window_status'], ['active', 'closing_soon'], true) ? 'Ingresar encuesta' : 'Ver detalle' ?>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="hero-card public-selected-card">
                    <div class="public-brand">
                        <span class="chip chip-muted">Encuesta pública</span>
                        <img src="<?= asset('img/shalom-wordmark.svg') ?>" alt="Shalom">
                    </div>
                    <div class="hero-meta">
                        <span class="<?= e($canRespond ? 'chip chip-success' : 'chip chip-warning') ?>"><?= e($selectedSurvey['status_label']) ?></span>
                    </div>
                    <div>
                        <h1><?= e($selectedSurvey['intro_title'] ?: $selectedSurvey['name']) ?></h1>
                        <?php if ($selectedIntroText !== ''): ?>
                            <p><?= e($selectedIntroText) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="survey-meta-grid">
                        <article class="survey-meta-item">
                            <span>Inicio oficial</span>
                            <strong><?= e(Helpers::formatDateTime($selectedSurvey['start_at'])) ?></strong>
                        </article>
                        <article class="survey-meta-item">
                            <span>Cierre oficial</span>
                            <strong><?= e(Helpers::formatDateTime($selectedSurvey['end_at'])) ?></strong>
                        </article>
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
const knownQuestionCodes = new Set((surveyData.questions_flat || []).map((question) => question.code));
const visibilityDrivers = new Set(
    (surveyData.questions_flat || []).flatMap((question) =>
        (Array.isArray(question.visibility_rules) ? question.visibility_rules : []).map((rule) => rule.question_code)
    )
);
let currentStep = 0;
let answers = {};
let invalidQuestions = new Set();
let questionErrors = {};
let startedAt = new Date().toISOString();
const surveySessionToken = ensureSurveySessionToken();
const surveyDraftKey = `shalom-survey-draft:${surveyData.slug}`;
let accessTracked = false;
let formMessage = defaultFormMessage();
let draftSaveTimer = null;
let lastDraftSavedAt = null;
let draftWasRestored = false;
let isSubmitting = false;

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

function readDraft() {
    try {
        const rawDraft = window.localStorage.getItem(surveyDraftKey);
        if (!rawDraft) {
            return null;
        }

        const parsedDraft = JSON.parse(rawDraft);
        return parsedDraft && typeof parsedDraft === 'object' ? parsedDraft : null;
    } catch (error) {
        return null;
    }
}

function persistDraftNow() {
    try {
        lastDraftSavedAt = new Date().toISOString();
        draftWasRestored = false;
        window.localStorage.setItem(surveyDraftKey, JSON.stringify({
            answers,
            currentStep,
            startedAt,
            savedAt: lastDraftSavedAt,
        }));
    } catch (error) {
        // Draft persistence should never block the form.
    }
}

function scheduleDraftSave() {
    window.clearTimeout(draftSaveTimer);
    draftSaveTimer = window.setTimeout(() => {
        persistDraftNow();
    }, 180);
}

function clearDraft() {
    try {
        window.localStorage.removeItem(surveyDraftKey);
    } catch (error) {
        // Ignore storage cleanup failures.
    }

    lastDraftSavedAt = null;
}

function restoreDraft() {
    const savedDraft = readDraft();
    if (!savedDraft) {
        return;
    }

    if (savedDraft.answers && typeof savedDraft.answers === 'object') {
        answers = savedDraft.answers;
    }

    if (Number.isInteger(savedDraft.currentStep)) {
        currentStep = savedDraft.currentStep;
    }

    if (typeof savedDraft.startedAt === 'string' && savedDraft.startedAt !== '') {
        startedAt = savedDraft.startedAt;
    }

    if (typeof savedDraft.savedAt === 'string' && savedDraft.savedAt !== '') {
        lastDraftSavedAt = savedDraft.savedAt;
    }

    draftWasRestored = true;
    formMessage = {
        type: 'info',
        title: 'Se recuperó su progreso',
        message: 'Puede continuar desde el último avance guardado en este dispositivo.',
    };
}

function formatSavedAt(value) {
    if (!value) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat('es-EC', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
        }).format(new Date(value));
    } catch (error) {
        return '';
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

function getVisibleQuestionCodes() {
    return new Set(
        (surveyData.questions_flat || [])
            .filter((question) => isQuestionVisible(question))
            .map((question) => question.code)
    );
}

function getQuestionDisplayOrderMap() {
    const orderMap = {};
    let order = 1;

    getVisibleSections().forEach((section) => {
        section.questions.forEach((question) => {
            orderMap[question.code] = order++;
        });
    });

    return orderMap;
}

function pruneInvisibleAnswers() {
    let changed = false;
    let shouldRecheck = true;

    while (shouldRecheck) {
        shouldRecheck = false;
        const visibleQuestionCodes = getVisibleQuestionCodes();

        for (const questionCode of Object.keys(answers)) {
            if (visibleQuestionCodes.has(questionCode)) {
                continue;
            }

            if (!knownQuestionCodes.has(questionCode) || questionCode in answers) {
                delete answers[questionCode];
                invalidQuestions.delete(questionCode);
                delete questionErrors[questionCode];
                changed = true;
                shouldRecheck = true;
            }
        }
    }

    if (changed && invalidQuestions.size === 0 && formMessage?.type === 'danger') {
        formMessage = defaultFormMessage();
    }

    return changed;
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
        floatingContainer.innerHTML = renderFloatingMessage();
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
        info: 'i',
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

function renderFloatingMessage() {
    if (!formMessage || formMessage.type !== 'danger') {
        return '';
    }

    return renderFormMessage();
}

function questionTitle(code) {
    const question = questionLookup[code];
    if (!question) {
        return code;
    }

    return `${questionDisplayLabel(question.code)}. ${question.prompt}`;
}

function questionDisplayLabel(code) {
    const order = getQuestionDisplayOrderMap()[code];
    return order ? String(order) : code;
}

function shortText(value, limit = 52) {
    const normalized = String(value ?? '').trim();
    if (normalized.length <= limit) {
        return normalized;
    }

    return `${normalized.slice(0, Math.max(limit - 1, 1)).trimEnd()}…`;
}

function buildMissingQuestionDetails(codes) {
    return codes.map((code) => questionTitle(code));
}

function questionHasAnswer(question) {
    const value = answers[question.code];

    if (question.question_type === 'multiple_choice') {
        return Array.isArray(value) && value.length > 0;
    }

    if (question.question_type === 'matrix') {
        const rows = question.settings?.matrix?.rows || [];
        const dimensions = question.settings?.matrix?.dimensions || [];

        if (!rows.length || !dimensions.length) {
            return false;
        }

        return rows.every((row) => dimensions.every((dimension) => !!answers[question.code]?.[row.code]?.[dimension.code]));
    }

    return value !== undefined && value !== null && String(value).trim() !== '';
}

function getSectionProgress(section) {
    const total = section.questions.length;
    const answered = section.questions.filter((question) => questionHasAnswer(question)).length;
    const requiredQuestions = section.questions.filter((question) => question.is_required);
    const requiredTotal = requiredQuestions.length;
    const requiredAnswered = requiredQuestions.filter((question) => questionHasAnswer(question)).length;

    return {
        total,
        answered,
        requiredTotal,
        requiredAnswered,
        percent: total > 0 ? Math.round((answered / total) * 100) : 0,
    };
}

function getOverallProgress(sections) {
    const totals = sections.reduce((carry, section) => {
        const progress = getSectionProgress(section);
        carry.total += progress.total;
        carry.answered += progress.answered;
        return carry;
    }, {total: 0, answered: 0});

    return {
        ...totals,
        percent: totals.total > 0 ? Math.round((totals.answered / totals.total) * 100) : 0,
    };
}

function getFirstPendingQuestion(section, requiredOnly = false) {
    const questions = requiredOnly
        ? section.questions.filter((question) => question.is_required)
        : section.questions;

    return questions.find((question) => !questionHasAnswer(question)) || null;
}

function getQuestionInteractionHint(question) {
    if (question.question_type === 'rating') {
        return 'Seleccione una valoración';
    }

    if (question.question_type === 'multiple_choice') {
        return 'Puede marcar varias opciones';
    }

    if (question.question_type === 'single_choice') {
        return 'Seleccione una sola opción';
    }

    if (question.question_type === 'matrix') {
        const rows = question.settings?.matrix?.rows || [];
        return rows.length ? `Complete ${rows.length} filas` : 'Complete la matriz';
    }

    if (question.question_type === 'textarea') {
        return 'Escriba una respuesta breve';
    }

    return 'Ingrese su respuesta';
}

function getQuestionStructureHint(question) {
    if (question.question_type === 'matrix') {
        const dimensions = question.settings?.matrix?.dimensions || [];
        return dimensions.length ? `${dimensions.length} columnas de evaluación` : 'Formato matricial';
    }

    if (Array.isArray(question.options) && question.options.length) {
        return `${question.options.length} opciones disponibles`;
    }

    return question.is_required ? 'Respuesta requerida' : 'Puede dejarla para después';
}

function renderSectionQuestionRail(section) {
    const firstPendingRequired = getFirstPendingQuestion(section, true);

    return `
        <aside class="section-outline-card" aria-label="Mapa rápido de preguntas de la sección">
            <div class="section-outline-head">
                <div>
                    <strong>Mapa rápido</strong>
                    <span>${section.questions.length} preguntas visibles</span>
                </div>
                ${firstPendingRequired ? `
                    <button
                        class="btn btn-secondary btn-compact"
                        type="button"
                        data-question-jump="${escapeHtml(firstPendingRequired.code)}"
                    >
                        Ir a pendiente
                    </button>
                ` : ''}
            </div>
            <div class="question-rail">
                ${section.questions.map((question) => {
                    const complete = questionHasAnswer(question);
                    const stateClass = complete ? 'is-complete' : (question.is_required ? 'is-pending' : 'is-optional');
                    const stateLabel = complete ? 'Respondida' : (question.is_required ? 'Pendiente' : 'Opcional');

                    return `
                        <button
                            class="question-pill ${stateClass}"
                            type="button"
                            data-question-jump="${escapeHtml(question.code)}"
                            title="${escapeHtml(question.prompt)}"
                            aria-label="Ir a la pregunta ${escapeHtml(questionDisplayLabel(question.code))}: ${escapeHtml(question.prompt)}"
                        >
                            <span class="question-pill-number">${escapeHtml(questionDisplayLabel(question.code))}</span>
                            <span class="question-pill-copy">
                                <span class="question-pill-text">${escapeHtml(shortText(question.prompt, 44))}</span>
                                <span class="question-pill-state">${escapeHtml(stateLabel)}</span>
                            </span>
                        </button>
                    `;
                }).join('')}
            </div>
        </aside>
    `;
}

function renderSectionBanner(section, sections) {
    const sectionProgress = getSectionProgress(section);
    const overallProgress = getOverallProgress(sections);
    const pendingRequired = Math.max(sectionProgress.requiredTotal - sectionProgress.requiredAnswered, 0);
    const firstPendingQuestion = getFirstPendingQuestion(section);
    const draftLabel = lastDraftSavedAt
        ? `${draftWasRestored ? 'Progreso recuperado' : 'Guardado local'} ${formatSavedAt(lastDraftSavedAt)}`
        : 'Guardado local disponible';

    return `
        <section class="section-banner">
            <div class="hero-meta">
                <span class="chip chip-muted">Paso ${currentStep + 1} de ${sections.length}</span>
                <span class="chip chip-muted">${sectionProgress.answered}/${sectionProgress.total} respondidas</span>
                <span class="chip ${pendingRequired > 0 ? 'chip-warning' : 'chip-success'}">
                    ${pendingRequired > 0 ? `${pendingRequired} obligatorias pendientes` : 'Sección lista'}
                </span>
                <span class="chip chip-muted">${draftLabel}</span>
            </div>
            <div class="section-banner-head">
                <div>
                    <h2>${escapeHtml(section.title)}</h2>
                    <p>${escapeHtml(section.description || 'Responda esta sección para continuar con el levantamiento.')}</p>
                </div>
                ${firstPendingQuestion ? `
                    <button
                        class="btn btn-secondary btn-compact section-banner-jump"
                        type="button"
                        data-question-jump="${escapeHtml(firstPendingQuestion.code)}"
                    >
                        Continuar en la siguiente pendiente
                    </button>
                ` : ''}
            </div>
            <div class="section-progress-layout">
                <div class="section-progress-panel" aria-label="Progreso de la encuesta">
                    <div class="section-progress-summary">
                        <div>
                            <span>Sección actual</span>
                            <strong>${sectionProgress.percent}%</strong>
                        </div>
                        <div>
                            <span>Avance total</span>
                            <strong>${overallProgress.percent}%</strong>
                        </div>
                    </div>
                    <div class="section-progress-bars">
                        <div class="section-progress-block">
                            <div class="section-progress-label">
                                <span>Sección</span>
                                <strong>${sectionProgress.answered}/${sectionProgress.total}</strong>
                            </div>
                            <div class="section-progress-track" aria-hidden="true">
                                <span style="width:${sectionProgress.percent}%"></span>
                            </div>
                        </div>
                        <div class="section-progress-block">
                            <div class="section-progress-label">
                                <span>Total visible</span>
                                <strong>${overallProgress.answered}/${overallProgress.total}</strong>
                            </div>
                            <div class="section-progress-track section-progress-track-overall" aria-hidden="true">
                                <span style="width:${overallProgress.percent}%"></span>
                            </div>
                        </div>
                    </div>
                    <div class="section-progress-meta">
                        <span>${sectionProgress.answered} de ${sectionProgress.total} preguntas respondidas</span>
                        <span>${pendingRequired > 0 ? `${pendingRequired} obligatorias por completar` : 'Puede continuar a la siguiente sección'}</span>
                    </div>
                </div>
                ${renderSectionQuestionRail(section)}
            </div>
        </section>
    `;
}

function getQuestionCard(questionCode) {
    if (!questionCode) {
        return null;
    }

    if (window.CSS?.escape) {
        return document.querySelector(`[data-question="${window.CSS.escape(questionCode)}"]`);
    }

    return Array.from(document.querySelectorAll('[data-question]')).find((element) => element.dataset.question === questionCode) || null;
}

function captureQuestionViewport(questionCode) {
    const card = getQuestionCard(questionCode);
    if (!card) {
        return null;
    }

    return {
        questionCode,
        top: card.getBoundingClientRect().top,
    };
}

function restoreQuestionViewport(snapshot) {
    if (!snapshot) {
        return;
    }

    window.requestAnimationFrame(() => {
        const card = getQuestionCard(snapshot.questionCode);
        if (!card) {
            return;
        }

        const top = card.getBoundingClientRect().top;
        const offset = top - snapshot.top;
        if (Math.abs(offset) < 2) {
            return;
        }

        window.scrollTo({
            top: Math.max(window.scrollY + offset, 0),
            behavior: 'auto',
        });
    });
}

function renderStepper(sections, options = {}) {
    surveyStepper.innerHTML = sections.map((section, index) => `
        <div class="survey-step ${index === currentStep ? 'active' : ''} ${getSectionProgress(section).percent === 100 ? 'done' : ''}">
            <strong>Paso ${index + 1}</strong><br>
            <span>${escapeHtml(section.title)}</span>
            <small>${getSectionProgress(section).answered}/${getSectionProgress(section).total}</small>
        </div>
    `).join('');

    if (!options.alignActiveStep) {
        return;
    }

    window.requestAnimationFrame(() => {
        const activeStep = surveyStepper.querySelector('.survey-step.active');
        if (!activeStep) {
            return;
        }

        const containerRect = surveyStepper.getBoundingClientRect();
        const stepRect = activeStep.getBoundingClientRect();
        const maxLeft = Math.max(surveyStepper.scrollWidth - surveyStepper.clientWidth, 0);
        const targetLeft = surveyStepper.scrollLeft + (stepRect.left - containerRect.left) - ((containerRect.width - stepRect.width) / 2);

        surveyStepper.scrollTo({
            left: Math.min(Math.max(targetLeft, 0), maxLeft),
            behavior: 'smooth',
        });
    });
}

function clearQuestionState(questionCode) {
    if (!invalidQuestions.has(questionCode)) {
        return;
    }

    invalidQuestions.delete(questionCode);
    delete questionErrors[questionCode];
    const card = getQuestionCard(questionCode);
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
    const ratingAccents = ['#027a48', '#2a6b4f', '#8b7b52', '#b54708', '#b42318'];

    if (type === 'checkbox') {
        classes.push('checkbox-card');
    }

    if (config.variant === 'rating') {
        classes.push('rating-card');
    }

    return `
        <label class="${classes.join(' ')}" ${config.variant === 'rating' ? `style="--rating-accent:${ratingAccents[config.index] || '#1e4d39'};"` : ''}>
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
    const role = question.question_type === 'rating' || question.question_type === 'single_choice' ? 'radiogroup' : 'group';
    return `
        <div class="${containerClass}" role="${role}" aria-label="${escapeHtml(question.prompt)}">
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
        <div class="option-grid columns-2" role="group" aria-label="${escapeHtml(question.prompt)}">
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
                <span class="chip chip-muted">1 fila corresponde a 1 personaje político</span>
                <span class="chip chip-muted">Seleccione una opción en cada columna</span>
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
    const isInvalid = invalidQuestions.has(question.code);
    const isComplete = questionHasAnswer(question);
    const guidance = getQuestionInteractionHint(question);
    const structureHint = getQuestionStructureHint(question);

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
            body = `<textarea class="public-control public-textarea" name="${escapeHtml(question.code)}" placeholder="${escapeHtml(question.placeholder || '')}" aria-invalid="${isInvalid ? 'true' : 'false'}">${escapeHtml(value)}</textarea>`;
        } else {
            body = `<input class="public-control" type="text" name="${escapeHtml(question.code)}" value="${escapeHtml(value)}" placeholder="${escapeHtml(question.placeholder || '')}" aria-invalid="${isInvalid ? 'true' : 'false'}">`;
        }
    }

    return `
        <article class="question-card ${isInvalid ? 'is-invalid' : ''} ${isComplete ? 'is-complete' : ''}" data-question="${question.code}">
            <div class="question-head">
                <div class="question-title-stack">
                    <div class="question-kicker">Pregunta ${escapeHtml(questionDisplayLabel(question.code))}</div>
                    <h3>${escapeHtml(question.prompt)}</h3>
                    <div class="question-support">
                        <span class="question-support-item">${escapeHtml(guidance)}</span>
                        <span class="question-support-item">${escapeHtml(structureHint)}</span>
                    </div>
                    ${question.help_text ? `<p class="question-help-text">${escapeHtml(question.help_text)}</p>` : ''}
                    ${Array.isArray(question.visibility_rules) && question.visibility_rules.length ? `<span class="question-conditional-hint">Pregunta condicional</span>` : ''}
                </div>
                <div class="question-badges">
                    <span class="question-status ${isComplete ? 'question-status-complete' : 'question-status-pending'}">
                        ${isComplete ? 'Respondida' : 'Sin responder'}
                    </span>
                    <span class="question-badge ${question.is_required ? 'question-badge-required' : 'question-badge-optional'}">
                        ${question.is_required ? 'Obligatoria' : 'Opcional'}
                    </span>
                </div>
            </div>
            ${body}
            ${isInvalid ? `<div class="question-error">${escapeHtml(questionErrors[question.code] || 'Debe completar esta pregunta para continuar.')}</div>` : ''}
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
            getQuestionCard(firstInvalid)?.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
        return false;
    }

    formMessage = defaultFormMessage();
    updateFormMessage();
    return true;
}

function getVisibleQuestionCodesSet() {
    return new Set(getVisibleSections().flatMap((s) => s.questions.map((q) => q.code)));
}

let previousVisibleCodes = getVisibleQuestionCodesSet();

function renderForm(options = {}) {
    const viewportSnapshot = captureQuestionViewport(options.preserveQuestionCode || null);
    pruneInvisibleAnswers();
    const sections = getVisibleSections();
    if (!sections.length) {
        surveyApp.innerHTML = '<div class="empty-state">No hay preguntas visibles para esta encuesta en este momento.</div>';
        return;
    }

    currentStep = Math.max(0, Math.min(currentStep, sections.length - 1));
    const section = sections[currentStep];
    renderStepper(sections, {alignActiveStep: !!options.alignActiveStep});

    surveyApp.innerHTML = `
        <form class="survey-form" id="publicSurveyForm" aria-busy="${isSubmitting ? 'true' : 'false'}">
            <div id="surveyMessage" aria-live="polite">${renderFormMessage()}</div>
            ${renderSectionBanner(section, sections)}
            ${section.questions.map((question) => renderQuestion(question)).join('')}
            <div class="form-actions-shell">
                <div class="form-actions-summary">
                    <div>
                        <strong>${currentStep < sections.length - 1 ? 'Continúe al siguiente bloque' : 'Revise y envíe la encuesta'}</strong>
                        <span>${currentStep < sections.length - 1 ? 'Complete esta sección para seguir avanzando.' : 'El envío puede tardar unos segundos según su conexión.'}</span>
                    </div>
                    <button class="btn btn-link" type="button" id="resetSurveyButton" ${isSubmitting ? 'disabled' : ''}>Reiniciar respuestas</button>
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" type="button" id="prevButton" ${currentStep === 0 || isSubmitting ? 'disabled' : ''}>Atrás</button>
                    ${currentStep < sections.length - 1
                        ? `<button class="btn btn-primary" type="button" id="nextButton" ${isSubmitting ? 'disabled' : ''}>Siguiente</button>`
                        : `<button class="btn btn-primary" type="submit" id="submitButton" ${isSubmitting ? 'disabled' : ''}>${isSubmitting ? 'Enviando...' : 'Enviar encuesta'}</button>`
                    }
                </div>
            </div>
        </form>
        <div id="surveyFloatingMessage" class="form-status-dock">${renderFloatingMessage()}</div>
    `;

    bindFormEvents(section);
    restoreQuestionViewport(viewportSnapshot);

    const currentVisibleCodes = getVisibleQuestionCodesSet();
    const newlyRevealed = [...currentVisibleCodes].filter((code) => !previousVisibleCodes.has(code));
    previousVisibleCodes = currentVisibleCodes;

    if (newlyRevealed.length > 0 && !options.alignActiveStep) {
        const firstNewCode = newlyRevealed[0];
        requestAnimationFrame(() => {
            const card = getQuestionCard(firstNewCode);
            if (card) {
                card.scrollIntoView({behavior: 'smooth', block: 'center'});
                card.classList.add('is-newly-revealed');
                setTimeout(() => card.classList.remove('is-newly-revealed'), 1600);
            }
        });
    }
}

function bindFormEvents(section) {
    const form = document.getElementById('publicSurveyForm');

    form.querySelectorAll('[data-question-jump]').forEach((button) => {
        button.addEventListener('click', () => {
            const questionCode = button.dataset.questionJump || '';
            if (!questionCode) {
                return;
            }

            const card = getQuestionCard(questionCode);
            if (!card) {
                return;
            }

            card.scrollIntoView({behavior: 'smooth', block: 'center'});
            window.requestAnimationFrame(() => {
                card.querySelector('input, textarea, select')?.focus({preventScroll: true});
            });
        });
    });

    form.querySelectorAll('input[type="radio"]:not([data-matrix-question])').forEach((input) => {
        input.addEventListener('change', () => {
            const questionCode = input.dataset.questionCode || input.name;
            answers[input.name] = input.value;
            clearQuestionState(questionCode);
            if (shouldRefreshForVisibility(input.name) && pruneInvisibleAnswers()) {
                clearQuestionState(input.name);
            }
            scheduleDraftSave();
            renderForm({preserveQuestionCode: questionCode});
        });
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
        input.addEventListener('change', () => {
            const questionCode = input.dataset.questionCode || input.name;
            const selected = Array.from(form.querySelectorAll(`input[name="${input.name}"]:checked`)).map((element) => element.value);
            answers[input.name] = selected;
            clearQuestionState(questionCode);
            if (shouldRefreshForVisibility(input.name) && pruneInvisibleAnswers()) {
                clearQuestionState(input.name);
            }
            scheduleDraftSave();
            renderForm({preserveQuestionCode: questionCode});
        });
    });

    form.querySelectorAll('input[type="text"], textarea').forEach((input) => {
        input.addEventListener('input', () => {
            answers[input.name] = input.value;
            clearQuestionState(input.name);
            if (shouldRefreshForVisibility(input.name)) {
                pruneInvisibleAnswers();
            }
            scheduleDraftSave();
        });

        input.addEventListener('blur', () => {
            if (shouldRefreshForVisibility(input.name) && pruneInvisibleAnswers()) {
                clearQuestionState(input.name);
            }
            renderForm({preserveQuestionCode: input.name});
        });
    });

    form.querySelectorAll('select.public-select').forEach((input) => {
        input.addEventListener('change', () => {
            answers[input.name] = input.value;
            clearQuestionState(input.name);
            if (shouldRefreshForVisibility(input.name) && pruneInvisibleAnswers()) {
                clearQuestionState(input.name);
            }
            scheduleDraftSave();
            renderForm({preserveQuestionCode: input.name});
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
            scheduleDraftSave();
            renderForm({preserveQuestionCode: questionCode});
        });
    });

    form.querySelectorAll('.option-grid, .rating-grid').forEach((grid) => {
        grid.addEventListener('keydown', (event) => {
            if (!['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            const cards = Array.from(grid.querySelectorAll('.choice-card'));
            const currentIndex = cards.findIndex((card) => card.contains(document.activeElement) || card === document.activeElement);
            if (currentIndex === -1) return;
            event.preventDefault();
            const direction = (event.key === 'ArrowDown' || event.key === 'ArrowRight') ? 1 : -1;
            const nextIndex = Math.max(0, Math.min(cards.length - 1, currentIndex + direction));
            if (nextIndex !== currentIndex) {
                const input = cards[nextIndex].querySelector('input');
                if (input) {
                    input.focus();
                    cards[nextIndex].scrollIntoView({behavior: 'smooth', block: 'nearest'});
                }
            }
        });
    });

    document.getElementById('prevButton')?.addEventListener('click', () => {
        currentStep -= 1;
        scheduleDraftSave();
        renderForm({alignActiveStep: true});
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    document.getElementById('nextButton')?.addEventListener('click', () => {
        if (!validateStep(section)) return;
        currentStep += 1;
        scheduleDraftSave();
        renderForm({alignActiveStep: true});
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    document.getElementById('resetSurveyButton')?.addEventListener('click', () => {
        if (!window.confirm('Se borrarán las respuestas guardadas en este dispositivo para esta encuesta.')) {
            return;
        }

        answers = {};
        invalidQuestions = new Set();
        questionErrors = {};
        currentStep = 0;
        startedAt = new Date().toISOString();
        draftWasRestored = false;
        formMessage = defaultFormMessage();
        clearDraft();
        renderForm({alignActiveStep: true});
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validateStep(section)) return;

        isSubmitting = true;
        persistDraftNow();
        renderForm({preserveQuestionCode: section.questions[section.questions.length - 1]?.code});

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
                isSubmitting = false;
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
                        getQuestionCard(firstError)?.scrollIntoView({behavior: 'smooth', block: 'center'});
                    });
                    return;
                }

                renderForm();
                setFormMessage('danger', 'No fue posible registrar la encuesta', result.message || 'Intente nuevamente en unos segundos.');
                return;
            }

            window.clearTimeout(draftSaveTimer);
            clearDraft();
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
            isSubmitting = false;
            renderForm();
            setFormMessage('danger', 'No se pudo enviar la encuesta', 'Verifique su conexión y vuelva a intentarlo.');
        }
    });
}

restoreDraft();
pruneInvisibleAnswers();
window.addEventListener('beforeunload', (event) => {
    if (Object.keys(answers).length > 0) {
        pruneInvisibleAnswers();
        persistDraftNow();
        event.preventDefault();
    }
});
trackSurveyAccess();
renderForm({alignActiveStep: true});
</script>
<?php endif; ?>
</body>
</html>
