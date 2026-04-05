<?php

class Auth
{
    private static ?Auth $instance = null;
    private ?array $user = null;
    private ?array $assignedSurveyIds = null;
    private ?bool $assignmentTableAvailable = null;

    private function __construct()
    {
        Helpers::startSession();
        $this->loadUser();
    }

    public static function getInstance(): Auth
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user ? (int) $this->user['id'] : null;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function role(): ?string
    {
        return $this->user ? (string) $this->user['role'] : null;
    }

    public function hasRole(string|array $roles): bool
    {
        if (!$this->check()) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        return in_array((string) $this->user['role'], $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageSurveys(): bool
    {
        return $this->hasRole(['super_admin', 'editor']);
    }

    public function canAccessInsights(): bool
    {
        return $this->hasRole(['super_admin', 'editor', 'analyst']);
    }

    public function assignedSurveyIds(): array
    {
        if (!$this->check() || $this->isSuperAdmin()) {
            return [];
        }

        if (!$this->hasAssignmentTable()) {
            return [];
        }

        if ($this->assignedSurveyIds !== null) {
            return $this->assignedSurveyIds;
        }

        $rows = db()->fetchAll(
            'SELECT survey_id FROM survey_user_assignments WHERE user_id = :user_id ORDER BY survey_id ASC',
            [':user_id' => $this->id()]
        );

        $this->assignedSurveyIds = array_map(static fn(array $row): int => (int) $row['survey_id'], $rows);
        return $this->assignedSurveyIds;
    }

    public function canViewSurvey(int $surveyId): bool
    {
        if (!$this->canAccessInsights()) {
            return false;
        }

        if ($this->isSuperAdmin() || !$this->hasAssignmentTable()) {
            return true;
        }

        return in_array($surveyId, $this->assignedSurveyIds(), true);
    }

    public function canEditSurvey(int $surveyId): bool
    {
        if (!$this->canManageSurveys()) {
            return false;
        }

        if ($this->isSuperAdmin() || !$this->hasAssignmentTable()) {
            return true;
        }

        return in_array($surveyId, $this->assignedSurveyIds(), true);
    }

    public function attempt(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $user = db()->fetch(
            'SELECT * FROM admin_users WHERE email = :email AND status = :status LIMIT 1',
            [':email' => $email, ':status' => 'active']
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        db()->update('admin_users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $user['id']]);
        $this->user = $user;
        $this->assignedSurveyIds = null;

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        $this->user = null;
        $this->assignedSurveyIds = null;
        $this->assignmentTableAvailable = null;
        self::$instance = null;
    }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            Helpers::redirect('login.php');
        }
    }

    public function requireGuest(): void
    {
        if ($this->check()) {
            Helpers::redirect('dashboard.php');
        }
    }

    public function requireManageUsers(): void
    {
        if (!$this->canManageUsers()) {
            $this->deny('No tiene permisos para administrar usuarios.');
        }
    }

    public function requireManageSurveys(): void
    {
        if (!$this->canManageSurveys()) {
            $this->deny('No tiene permisos para administrar encuestas.');
        }
    }

    public function requireInsightsAccess(): void
    {
        if (!$this->canAccessInsights()) {
            $this->deny('No tiene permisos para consultar respuestas o reportes.');
        }
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public function validateCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }

    private function loadUser(): void
    {
        if (!Database::isInstalled()) {
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return;
        }

        $user = db()->fetch('SELECT * FROM admin_users WHERE id = :id AND status = :status LIMIT 1', [
            ':id' => $userId,
            ':status' => 'active',
        ]);

        if ($user) {
            $this->user = $user;
            $this->assignedSurveyIds = null;
            return;
        }

        unset($_SESSION['user_id']);
    }

    private function deny(string $message): never
    {
        if (Helpers::isAjax()) {
            Helpers::json(['success' => false, 'message' => $message], 403);
        }

        Helpers::flash('error', $message);
        Helpers::redirect('dashboard.php');
    }

    private function hasAssignmentTable(): bool
    {
        if ($this->assignmentTableAvailable !== null) {
            return $this->assignmentTableAvailable;
        }

        try {
            $quoted = db()->pdo()->quote('survey_user_assignments');
            $this->assignmentTableAvailable = (bool) db()->fetchColumn("SHOW TABLES LIKE {$quoted}");
        } catch (Throwable) {
            $this->assignmentTableAvailable = false;
        }

        return $this->assignmentTableAvailable;
    }
}
