<?php
$currentPage = $currentPage ?? '';
$pageTitle = $pageTitle ?? 'Panel administrativo';
$pageDescription = $pageDescription ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$authUser = auth()->user();
$canManageSurveys = auth()->canManageSurveys();
$canAccessInsights = auth()->canAccessInsights();
$canManageUsers = auth()->canManageUsers();
$workspaceLinks = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => url('dashboard.php')],
];

if ($canManageSurveys) {
    $workspaceLinks[] = ['key' => 'surveys', 'label' => 'Encuestas', 'icon' => 'clipboard-list', 'url' => url('encuestas/index.php')];
}

if ($canAccessInsights) {
    $workspaceLinks[] = ['key' => 'responses', 'label' => 'Respuestas', 'icon' => 'messages-square', 'url' => url('respuestas/index.php')];
    $workspaceLinks[] = ['key' => 'reports', 'label' => 'Reportes', 'icon' => 'chart-column-big', 'url' => url('reportes/index.php')];
    $workspaceLinks[] = ['key' => 'question-analysis', 'label' => 'Análisis por pregunta', 'icon' => 'search', 'url' => url('reportes/preguntas.php')];
    $workspaceLinks[] = ['key' => 'homologation', 'label' => 'Homologación', 'icon' => 'workflow', 'url' => url('homologacion/index.php')];
}

if ($canManageUsers) {
    $workspaceLinks[] = ['key' => 'users', 'label' => 'Usuarios', 'icon' => 'users-round', 'url' => url('usuarios/index.php')];
}

$workspaceLinks[] = ['key' => 'public-form', 'label' => 'Formulario público', 'icon' => 'external-link', 'url' => url('public/index.php')];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
    <script defer src="<?= asset('js/app.js') ?>"></script>
</head>
<body class="app-shell admin-body page-<?= e($currentPage !== '' ? $currentPage : 'admin') ?>">
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">S</div>
            <div>
                <strong>Shalom Encuestas</strong>
                <span>Panel administrativo</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= url('dashboard.php') ?>">
                <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
            </a>
            <?php if ($canManageSurveys): ?>
                <a class="nav-link <?= $currentPage === 'surveys' ? 'active' : '' ?>" href="<?= url('encuestas/index.php') ?>">
                    <i data-lucide="clipboard-list"></i><span>Encuestas</span>
                </a>
            <?php endif; ?>
            <?php if ($canAccessInsights): ?>
                <a class="nav-link <?= $currentPage === 'responses' ? 'active' : '' ?>" href="<?= url('respuestas/index.php') ?>">
                    <i data-lucide="messages-square"></i><span>Respuestas</span>
                </a>
                <a class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>" href="<?= url('reportes/index.php') ?>">
                    <i data-lucide="chart-column-big"></i><span>Reportes</span>
                </a>
                <a class="nav-link <?= $currentPage === 'question-analysis' ? 'active' : '' ?>" href="<?= url('reportes/preguntas.php') ?>">
                    <i data-lucide="search"></i><span>Análisis por pregunta</span>
                </a>
                <a class="nav-link <?= $currentPage === 'homologation' ? 'active' : '' ?>" href="<?= url('homologacion/index.php') ?>">
                    <i data-lucide="workflow"></i><span>Homologación</span>
                </a>
            <?php endif; ?>
            <?php if ($canManageUsers): ?>
                <a class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>" href="<?= url('usuarios/index.php') ?>">
                    <i data-lucide="users-round"></i><span>Usuarios</span>
                </a>
            <?php endif; ?>
            <a class="nav-link" href="<?= url('public/index.php') ?>" target="_blank" rel="noreferrer">
                <i data-lucide="external-link"></i><span>Formulario público</span>
            </a>
        </nav>
        <div class="sidebar-support">
            <span class="chip chip-outline-light">Centro operativo</span>
            <p>Use los accesos rápidos del panel para cambiar de módulo sin perder contexto.</p>
        </div>
        <div class="sidebar-footer">
            <span><?= e($authUser['full_name'] ?? 'Administrador') ?></span>
            <small><?= e(Helpers::userRoleLabel((string) ($authUser['role'] ?? 'editor'))) ?></small>
            <a href="<?= url('logout.php') ?>">Cerrar sesión</a>
        </div>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div class="topbar-copy">
                <span class="topbar-eyebrow">Panel administrativo</span>
                <div class="breadcrumbs">
                    <a href="<?= url('dashboard.php') ?>">Inicio</a>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        <span>/</span>
                        <?php if (!empty($breadcrumb['url'])): ?>
                            <a href="<?= e($breadcrumb['url']) ?>"><?= e($breadcrumb['title']) ?></a>
                        <?php else: ?>
                            <strong><?= e($breadcrumb['title']) ?></strong>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <h1><?= e($pageTitle) ?></h1>
                <?php if ($pageDescription !== ''): ?>
                    <p><?= e($pageDescription) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar-actions">
                <span class="chip chip-muted"><?= e(Helpers::userRoleLabel((string) ($authUser['role'] ?? 'editor'))) ?></span>
                <span class="chip chip-muted"><?= date('d/m/Y H:i') ?></span>
            </div>
        </header>
        <nav class="workspace-bar" aria-label="Accesos rápidos del panel">
            <?php foreach ($workspaceLinks as $workspaceLink): ?>
                <?php
                $isExternal = $workspaceLink['key'] === 'public-form';
                $isActive = $workspaceLink['key'] === $currentPage;
                ?>
                <a
                    class="workspace-link <?= $isActive ? 'active' : '' ?>"
                    href="<?= e($workspaceLink['url']) ?>"
                    <?= $isExternal ? 'target="_blank" rel="noreferrer"' : '' ?>
                >
                    <i data-lucide="<?= e($workspaceLink['icon']) ?>"></i>
                    <span><?= e($workspaceLink['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="page-content">
            <?php if ($message = flash('success')): ?>
                <div class="alert alert-success"><?= e($message) ?></div>
            <?php endif; ?>
            <?php if ($message = flash('error')): ?>
                <div class="alert alert-danger"><?= e($message) ?></div>
            <?php endif; ?>
