<?php

require_once __DIR__ . '/../bootstrap.php';

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
<body class="public-shell login-body">
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
                    <h1>Panel de acceso administrativo</h1>
                    <p>Gestione encuestas, respuestas y reportes desde un entorno interno protegido.</p>
                </div>
                <div class="login-badge-list">
                    <article class="login-badge">
                        <strong>Sin claves visibles</strong>
                        <p>El acceso inicial debe entregarse por un canal administrativo controlado.</p>
                    </article>
                    <article class="login-badge">
                        <strong>Operación centralizada</strong>
                        <p>Encuestas, respuestas, usuarios y reportes en un único panel interno.</p>
                    </article>
                    <article class="login-badge">
                        <strong>Soporte y restablecimiento</strong>
                        <p>Si necesita acceso o restablecer su contraseña, contacte al responsable del sistema.</p>
                    </article>
                </div>
                <div class="login-powered">
                    <span>Powered by</span>
                    <img src="<?= asset('img/shalom-wordmark.svg') ?>" alt="Shalom">
                </div>
            </div>
            <div class="hero-card login-card">
                <div class="login-card-header">
                    <div class="login-card-rail" aria-hidden="true"></div>
                    <div>
                        <h2>Iniciar sesión</h2>
                        <p>Use su cuenta administrativa para continuar. Por seguridad, esta pantalla no muestra credenciales.</p>
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
                            <input id="login-email" type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" autocapitalize="off" spellcheck="false" inputmode="email" placeholder="correo@dominio.com" required autofocus>
                        </div>
                        <div class="field">
                            <div class="field-label-row">
                                <label for="login-password">Contraseña</label>
                                <button type="button" class="login-toggle" id="togglePasswordButton" aria-controls="login-password" aria-pressed="false">Mostrar</button>
                            </div>
                            <input id="login-password" type="password" name="password" autocomplete="current-password" placeholder="Ingrese su contraseña" required>
                        </div>
                        <div class="login-note">
                            El acceso inicial y cualquier restablecimiento de contraseña se gestionan fuera de esta pantalla.
                        </div>
                        <div class="actions-inline">
                            <button class="btn btn-primary login-submit" type="submit">Ingresar al panel</button>
                        </div>
                    </div>
                </form>
                <div class="login-card-footer">
                    <span>Entorno interno protegido</span>
                    <span>Sesión validada con CSRF</span>
                </div>
            </div>
        </div>
    </section>
    <script>
    (() => {
        const button = document.getElementById('togglePasswordButton');
        const input = document.getElementById('login-password');

        if (!button || !input) {
            return;
        }

        button.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'Ocultar' : 'Mostrar';
            button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        });
    })();
    </script>
</body>
</html>
