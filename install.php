<?php

require_once __DIR__ . '/bootstrap.php';

if (Database::isInstalled()) {
    redirect('admin/');
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

    $mergedConfig = array_replace($currentConfig, $overrides);

    if (is_file($configFile) && !is_writable($configFile)) {
        foreach ($overrides as $key => $value) {
            if (($currentConfig[$key] ?? null) !== $value) {
                throw new RuntimeException('`config/local.php` no es escribible. Actualice ese archivo manualmente con las credenciales correctas antes de instalar.');
            }
        }

        return;
    }

    if (!is_file($configFile) && !is_writable(CONFIG_PATH)) {
        throw new RuntimeException('La carpeta `config/` no tiene permisos de escritura. Cree `config/local.php` manualmente antes de instalar.');
    }

    $configContent = "<?php\n\nreturn " . var_export($mergedConfig, true) . ";\n";

    if (file_put_contents($configFile, $configContent, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar la configuración local del instalador.');
    }
}

function shouldCreateDatabase(PDOException $exception): bool
{
    $errorInfo = $exception->errorInfo ?? [];
    $mysqlCode = isset($errorInfo[1]) ? (int) $errorInfo[1] : 0;

    return $mysqlCode === 1049 || str_contains(strtolower($exception->getMessage()), 'unknown database');
}

$message = null;
$error = null;
$configFileLocked = is_file(CONFIG_PATH . '/local.php') && !is_writable(CONFIG_PATH . '/local.php');
$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$configFileLocked) {
        $dbHost = trim((string) ($_POST['db_host'] ?? DB_HOST));
        $dbUser = trim((string) ($_POST['db_user'] ?? DB_USER));
        $dbPass = (string) ($_POST['db_pass'] ?? DB_PASS);
    }

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
        if (!$configFileLocked) {
            persistInstallConfig([
                'DB_HOST' => $dbHost,
                'DB_USER' => $dbUser,
                'DB_PASS' => $dbPass,
            ]);
        }

        try {
            $pdo = Database::createPdo(true);
        } catch (PDOException $exception) {
            if (!shouldCreateDatabase($exception)) {
                throw $exception;
            }

            $pdo = Database::createPdo(false);
            $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', DB_NAME));
            $pdo->exec(sprintf('USE `%s`', DB_NAME));
        }

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
                    <p>Si la base ya existe y `config/local.php` fue cargado manualmente, el instalador usará esa configuración sin reescribir el archivo.</p>
                </div>
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= e($message) ?></div>
                    <div class="actions-inline">
                        <a class="btn btn-primary" href="<?= url('admin/') ?>">Ir al acceso administrativo</a>
                    </div>
                <?php else: ?>
                    <?php if ($configFileLocked): ?>
                        <div class="alert alert-info">`config/local.php` está en modo solo lectura. El instalador usará esos valores y no intentará modificarlos.</div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" class="stack">
                        <div class="panel">
                            <div class="form-grid">
                                <div class="field">
                                    <label>Host</label>
                                    <input name="db_host" value="<?= e($dbHost) ?>" <?= $configFileLocked ? 'readonly' : 'required' ?>>
                                </div>
                                <div class="field">
                                    <label>Base de datos</label>
                                    <input value="<?= e(DB_NAME) ?>" disabled>
                                </div>
                                <div class="field">
                                    <label>Usuario DB</label>
                                    <input name="db_user" value="<?= e($dbUser) ?>" <?= $configFileLocked ? 'readonly' : 'required' ?>>
                                </div>
                                <div class="field">
                                    <label>Clave DB</label>
                                    <input name="db_pass" type="password" value="<?= e($dbPass) ?>" autocomplete="current-password" <?= $configFileLocked ? 'readonly' : '' ?>>
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
