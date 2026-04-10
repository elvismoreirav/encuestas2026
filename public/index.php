<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

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

$allSurveys = !$selectedSurvey ? surveys()->listPublicSurveys() : [];
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
const surveySessionStorageKey = `shalom-survey-session:${surveyData.slug}`;
const surveyDraftKey = `shalom-survey-draft:${surveyData.slug}`;
const surveyDraftSessionKey = `shalom-survey-draft-active:${surveyData.slug}`;
const DRAFT_RECOVERY_WINDOW_MS = 12 * 60 * 60 * 1000;
let surveySessionToken = ensureSurveySessionToken();
let currentDraftId = ensureActiveDraftId();
let pendingDraftRecovery = null;
let accessTracked = false;
let formMessage = defaultFormMessage();
let draftSaveTimer = null;
let lastDraftSavedAt = null;
let draftWasRestored = false;
let isSubmitting = false;
let stickyActionObserver = null;

function surveyAllowsMultipleSubmissions() {
    const value = surveyData?.settings?.allow_multiple_submissions;
    return value === true || value === 1 || value === '1';
}

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

function generateDraftId() {
    return `draft-${generateSessionToken()}`;
}

function ensureSurveySessionToken() {
    try {
        const existing = window.sessionStorage.getItem(surveySessionStorageKey);
        if (existing) {
            return existing;
        }

        const token = generateSessionToken();
        window.sessionStorage.setItem(surveySessionStorageKey, token);
        return token;
    } catch (error) {
        return generateSessionToken();
    }
}

function setSurveySessionToken(token) {
    surveySessionToken = token || generateSessionToken();

    try {
        window.sessionStorage.setItem(surveySessionStorageKey, surveySessionToken);
    } catch (error) {
        // Session storage should not block the survey.
    }
}

function rotateSurveySessionToken() {
    setSurveySessionToken(generateSessionToken());
}

function ensureActiveDraftId() {
    try {
        const existing = window.sessionStorage.getItem(surveyDraftSessionKey);
        if (existing) {
            return existing;
        }

        const draftId = generateDraftId();
        window.sessionStorage.setItem(surveyDraftSessionKey, draftId);
        return draftId;
    } catch (error) {
        return generateDraftId();
    }
}

function setActiveDraftId(draftId) {
    currentDraftId = draftId || generateDraftId();

    try {
        window.sessionStorage.setItem(surveyDraftSessionKey, currentDraftId);
    } catch (error) {
        // Session storage should not block the survey.
    }
}

function rotateActiveDraftId() {
    setActiveDraftId(generateDraftId());
}

function isDraftExpired(savedAt) {
    if (typeof savedAt !== 'string' || savedAt.trim() === '') {
        return false;
    }

    const savedAtMs = Date.parse(savedAt);
    if (!Number.isFinite(savedAtMs)) {
        return false;
    }

    return (Date.now() - savedAtMs) > DRAFT_RECOVERY_WINDOW_MS;
}

function readDraft() {
    try {
        const rawDraft = window.localStorage.getItem(surveyDraftKey);
        if (!rawDraft) {
            return null;
        }

        const parsedDraft = JSON.parse(rawDraft);
        if (!parsedDraft || typeof parsedDraft !== 'object') {
            return null;
        }

        if (isDraftExpired(parsedDraft.savedAt ?? null)) {
            window.localStorage.removeItem(surveyDraftKey);
            return null;
        }

        return parsedDraft;
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
            surveyUpdatedAt: surveyData.updated_at || null,
            draftId: currentDraftId,
            sessionToken: surveySessionToken,
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

function buildDraftRestoreMessage({surveyUpdated = false, adjusted = false} = {}) {
    return surveyUpdated || adjusted
        ? {
            type: 'warning',
            title: 'Se actualizó la encuesta',
            message: 'Se recuperó su progreso, pero algunas respuestas guardadas fueron ajustadas para coincidir con la versión vigente del formulario.',
        }
        : {
            type: 'info',
            title: 'Se recuperó su progreso',
            message: 'Puede continuar desde el último avance guardado en este dispositivo.',
        };
}

function resetResponseState() {
    answers = {};
    invalidQuestions = new Set();
    questionErrors = {};
    currentStep = 0;
    startedAt = new Date().toISOString();
    lastDraftSavedAt = null;
    draftWasRestored = false;
}

function countStoredDraftAnswers(snapshot) {
    if (!snapshot || typeof snapshot !== 'object' || Array.isArray(snapshot)) {
        return 0;
    }

    return Object.keys(snapshot).length;
}

function restoreDraft() {
    const savedDraft = readDraft();
    if (!savedDraft) {
        return;
    }

    if (savedDraft.answers && typeof savedDraft.answers === 'object') {
        answers = savedDraft.answers;
    }

    const sanitizedDraft = sanitizeAnswersSnapshot(answers);
    const draftSurveyUpdatedAt = typeof savedDraft.surveyUpdatedAt === 'string' && savedDraft.surveyUpdatedAt !== ''
        ? savedDraft.surveyUpdatedAt
        : null;
    const surveyUpdated = draftSurveyUpdatedAt !== null
        && typeof surveyData.updated_at === 'string'
        && surveyData.updated_at !== ''
        && draftSurveyUpdatedAt !== surveyData.updated_at;
    const sanitizedAnswerCount = countStoredDraftAnswers(sanitizedDraft.answers);

    if (sanitizedAnswerCount === 0) {
        clearDraft();
        return;
    }

    const normalizedDraft = {
        answers: sanitizedDraft.answers,
        currentStep: Number.isInteger(savedDraft.currentStep) ? savedDraft.currentStep : 0,
        startedAt: typeof savedDraft.startedAt === 'string' && savedDraft.startedAt !== '' ? savedDraft.startedAt : startedAt,
        savedAt: typeof savedDraft.savedAt === 'string' && savedDraft.savedAt !== '' ? savedDraft.savedAt : null,
        surveyUpdated,
        adjusted: sanitizedDraft.changed,
        draftId: typeof savedDraft.draftId === 'string' && savedDraft.draftId !== '' ? savedDraft.draftId : generateDraftId(),
        sessionToken: typeof savedDraft.sessionToken === 'string' && savedDraft.sessionToken !== '' ? savedDraft.sessionToken : generateSessionToken(),
    };

    resetResponseState();
    pendingDraftRecovery = normalizedDraft;
    formMessage = {
        type: 'info',
        title: 'Se detectó un borrador previo',
        message: 'Antes de continuar, confirme si desea recuperar ese borrador o iniciar una encuesta nueva en este dispositivo.',
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

function renderDraftRecoveryGate() {
    if (!pendingDraftRecovery) {
        return '';
    }

    const savedAtLabel = pendingDraftRecovery.savedAt ? formatSavedAt(pendingDraftRecovery.savedAt) : 'hace un momento';
    const answerCount = countStoredDraftAnswers(pendingDraftRecovery.answers);
    const stateChip = pendingDraftRecovery.surveyUpdated || pendingDraftRecovery.adjusted
        ? '<span class="chip chip-warning">Se ajustará a la versión vigente</span>'
        : '<span class="chip chip-success">Borrador recuperable</span>';

    return `
        <div class="hero-card draft-recovery-card">
            <div class="hero-meta">
                ${stateChip}
                <span class="chip chip-muted">Último guardado ${escapeHtml(savedAtLabel)}</span>
                <span class="chip chip-muted">${answerCount} respuestas válidas guardadas</span>
            </div>
            <div>
                <h2>Se encontró un borrador en este dispositivo</h2>
                <p>En equipos compartidos no conviene recuperar automáticamente la última encuesta. Elija si desea continuar ese borrador o empezar una respuesta nueva.</p>
            </div>
            <div class="actions-inline">
                <button class="btn btn-primary" type="button" id="resumeDraftButton">Continuar borrador</button>
                <button class="btn btn-secondary" type="button" id="startFreshResponseButton">Empezar nueva encuesta</button>
            </div>
            <p class="draft-recovery-note">Si este teléfono o computador lo usa otra persona, seleccione <strong>Empezar nueva encuesta</strong> para evitar mezclar respuestas.</p>
        </div>
    `;
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

    if (changed && formMessage?.type === 'danger') {
        if (invalidQuestions.size === 0) {
            formMessage = defaultFormMessage();
        } else {
            refreshValidationFormMessage();
        }
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

function refreshValidationFormMessage() {
    if (formMessage?.type !== 'danger') {
        return;
    }

    if (invalidQuestions.size === 0) {
        formMessage = defaultFormMessage();
        updateFormMessage();
        return;
    }

    if (!['Faltan respuestas obligatorias', 'No fue posible registrar la encuesta'].includes(formMessage.title)) {
        return;
    }

    formMessage = {
        ...formMessage,
        details: buildMissingQuestionDetails(Array.from(invalidQuestions)),
    };
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
    return codes.map((code) => {
        const errorConfig = getQuestionErrorConfig(code);
        return errorConfig.message ? `${questionTitle(code)}: ${errorConfig.message}` : questionTitle(code);
    });
}

function getQuestionErrorConfig(questionCode) {
    const fallbackMessage = 'Debe completar esta pregunta para continuar.';
    const error = questionErrors[questionCode];

    if (!error) {
        return {message: fallbackMessage, details: [], meta: {}};
    }

    if (typeof error === 'string') {
        const message = error.trim();
        return {message: message || fallbackMessage, details: [], meta: {}};
    }

    if (typeof error === 'object') {
        const message = typeof error.message === 'string' && error.message.trim() !== ''
            ? error.message.trim()
            : fallbackMessage;

        return {
            message,
            details: Array.isArray(error.details)
                ? error.details.filter((detail) => typeof detail === 'string' && detail.trim() !== '')
                : [],
            meta: error.meta && typeof error.meta === 'object' ? error.meta : {},
        };
    }

    return {message: fallbackMessage, details: [], meta: {}};
}

function isCompactSurveyViewport() {
    return inferDeviceType() === 'mobile' || window.innerWidth <= 768;
}

function clearSurveyLayoutOffsets() {
    surveyApp?.style?.setProperty('--survey-sticky-actions-space', '0px');
}

function syncSurveyLayoutOffsets() {
    const form = document.getElementById('publicSurveyForm');
    const actionShell = form?.querySelector('.form-actions-shell');

    if (!form || !actionShell || !isCompactSurveyViewport()) {
        clearSurveyLayoutOffsets();
        return;
    }

    const actionShellStyles = window.getComputedStyle(actionShell);
    const bottomOffset = parseFloat(actionShellStyles.bottom || '0') || 0;
    const stickySpace = Math.ceil(actionShell.getBoundingClientRect().height + bottomOffset + 20);
    surveyApp?.style?.setProperty('--survey-sticky-actions-space', `${stickySpace}px`);
}

function disconnectStickyActionObserver() {
    stickyActionObserver?.disconnect();
    stickyActionObserver = null;
}

function observeStickyActionShell() {
    disconnectStickyActionObserver();
    syncSurveyLayoutOffsets();

    const actionShell = document.querySelector('#publicSurveyForm .form-actions-shell');
    if (!actionShell || typeof window.ResizeObserver !== 'function') {
        return;
    }

    stickyActionObserver = new window.ResizeObserver(() => {
        syncSurveyLayoutOffsets();
    });
    stickyActionObserver.observe(actionShell);
}

function getMatrixConfig(question) {
    const matrix = question.settings?.matrix || {};
    return {
        rows: Array.isArray(matrix.rows) ? matrix.rows : [],
        dimensions: Array.isArray(matrix.dimensions) ? matrix.dimensions : [],
    };
}

function getMatrixMissingSelections(question, value = answers[question.code]) {
    const {rows, dimensions} = getMatrixConfig(question);
    const matrixAnswers = value && typeof value === 'object' ? value : {};
    const missing = [];

    rows.forEach((row) => {
        dimensions.forEach((dimension) => {
            if (matrixAnswers?.[row.code]?.[dimension.code]) {
                return;
            }

            missing.push({
                key: `${row.code}::${dimension.code}`,
                rowCode: row.code,
                rowLabel: row.label || row.code,
                dimensionCode: dimension.code,
                dimensionLabel: dimension.label || dimension.code,
            });
        });
    });

    return missing;
}

function getQuestionOptionCodes(question) {
    return new Set(
        (Array.isArray(question.options) ? question.options : [])
            .map((option) => String(option.code ?? '').trim())
            .filter((code) => code !== '')
    );
}

function isValidSingleChoiceAnswer(question, value) {
    if (value === null || value === undefined || Array.isArray(value)) {
        return false;
    }

    const normalized = String(value).trim();
    if (normalized === '') {
        return false;
    }

    return getQuestionOptionCodes(question).has(normalized);
}

function sanitizeMatrixAnswer(question, value) {
    const {rows, dimensions} = getMatrixConfig(question);
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return null;
    }

    const nextValue = {};

    rows.forEach((row) => {
        const rowValue = value[row.code];
        if (!rowValue || typeof rowValue !== 'object' || Array.isArray(rowValue)) {
            return;
        }

        dimensions.forEach((dimension) => {
            const selected = rowValue[dimension.code];
            if (selected === null || selected === undefined || Array.isArray(selected)) {
                return;
            }

            const normalized = String(selected).trim();
            if (normalized === '') {
                return;
            }

            const dimensionOptionCodes = new Set(
                (Array.isArray(dimension.options) ? dimension.options : [])
                    .map((option) => String(option.code ?? '').trim())
                    .filter((code) => code !== '')
            );

            if (!dimensionOptionCodes.has(normalized)) {
                return;
            }

            nextValue[row.code] = nextValue[row.code] || {};
            nextValue[row.code][dimension.code] = normalized;
        });
    });

    return Object.keys(nextValue).length ? nextValue : null;
}

function sanitizeAnswerForQuestion(question, value) {
    if (value === null || value === undefined) {
        return null;
    }

    if (question.question_type === 'single_choice' || question.question_type === 'rating') {
        return isValidSingleChoiceAnswer(question, value) ? String(value).trim() : null;
    }

    if (question.question_type === 'multiple_choice') {
        if (!Array.isArray(value)) {
            return null;
        }

        const validCodes = getQuestionOptionCodes(question);
        const selected = Array.from(new Set(
            value
                .filter((item) => item !== null && item !== undefined && !Array.isArray(item))
                .map((item) => String(item).trim())
                .filter((item) => item !== '' && validCodes.has(item))
        ));

        return selected.length ? selected : null;
    }

    if (question.question_type === 'matrix') {
        return sanitizeMatrixAnswer(question, value);
    }

    if (typeof value === 'object') {
        return null;
    }

    const normalized = String(value).trim();
    return normalized === '' ? null : normalized;
}

function sanitizeAnswersSnapshot(snapshot) {
    if (!snapshot || typeof snapshot !== 'object' || Array.isArray(snapshot)) {
        return {answers: {}, changed: false};
    }

    const nextAnswers = {};
    let changed = false;

    Object.entries(snapshot).forEach(([questionCode, value]) => {
        const question = questionLookup[questionCode];
        if (!question) {
            changed = true;
            return;
        }

        const sanitized = sanitizeAnswerForQuestion(question, value);
        if (sanitized === null) {
            if (value !== null && value !== undefined && value !== '') {
                changed = true;
            }
            return;
        }

        nextAnswers[questionCode] = sanitized;
        if (JSON.stringify(sanitized) !== JSON.stringify(value)) {
            changed = true;
        }
    });

    return {answers: nextAnswers, changed};
}

function applyRecoveredDraft(draft) {
    if (!draft) {
        return;
    }

    answers = draft.answers;
    invalidQuestions = new Set();
    questionErrors = {};
    currentStep = Number.isInteger(draft.currentStep) ? draft.currentStep : 0;
    startedAt = draft.startedAt || new Date().toISOString();
    lastDraftSavedAt = draft.savedAt || null;
    draftWasRestored = true;
    pendingDraftRecovery = null;
    setActiveDraftId(draft.draftId);
    setSurveySessionToken(draft.sessionToken);
    formMessage = buildDraftRestoreMessage({
        surveyUpdated: draft.surveyUpdated,
        adjusted: draft.adjusted,
    });
}

function startFreshSurveyResponse(messageConfig = null) {
    pendingDraftRecovery = null;
    clearDraft();
    rotateActiveDraftId();
    rotateSurveySessionToken();
    resetResponseState();
    formMessage = messageConfig || {
        type: 'info',
        title: 'Nueva encuesta iniciada',
        message: 'El dispositivo quedó listo para registrar una respuesta nueva sin mezclar el borrador anterior.',
    };
    renderForm({alignActiveStep: true});
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function validateRequiredQuestion(question) {
    const value = answers[question.code];

    if (question.question_type === 'multiple_choice') {
        const validSelections = sanitizeAnswerForQuestion(question, value);
        const valid = Array.isArray(validSelections) && validSelections.length > 0;
        return valid
            ? {valid: true}
            : {
                valid: false,
                error: {
                    message: 'Seleccione al menos una opción.',
                    details: [],
                    meta: {},
                },
            };
    }

    if (question.question_type === 'matrix') {
        const {rows, dimensions} = getMatrixConfig(question);
        if (!rows.length || !dimensions.length) {
            return {
                valid: false,
                error: {
                    message: 'La matriz no pudo validarse correctamente. Recargue la página e intente nuevamente.',
                    details: [],
                    meta: {},
                },
            };
        }

        const missing = getMatrixMissingSelections(question, value);
        if (missing.length === 0) {
            return {valid: true};
        }

        const affectedRows = new Set(missing.map((item) => item.rowCode)).size;
        const compact = isCompactSurveyViewport();
        const details = missing.map((item) => (
            compact
                ? `${item.rowLabel}: ${item.dimensionLabel}`
                : `${item.rowLabel} -> ${item.dimensionLabel}`
        ));

        return {
            valid: false,
            error: {
                message: compact
                    ? `Faltan ${missing.length} selecciones en ${affectedRows} ${affectedRows === 1 ? 'personaje' : 'personajes'}. Revise los bloques marcados debajo.`
                    : `Faltan ${missing.length} cruces por responder en la matriz.`,
                details,
                meta: {
                    missingMatrixItems: missing,
                },
            },
        };
    }

    if (question.question_type === 'single_choice' || question.question_type === 'rating') {
        return isValidSingleChoiceAnswer(question, value)
            ? {valid: true}
            : {
                valid: false,
                error: {
                    message: 'Seleccione una opción válida para continuar.',
                    details: [],
                    meta: {},
                },
            };
    }

    const valid = value !== undefined && value !== null && String(value).trim() !== '';
    return valid
        ? {valid: true}
        : {
            valid: false,
            error: {
                message: 'Esta pregunta es obligatoria y aún no ha sido respondida.',
                details: [],
                meta: {},
            },
        };
}

function questionHasAnswer(question) {
    const value = answers[question.code];

    if (question.question_type === 'multiple_choice') {
        const validSelections = sanitizeAnswerForQuestion(question, value);
        return Array.isArray(validSelections) && validSelections.length > 0;
    }

    if (question.question_type === 'single_choice' || question.question_type === 'rating') {
        return isValidSingleChoiceAnswer(question, value);
    }

    if (question.question_type === 'matrix') {
        const {rows, dimensions} = getMatrixConfig(question);

        if (!rows.length || !dimensions.length) {
            return false;
        }

        return getMatrixMissingSelections(question, value).length === 0;
    }

    return value !== undefined && value !== null && String(value).trim() !== '';
}

function detectSurveyStructureMismatch(serverErrors) {
    const codes = Object.keys(serverErrors || {});
    const unknownCodes = codes.filter((code) => !questionLookup[code]);
    const locallyAnsweredCodes = codes.filter((code) => {
        const question = questionLookup[code];
        if (!question || !question.is_required) {
            return false;
        }

        return questionHasAnswer(question) && validateRequiredQuestion(question).valid;
    });

    return {
        unknownCodes,
        locallyAnsweredCodes,
        hasMismatch: unknownCodes.length > 0 || locallyAnsweredCodes.length > 0,
    };
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

function scrollToQuestionValidationTarget(questionCode) {
    const card = getQuestionCard(questionCode);
    if (!card) {
        return;
    }

    const target = card.querySelector('.matrix-error-summary, .matrix-cell.is-missing, .question-error') || card;
    target.scrollIntoView({behavior: 'smooth', block: 'center'});
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

function syncQuestionValidationState(questionCode) {
    const question = questionLookup[questionCode];
    if (!question || !question.is_required || !isQuestionVisible(question)) {
        invalidQuestions.delete(questionCode);
        delete questionErrors[questionCode];
    } else {
        const validation = validateRequiredQuestion(question);
        if (validation.valid) {
            invalidQuestions.delete(questionCode);
            delete questionErrors[questionCode];
        } else {
            invalidQuestions.add(questionCode);
            questionErrors[questionCode] = validation.error;
        }
    }

    refreshValidationFormMessage();
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

function renderMatrix(question, errorConfig = getQuestionErrorConfig(question.code)) {
    const matrix = question.settings?.matrix || {rows: [], dimensions: []};
    const gridStyle = `style="grid-template-columns: 220px repeat(${matrix.dimensions.length}, minmax(0, 1fr));"`;
    const missingItems = Array.isArray(errorConfig.meta?.missingMatrixItems) ? errorConfig.meta.missingMatrixItems : [];
    const missingKeys = new Set(missingItems.map((item) => item.key));
    const missingByRow = missingItems.reduce((carry, item) => {
        carry[item.rowCode] = carry[item.rowCode] || [];
        carry[item.rowCode].push(item);
        return carry;
    }, {});

    return `
        <div class="matrix-shell">
            ${missingItems.length ? `
                <div class="matrix-error-summary" role="alert">
                    <strong>${escapeHtml(errorConfig.message)}</strong>
                    <ul class="matrix-error-list">
                        ${errorConfig.details.map((detail) => `<li>${escapeHtml(detail)}</li>`).join('')}
                    </ul>
                </div>
            ` : ''}
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
                    ${(() => {
                        const rowMissing = missingByRow[row.code] || [];
                        return `
                    <div class="matrix-table-row" ${gridStyle}>
                        <div class="matrix-candidate ${rowMissing.length ? 'is-missing' : ''}">
                            <span class="matrix-order">${String(rowIndex + 1).padStart(2, '0')}</span>
                            <strong>${escapeHtml(row.label)}</strong>
                            ${rowMissing.length ? `
                                <span class="matrix-row-status matrix-row-status-pending">
                                    ${rowMissing.length} ${rowMissing.length === 1 ? 'pendiente' : 'pendientes'}
                                </span>
                            ` : ''}
                        </div>
                        ${matrix.dimensions.map((dimension) => {
                            const cellMissing = missingKeys.has(`${row.code}::${dimension.code}`);
                            return `
                            <div class="matrix-cell ${cellMissing ? 'is-missing' : ''}">
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
                                                aria-invalid="${cellMissing ? 'true' : 'false'}"
                                                ${answers[question.code]?.[row.code]?.[dimension.code] === option.code ? 'checked' : ''}
                                            >
                                            <span class="matrix-option-badge">${String.fromCharCode(65 + optionIndex)}</span>
                                            <span class="matrix-option-label">${escapeHtml(option.label)}</span>
                                        </label>
                                    `).join('')}
                                </div>
                                ${cellMissing ? `<span class="matrix-cell-error">Falta seleccionar una opción para ${escapeHtml(dimension.label)}.</span>` : ''}
                            </div>
                        `;
                        }).join('')}
                    </div>
                `;
                    })()}
                `).join('')}
            </div>
        </div>
    `;
}

function renderQuestion(question) {
    let body = '';
    const isInvalid = invalidQuestions.has(question.code);
    const isComplete = questionHasAnswer(question);
    const errorConfig = getQuestionErrorConfig(question.code);
    const guidance = getQuestionInteractionHint(question);
    const structureHint = getQuestionStructureHint(question);

    if (question.question_type === 'single_choice' || question.question_type === 'rating') {
        body = renderSingleChoice(question);
    } else if (question.question_type === 'multiple_choice') {
        body = renderMultipleChoice(question);
    } else if (question.question_type === 'matrix') {
        body = renderMatrix(question, errorConfig);
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
            ${isInvalid ? `
                <div class="question-error">
                    <strong>${escapeHtml(errorConfig.message)}</strong>
                    ${question.question_type !== 'matrix' && errorConfig.details.length ? `
                        <ul class="question-error-list">
                            ${errorConfig.details.map((detail) => `<li>${escapeHtml(detail)}</li>`).join('')}
                        </ul>
                    ` : ''}
                </div>
            ` : ''}
        </article>
    `;
}

function validateStep(section) {
    const nextInvalidQuestions = new Set();
    const nextQuestionErrors = {};
    let firstInvalid = null;

    for (const question of section.questions) {
        if (!question.is_required) continue;

        const validation = validateRequiredQuestion(question);
        if (!validation.valid) {
            nextInvalidQuestions.add(question.code);
            nextQuestionErrors[question.code] = validation.error;
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
            scrollToQuestionValidationTarget(firstInvalid);
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
        disconnectStickyActionObserver();
        clearSurveyLayoutOffsets();
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
    observeStickyActionShell();
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

function bindDraftRecoveryEvents() {
    document.getElementById('resumeDraftButton')?.addEventListener('click', () => {
        applyRecoveredDraft(pendingDraftRecovery);
        renderForm({alignActiveStep: true});
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    document.getElementById('startFreshResponseButton')?.addEventListener('click', () => {
        startFreshSurveyResponse();
    });
}

function renderSurveyExperience(options = {}) {
    if (!pendingDraftRecovery) {
        renderForm(options);
        return;
    }

    disconnectStickyActionObserver();
    clearSurveyLayoutOffsets();
    surveyStepper.innerHTML = '';
    surveyApp.innerHTML = `
        <div id="surveyMessage" aria-live="polite">${renderFormMessage()}</div>
        ${renderDraftRecoveryGate()}
    `;
    bindDraftRecoveryEvents();
}

function renderSubmissionSuccess(message) {
    const canRespondAgain = surveyAllowsMultipleSubmissions();

    surveyApp.innerHTML = `
        <div class="hero-card">
            <span class="chip chip-success">Respuesta registrada</span>
            <div>
                <h2>Gracias por participar</h2>
                <p>${escapeHtml(message)}</p>
            </div>
            <div class="actions-inline">
                ${canRespondAgain ? '<button class="btn btn-primary" type="button" id="respondAgainButton">Registrar otra respuesta</button>' : ''}
                <a class="btn btn-secondary" href="<?= url('public/index.php') ?>">Ver otras encuestas</a>
            </div>
            ${canRespondAgain ? '<p class="draft-recovery-note">El dispositivo quedó libre de respuestas anteriores. Use esta opción para registrar al siguiente encuestado sin mezclar información.</p>' : ''}
        </div>
    `;

    document.getElementById('respondAgainButton')?.addEventListener('click', () => {
        startFreshSurveyResponse({
            type: 'info',
            title: 'Nueva respuesta iniciada',
            message: 'El formulario quedó listo para registrar una nueva respuesta en este dispositivo.',
        });
    });
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
            if (shouldRefreshForVisibility(input.name)) {
                pruneInvisibleAnswers();
            }
            syncQuestionValidationState(questionCode);
            scheduleDraftSave();
            renderForm({preserveQuestionCode: questionCode});
        });
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
        input.addEventListener('change', () => {
            const questionCode = input.dataset.questionCode || input.name;
            const selected = Array.from(form.querySelectorAll(`input[name="${input.name}"]:checked`)).map((element) => element.value);
            answers[input.name] = selected;
            if (shouldRefreshForVisibility(input.name)) {
                pruneInvisibleAnswers();
            }
            syncQuestionValidationState(questionCode);
            scheduleDraftSave();
            renderForm({preserveQuestionCode: questionCode});
        });
    });

    form.querySelectorAll('input[type="text"], textarea').forEach((input) => {
        input.addEventListener('input', () => {
            answers[input.name] = input.value;
            if (shouldRefreshForVisibility(input.name)) {
                pruneInvisibleAnswers();
            }
            syncQuestionValidationState(input.name);
            scheduleDraftSave();
        });

        input.addEventListener('blur', () => {
            if (shouldRefreshForVisibility(input.name)) {
                pruneInvisibleAnswers();
            }
            syncQuestionValidationState(input.name);
            renderForm({preserveQuestionCode: input.name});
        });
    });

    form.querySelectorAll('select.public-select').forEach((input) => {
        input.addEventListener('change', () => {
            answers[input.name] = input.value;
            if (shouldRefreshForVisibility(input.name)) {
                pruneInvisibleAnswers();
            }
            syncQuestionValidationState(input.name);
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
            syncQuestionValidationState(questionCode);
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

        startFreshSurveyResponse({
            type: 'info',
            title: 'Nueva encuesta iniciada',
            message: 'Se borró el avance anterior y el formulario quedó listo para una respuesta nueva en este dispositivo.',
        });
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
                    const mismatch = detectSurveyStructureMismatch(result.errors);
                    if (mismatch.hasMismatch) {
                        answers = sanitizeAnswersSnapshot(answers).answers;
                        persistDraftNow();
                        isSubmitting = false;
                        formMessage = {
                            type: 'warning',
                            title: 'La encuesta fue actualizada',
                            message: 'Se detectaron cambios en las preguntas u opciones del servidor. La página se recargará para mostrar la versión vigente y conservar su avance válido.',
                            details: [
                                ...mismatch.locallyAnsweredCodes.map((code) => questionTitle(code)),
                                ...mismatch.unknownCodes,
                            ],
                        };
                        renderForm();
                        window.setTimeout(() => window.location.reload(), 600);
                        return;
                    }

                    invalidQuestions = new Set(Object.keys(result.errors));
                    questionErrors = Object.fromEntries(
                        Object.entries(result.errors).map(([code, message]) => {
                            const question = questionLookup[code];
                            if (question?.is_required) {
                                const validation = validateRequiredQuestion(question);
                                if (!validation.valid) {
                                    return [code, validation.error];
                                }
                            }

                            return [code, {
                                message: message || 'Esta pregunta es obligatoria.',
                                details: [],
                                meta: {},
                            }];
                        })
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
                        scrollToQuestionValidationTarget(firstError);
                    });
                    return;
                }

                renderForm();
                setFormMessage('danger', 'No fue posible registrar la encuesta', result.message || 'Intente nuevamente en unos segundos.');
                return;
            }

            window.clearTimeout(draftSaveTimer);
            clearDraft();
            rotateActiveDraftId();
            rotateSurveySessionToken();
            pendingDraftRecovery = null;
            draftWasRestored = false;
            renderSubmissionSuccess(result.message);
            disconnectStickyActionObserver();
            clearSurveyLayoutOffsets();
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
window.addEventListener('resize', syncSurveyLayoutOffsets);
trackSurveyAccess();
renderSurveyExperience({alignActiveStep: true});
</script>
<?php endif; ?>
</body>
</html>
