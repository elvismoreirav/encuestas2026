<?php

class Helpers
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return $path === '' ? BASE_URL : BASE_URL . '/' . $path;
    }

    public static function asset(string $path): string
    {
        return ASSETS_URL . '/' . ltrim($path, '/');
    }

    public static function redirect(string $path = ''): never
    {
        header('Location: ' . self::url($path));
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        return $_SESSION['_old'][$key] ?? $default;
    }

    public static function setOld(array $data): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        $_SESSION['_old'] = $data;
    }

    public static function clearOld(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }

        unset($_SESSION['_old']);
    }

    public static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?: '';
        return trim($value, '-') ?: 'encuesta';
    }

    public static function decodeJson(?string $value, mixed $default = []): mixed
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public static function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public static function requestJson(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function isAjax(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json') || strtolower($requestedWith) === 'xmlhttprequest';
    }

    public static function formatDateTime(?string $value): string
    {
        if (!$value) {
            return 'Sin registro';
        }

        return date('d/m/Y H:i', strtotime($value));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activa',
            'scheduled' => 'Programada',
            'closed' => 'Cerrada',
            'archived' => 'Archivada',
            default => 'Borrador',
        };
    }

    public static function userRoleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super administrador',
            'analyst' => 'Analista',
            default => 'Administrativo',
        };
    }

    public static function userStatusLabel(string $status): string
    {
        return $status === 'active' ? 'Activo' : 'Inactivo';
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => SESSION_SECURE,
            'httponly' => SESSION_HTTPONLY,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
