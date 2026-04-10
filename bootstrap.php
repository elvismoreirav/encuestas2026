<?php

define('ENCUESTAS2026', true);

ob_start();

require_once __DIR__ . '/config/config.php';

spl_autoload_register(static function (string $class): void {
    $file = CORE_PATH . '/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

foreach ([LOGS_PATH] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

require_once CORE_PATH . '/Helpers.php';

if (APP_DEBUG) {
    set_exception_handler(static function (Throwable $exception): void {
        error_log(sprintf(
            "[%s] %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));

        if (Helpers::isAjax()) {
            Helpers::json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado.',
                'debug' => [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
            ], 500);
        }

        http_response_code(500);
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Error</title></head><body>';
        echo '<h1>Error interno</h1>';
        echo '<pre>' . Helpers::e($exception->getMessage()) . '</pre>';
        echo '</body></html>';
        exit;
    });
}

function db(): Database
{
    return Database::getInstance();
}

function auth(): Auth
{
    return Auth::getInstance();
}

function surveys(): SurveyService
{
    return SurveyService::getInstance();
}

function users(): UserService
{
    return UserService::getInstance();
}

function e(mixed $value): string
{
    return Helpers::e($value);
}

function url(string $path = ''): string
{
    return Helpers::url($path);
}

function asset(string $path): string
{
    return Helpers::asset($path);
}

function redirect(string $path = ''): never
{
    Helpers::redirect($path);
}

function json_response(array $data, int $status = 200): never
{
    Helpers::json($data, $status);
}

function flash(string $key, ?string $message = null): ?string
{
    return Helpers::flash($key, $message);
}

function old(string $key, mixed $default = ''): mixed
{
    return Helpers::old($key, $default);
}

function csrf_token(): string
{
    return auth()->csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrf_token()) . '">';
}
