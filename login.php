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
    <section class="public-hero login-hero">
        <div class="public-container login-shell">
            <div class="login-overview">
                <span class="chip chip-muted">Acceso administrativo</span>
                <div class="login-brand">
                    <span class="brand-mark">SE</span>
                    <div>
                        <strong><?= e(APP_NAME) ?></strong>
                        <span>Panel interno protegido</span>
                    </div>
                </div>
                <div class="login-copy">
                    <h1>Acceso seguro para operación y análisis</h1>
                    <p>Administre encuestas, usuarios, respuestas y reportes desde una interfaz interna enfocada en control operativo, seguimiento y trazabilidad.</p>
                </div>
                <div class="login-points">
                    <article class="login-point">
                        <strong>Credenciales no expuestas</strong>
                        <p>Esta pantalla no muestra usuarios ni contraseñas predefinidas. El acceso debe entregarse por un canal administrativo controlado.</p>
                    </article>
                    <article class="login-point">
                        <strong>Ingreso orientado al trabajo interno</strong>
                        <p>Use su cuenta asignada para gestionar encuestas activas, revisar cobertura y consultar resultados consolidados.</p>
                    </article>
                    <article class="login-point">
                        <strong>Soporte de acceso</strong>
                        <p>Si necesita restablecer credenciales o habilitar un perfil, coordínelo con el responsable del sistema.</p>
                    </article>
                </div>
            </div>
            <div class="hero-card login-card">
                <div class="login-card-header">
                    <span class="chip chip-muted">Ingreso</span>
                    <div>
                        <h2>Iniciar sesión</h2>
                        <p>Ingrese con su cuenta administrativa. Las credenciales sensibles no se muestran en esta vista.</p>
                    </div>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="post" class="login-form">
                    <?= csrf_field() ?>
                    <div class="stack">
                        <div class="field">
                            <label for="login-email">Correo</label>
                            <input id="login-email" type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus>
                        </div>
                        <div class="field">
                            <label for="login-password">Contraseña</label>
                            <input id="login-password" type="password" name="password" autocomplete="current-password" required>
                        </div>
                        <div class="panel-muted login-note">
                            El acceso inicial y cualquier restablecimiento de contraseña deben gestionarse fuera de esta pantalla.
                        </div>
                        <div class="actions-inline">
                            <button class="btn btn-primary login-submit" type="submit">Ingresar al panel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</body>
</html>
