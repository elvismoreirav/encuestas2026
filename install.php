<?php

require_once __DIR__ . '/bootstrap.php';

if (Database::isInstalled()) {
    redirect('login.php');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::createPdo(false);
        $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', DB_NAME));
        $pdo->exec(sprintf('USE `%s`', DB_NAME));

        $statements = array_filter(array_map('trim', explode(";\n", file_get_contents(DATABASE_PATH . '/schema.sql') ?: '')));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        Database::reset();

        $adminId = (int) db()->fetchColumn('SELECT id FROM admin_users WHERE email = :email LIMIT 1', [
            ':email' => 'admin@shalom.local',
        ]);

        if ($adminId === 0) {
            $adminId = db()->insert('admin_users', [
                'full_name' => 'Administrador Shalom',
                'email' => 'admin@shalom.local',
                'password_hash' => password_hash('Shalom2026!', PASSWORD_DEFAULT),
                'role' => 'super_admin',
                'status' => 'active',
            ]);
        }

        $surveyExists = (int) db()->fetchColumn('SELECT COUNT(*) FROM surveys');
        if ($surveyExists === 0) {
            $definition = require DATABASE_PATH . '/default_survey.php';
            surveys()->seedSurvey($definition, $adminId);
        }

        $message = 'Instalación completada. Usuario: admin@shalom.local / Clave: Shalom2026!';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalador | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="public-shell">
    <section class="public-hero">
        <div class="public-container">
            <div class="hero-card">
                <span class="chip chip-muted">Instalación inicial</span>
                <div>
                    <h1>Shalom Encuestas</h1>
                    <p>Configuración automatizada de base de datos, usuario administrador y encuesta semilla de abril 2026.</p>
                </div>
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= e($message) ?></div>
                    <div class="actions-inline">
                        <a class="btn btn-primary" href="<?= url('login.php') ?>">Ir al acceso</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" class="stack">
                        <div class="panel">
                            <div class="form-grid">
                                <div class="field">
                                    <label>Host</label>
                                    <input value="<?= e(DB_HOST) ?>" disabled>
                                </div>
                                <div class="field">
                                    <label>Base de datos</label>
                                    <input value="<?= e(DB_NAME) ?>" disabled>
                                </div>
                                <div class="field">
                                    <label>Usuario DB</label>
                                    <input value="<?= e(DB_USER) ?>" disabled>
                                </div>
                                <div class="field">
                                    <label>Zona horaria</label>
                                    <input value="<?= e(TIMEZONE) ?>" disabled>
                                </div>
                            </div>
                        </div>
                        <div class="actions-inline">
                            <button class="btn btn-primary" type="submit">Instalar sistema</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
