<?php

require_once __DIR__ . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireLogin();

$authUser = auth()->user();
$summary = surveys()->dashboardSummary($authUser);
$canManageSurveys = auth()->canManageSurveys();
$canAccessInsights = auth()->canAccessInsights();

$pageTitle = 'Dashboard';
$pageDescription = 'Monitoreo ejecutivo de encuestas, respuestas y ventanas activas.';
$currentPage = 'dashboard';

require TEMPLATES_PATH . '/admin_header.php';
?>
<div class="grid-cards">
    <article class="card">
        <div class="metric-value"><?= (int) $summary['totals']['surveys'] ?></div>
        <div class="metric-label">Encuestas configuradas</div>
        <div class="metric-foot"><?= (int) $summary['totals']['active'] ?> activas y <?= (int) $summary['totals']['scheduled'] ?> programadas</div>
    </article>
    <article class="card">
        <div class="metric-value"><?= (int) $summary['totals']['responses'] ?></div>
        <div class="metric-label">Respuestas registradas</div>
        <div class="metric-foot"><?= (int) $summary['totals']['responses_today'] ?> ingresadas hoy</div>
    </article>
    <article class="card">
        <div class="metric-value"><?= (int) $summary['totals']['draft'] ?></div>
        <div class="metric-label">Borradores</div>
        <div class="metric-foot">Listos para edición y carga masiva</div>
    </article>
    <article class="card">
        <div class="metric-value"><?= (int) $summary['totals']['closed'] ?></div>
        <div class="metric-label">Encuestas cerradas</div>
        <div class="metric-foot">Con historial disponible para reportes</div>
    </article>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Estado de encuestas</h2>
            <p>Control de fechas, estructura y volumen de respuestas.</p>
        </div>
        <?php if ($canManageSurveys): ?>
            <a class="btn btn-primary" href="<?= url('encuestas/index.php') ?>">Administrar encuestas</a>
        <?php elseif ($canAccessInsights): ?>
            <a class="btn btn-secondary" href="<?= url('reportes/index.php') ?>">Ir a reportes</a>
        <?php endif; ?>
    </div>
    <?php if ($summary['surveys'] === []): ?>
        <div class="empty-state">No hay encuestas disponibles para su usuario en este momento.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Encuesta</th>
                    <th>Ventana</th>
                    <th>Estructura</th>
                    <th>Respuestas</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary['surveys'] as $survey): ?>
                    <tr>
                        <td>
                            <strong><?= e($survey['name']) ?></strong><br>
                            <span class="<?= e(str_contains($survey['window_status'], 'active') ? 'chip chip-success' : (str_contains($survey['window_status'], 'scheduled') ? 'chip chip-warning' : 'chip chip-muted')) ?>">
                                <?= e($survey['status_label']) ?>
                            </span>
                        </td>
                        <td>
                            <?= e(Helpers::formatDateTime($survey['start_at'])) ?><br>
                            <small>hasta <?= e(Helpers::formatDateTime($survey['end_at'])) ?></small>
                        </td>
                        <td><?= (int) $survey['section_count'] ?> secciones / <?= (int) $survey['question_count'] ?> preguntas</td>
                        <td><?= (int) $survey['response_count'] ?></td>
                        <td class="actions-inline">
                            <?php if ($canManageSurveys): ?>
                                <a class="btn btn-secondary" href="<?= url('encuestas/editor.php?id=' . (int) $survey['id']) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if ($canAccessInsights): ?>
                                <a class="btn btn-secondary" href="<?= url('reportes/index.php?survey_id=' . (int) $survey['id']) ?>">Ver reporte</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Últimas respuestas</h2>
            <p>Trazabilidad reciente para monitoreo operativo.</p>
        </div>
        <?php if ($canAccessInsights): ?>
            <a class="btn btn-secondary" href="<?= url('respuestas/index.php') ?>">Ir a respuestas</a>
        <?php endif; ?>
    </div>
    <?php if ($summary['latest_responses'] === []): ?>
        <div class="empty-state">Todavía no existen respuestas registradas.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Código</th>
                    <th>Encuesta</th>
                    <th>Fecha</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary['latest_responses'] as $response): ?>
                    <tr>
                        <td><?= e($response['response_uuid']) ?></td>
                        <td><?= e($response['survey_name']) ?></td>
                        <td><?= e(Helpers::formatDateTime($response['submitted_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
