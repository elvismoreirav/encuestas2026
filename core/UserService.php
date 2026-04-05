<?php

class UserService
{
    private static ?UserService $instance = null;
    private Database $db;
    private ?bool $assignmentTableAvailable = null;

    private function __construct()
    {
        $this->db = db();
    }

    public static function getInstance(): UserService
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function roleOptions(): array
    {
        return [
            [
                'value' => 'super_admin',
                'label' => Helpers::userRoleLabel('super_admin'),
                'description' => 'Control total de usuarios, encuestas, respuestas y reportes.',
            ],
            [
                'value' => 'editor',
                'label' => Helpers::userRoleLabel('editor'),
                'description' => 'Administra únicamente las encuestas que tenga asignadas.',
            ],
            [
                'value' => 'analyst',
                'label' => Helpers::userRoleLabel('analyst'),
                'description' => 'Consulta respuestas y reportes de las encuestas asignadas.',
            ],
        ];
    }

    public function listUsers(): array
    {
        $assignmentCountSelect = $this->hasAssignmentTable()
            ? 'COUNT(DISTINCT sua_count.survey_id)'
            : '0';
        $assignmentJoin = $this->hasAssignmentTable()
            ? 'LEFT JOIN survey_user_assignments sua_count ON sua_count.user_id = u.id'
            : '';

        $users = $this->db->fetchAll(
            "SELECT
                u.id,
                u.full_name,
                u.email,
                u.role,
                u.status,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                {$assignmentCountSelect} AS assigned_survey_count
             FROM admin_users u
             {$assignmentJoin}
             GROUP BY u.id
             ORDER BY FIELD(u.role, 'super_admin', 'editor', 'analyst'), u.full_name ASC, u.id ASC"
        );

        $assignmentsByUser = $this->fetchAssignmentsByUser();

        foreach ($users as &$user) {
            $userId = (int) $user['id'];
            $user['role_label'] = Helpers::userRoleLabel((string) $user['role']);
            $user['status_label'] = Helpers::userStatusLabel((string) $user['status']);
            $user['assigned_surveys'] = $assignmentsByUser[$userId] ?? [];
            $user['assigned_survey_ids'] = array_map(
                static fn(array $assignment): int => (int) $assignment['survey_id'],
                $user['assigned_surveys']
            );
        }
        unset($user);

        return $users;
    }

    public function getUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        foreach ($this->listUsers() as $user) {
            if ((int) $user['id'] === $userId) {
                return $user;
            }
        }

        return null;
    }

    public function saveUser(array $payload, int $actorId): int
    {
        $userId = (int) ($payload['id'] ?? 0);
        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')), 'UTF-8');
        $password = (string) ($payload['password'] ?? '');
        $role = (string) ($payload['role'] ?? 'editor');
        $status = (string) ($payload['status'] ?? 'active');
        $surveyIds = $this->normalizeSurveyIds($payload['assigned_surveys'] ?? []);

        if ($fullName === '') {
            throw new InvalidArgumentException('El nombre del usuario es obligatorio.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Debe ingresar un correo válido.');
        }

        if (!in_array($role, ['super_admin', 'editor', 'analyst'], true)) {
            throw new InvalidArgumentException('El rol seleccionado no es válido.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('El estado seleccionado no es válido.');
        }

        if ($userId <= 0 && strlen($password) < 8) {
            throw new InvalidArgumentException('La contraseña inicial debe tener al menos 8 caracteres.');
        }

        $existing = $this->db->fetch(
            'SELECT id, role, status FROM admin_users WHERE email = :email AND id <> :id LIMIT 1',
            [':email' => $email, ':id' => $userId]
        );

        if ($existing) {
            throw new InvalidArgumentException('Ya existe un usuario registrado con ese correo.');
        }

        $currentUser = $userId > 0
            ? $this->db->fetch('SELECT id, role, status FROM admin_users WHERE id = :id LIMIT 1', [':id' => $userId])
            : null;

        if ($userId > 0 && !$currentUser) {
            throw new InvalidArgumentException('El usuario solicitado no existe.');
        }

        if ($userId > 0 && $userId === $actorId) {
            if ($status !== 'active') {
                throw new InvalidArgumentException('No puede desactivar su propio usuario.');
            }

            if ($role !== (string) $currentUser['role']) {
                throw new InvalidArgumentException('No puede cambiar su propio rol desde esta pantalla.');
            }
        }

        if ($userId > 0 && $currentUser && (string) $currentUser['role'] === 'super_admin') {
            $isRoleChanging = $role !== 'super_admin';
            $isDeactivating = $status !== 'active';
            if (($isRoleChanging || $isDeactivating) && !$this->hasAnotherActiveSuperAdmin($userId)) {
                throw new InvalidArgumentException('Debe existir al menos un super administrador activo en el sistema.');
            }
        }

        $validSurveyIds = $this->filterExistingSurveyIds($surveyIds);
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();

        try {
            if ($userId > 0) {
                $updateData = [
                    'full_name' => $fullName,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status,
                    'updated_at' => $now,
                ];

                if (trim($password) !== '') {
                    if (strlen($password) < 8) {
                        throw new InvalidArgumentException('La nueva contraseña debe tener al menos 8 caracteres.');
                    }
                    $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $this->db->update('admin_users', $updateData, 'id = :id', [':id' => $userId]);
            } else {
                $userId = $this->db->insert('admin_users', [
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($this->hasAssignmentTable()) {
                $this->syncAssignments($userId, $role === 'super_admin' ? [] : $validSurveyIds, $actorId, $now);
            }

            $this->db->commit();
            return $userId;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function deleteUser(int $userId, int $actorId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($userId === $actorId) {
            throw new InvalidArgumentException('No puede eliminar su propio usuario.');
        }

        $user = $this->db->fetch('SELECT id, role FROM admin_users WHERE id = :id LIMIT 1', [':id' => $userId]);
        if (!$user) {
            throw new InvalidArgumentException('El usuario solicitado no existe.');
        }

        if ((string) $user['role'] === 'super_admin' && !$this->hasAnotherActiveSuperAdmin($userId)) {
            throw new InvalidArgumentException('Debe permanecer al menos un super administrador activo.');
        }

        return $this->db->delete('admin_users', 'id = :id', [':id' => $userId]) > 0;
    }

    private function fetchAssignmentsByUser(): array
    {
        if (!$this->hasAssignmentTable()) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT
                sua.user_id,
                sua.survey_id,
                s.name AS survey_name,
                s.slug AS survey_slug
             FROM survey_user_assignments sua
             INNER JOIN surveys s ON s.id = sua.survey_id
             ORDER BY s.name ASC"
        );

        $assignments = [];
        foreach ($rows as $row) {
            $assignments[(int) $row['user_id']][] = [
                'survey_id' => (int) $row['survey_id'],
                'survey_name' => $row['survey_name'],
                'survey_slug' => $row['survey_slug'],
            ];
        }

        return $assignments;
    }

    private function syncAssignments(int $userId, array $surveyIds, int $actorId, string $now): void
    {
        $this->db->delete('survey_user_assignments', 'user_id = :user_id', [':user_id' => $userId]);

        foreach ($surveyIds as $surveyId) {
            $this->db->insert('survey_user_assignments', [
                'user_id' => $userId,
                'survey_id' => $surveyId,
                'assigned_by' => $actorId ?: null,
                'created_at' => $now,
            ]);
        }
    }

    private function normalizeSurveyIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn(mixed $item): int => (int) $item,
            $value
        ), static fn(int $id): bool => $id > 0)));

        sort($ids);
        return $ids;
    }

    private function filterExistingSurveyIds(array $surveyIds): array
    {
        if ($surveyIds === []) {
            return [];
        }

        $placeholder = implode(',', array_fill(0, count($surveyIds), '?'));
        $rows = $this->db->fetchAll("SELECT id FROM surveys WHERE id IN ({$placeholder})", $surveyIds);

        $validIds = array_map(static fn(array $row): int => (int) $row['id'], $rows);
        sort($validIds);

        return $validIds;
    }

    private function hasAnotherActiveSuperAdmin(int $excludedUserId): bool
    {
        $count = (int) $this->db->fetchColumn(
            "SELECT COUNT(*)
             FROM admin_users
             WHERE role = 'super_admin' AND status = 'active' AND id <> :id",
            [':id' => $excludedUserId]
        );

        return $count > 0;
    }

    private function hasAssignmentTable(): bool
    {
        if ($this->assignmentTableAvailable !== null) {
            return $this->assignmentTableAvailable;
        }

        $this->assignmentTableAvailable = $this->tableExists('survey_user_assignments');
        return $this->assignmentTableAvailable;
    }

    private function tableExists(string $table): bool
    {
        try {
            $quoted = $this->db->pdo()->quote($table);
            return (bool) $this->db->fetchColumn("SHOW TABLES LIKE {$quoted}");
        } catch (Throwable) {
            return false;
        }
    }
}
