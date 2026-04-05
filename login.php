<?php

require_once __DIR__ . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireGuest();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::setOld($_POST);

    if (!auth()->validateCsrf($_POST[CSRF_TOKEN_NAME] ?? null)) {
        $error = 'La sesión expiró. Recargue la página e intente de nuevo.';
    } elseif (!auth()->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        $error = 'Credenciales incorrectas.';
    } else {
        Helpers::clearOld();
        flash('success', 'Bienvenido al panel administrativo.');
        redirect('dashboard.php');
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="public-shell">
    <section class="public-hero">
        <div class="public-container">
            <div class="hero-card">
                <span class="chip chip-muted">Acceso administrativo</span>
                <div>
                    <h1>Control total de encuestas</h1>
                    <p>Cree múltiples encuestas, cargue preguntas por secciones o de forma masiva y siga indicadores en tiempo real con una interfaz optimizada para campo y análisis.</p>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="post" class="panel">
                    <?= csrf_field() ?>
                    <div class="stack">
                        <div class="field">
                            <label>Correo</label>
                            <input type="email" name="email" value="<?= e(old('email')) ?>" required>
                        </div>
                        <div class="field">
                            <label>Contraseña</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="actions-inline">
                            <button class="btn btn-primary" type="submit">Ingresar</button>
                        </div>
                    </div>
                </form>
                <div class="chip chip-warning">Usuario inicial: admin@shalom.local / Shalom2026!</div>
            </div>
        </div>
    </section>
</body>
</html>
