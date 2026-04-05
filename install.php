<?php

require_once __DIR__ . '/bootstrap.php';

if (Database::isInstalled()) {
    redirect('login.php');
}

function persistInstallConfig(array $overrides): void
{
    $configFile = CONFIG_PATH . '/local.php';
    $currentConfig = [];

    if (is_file($configFile)) {
        $currentConfig = require $configFile;
        if (!is_array($currentConfig)) {
            $currentConfig = [];
        }
    }

    $configContent = "<?php\n\nreturn " . var_export(array_replace($currentConfig, $overrides), true) . ";\n";

    if (file_put_contents($configFile, $configContent, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar la configuración local del instalador.');
    }
}

$message = null;
$error = null;
$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string) ($_POST['db_host'] ?? DB_HOST));
    $dbUser = trim((string) ($_POST['db_user'] ?? DB_USER));
    $dbPass = (string) ($_POST['db_pass'] ?? DB_PASS);

    try {
        if ($dbHost === '') {
            throw new InvalidArgumentException('El host de la base de datos es obligatorio.');
        }

        if ($dbUser === '') {
            throw new InvalidArgumentException('El usuario de la base de datos es obligatorio.');
        }

        Database::setConnectionOverride([
            'host' => $dbHost,
            'user' => $dbUser,
            'pass' => $dbPass,
        ]);
        $pdo = Database::createPdo(false);
        persistInstallConfig([
            'DB_HOST' => $dbHost,
            'DB_USER' => $dbUser,
            'DB_PASS' => $dbPass,
        ]);
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
        Database::clearConnectionOverride();
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
                                    <input name="db_host" value="<?= e($dbHost) ?>" required>
                                </div>
                                <div class="field">
                                    <label>Base de datos</label>
                                    <input value="<?= e(DB_NAME) ?>" disabled>
                                </div>
                                <div class="field">
                                    <label>Usuario DB</label>
                                    <input name="db_user" value="<?= e($dbUser) ?>" required>
                                </div>
                                <div class="field">
                                    <label>Clave DB</label>
                                    <input name="db_pass" type="password" value="<?= e($dbPass) ?>" autocomplete="current-password">
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
