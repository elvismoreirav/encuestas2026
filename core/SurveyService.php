<?php

class SurveyService
{
    private const SECONDARY_REPORT_TAG = 'ELVIS PORFAVOR ESTA';

    private static ?SurveyService $instance = null;
    private Database $db;
    private ?bool $surveyAccessLogTableAvailable = null;
    private ?bool $assignmentTableAvailable = null;
    private array $tableExistsCache = [];
    private array $tableColumnsCache = [];

    private function __construct()
    {
        $this->db = db();
    }

    public static function getInstance(): SurveyService
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function buildSurveyAccessConstraint(string $surveyAlias = 's', ?array $user = null): array
    {
        if ($this->userCanAccessAllSurveys($user)) {
            return ['joins' => '', 'where' => '', 'params' => []];
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['joins' => '', 'where' => '', 'params' => []];
        }

        return [
            'joins' => " INNER JOIN survey_user_assignments sua_access ON sua_access.survey_id = {$surveyAlias}.id",
            'where' => ' AND sua_access.user_id = :access_user_id',
            'params' => [':access_user_id' => $userId],
        ];
    }

    private function userCanAccessAllSurveys(?array $user = null): bool
    {
        if (!$user) {
            return true;
        }

        return (string) ($user['role'] ?? '') === 'super_admin' || !$this->hasAssignmentTable();
    }

    private function ensureSurveyReadAccess(int $surveyId, ?array $user = null): void
    {
        if (!$user || $this->userCanAccessAllSurveys($user)) {
            return;
        }

        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, ['editor', 'analyst'], true)) {
            throw new InvalidArgumentException('No tiene permisos para acceder a esta encuesta.');
        }

        $hasAccess = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM survey_user_assignments WHERE user_id = :user_id AND survey_id = :survey_id',
            [':user_id' => (int) $user['id'], ':survey_id' => $surveyId]
        ) > 0;

        if (!$hasAccess) {
            throw new InvalidArgumentException('La encuesta no está asignada a su usuario.');
        }
    }

    private function ensureSurveyWriteAccess(int $surveyId, ?array $user = null): void
    {
        if (!$user) {
            return;
        }

        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, ['super_admin', 'editor'], true)) {
            throw new InvalidArgumentException('No tiene permisos para modificar encuestas.');
        }

        $this->ensureSurveyReadAccess($surveyId, $user);
    }

    private function ensureSurveyAssignment(int $surveyId, int $userId, ?int $assignedBy = null): void
    {
        if ($surveyId <= 0 || $userId <= 0 || !$this->hasAssignmentTable()) {
            return;
        }

        $exists = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM survey_user_assignments WHERE survey_id = :survey_id AND user_id = :user_id',
            [':survey_id' => $surveyId, ':user_id' => $userId]
        );

        if ($exists > 0) {
            return;
        }

        $this->db->insert('survey_user_assignments', [
            'survey_id' => $surveyId,
            'user_id' => $userId,
            'assigned_by' => $assignedBy ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveSurveyIdBySection(int $sectionId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT survey_id FROM survey_sections WHERE id = :id LIMIT 1',
            [':id' => $sectionId]
        );
    }

    private function resolveSurveyIdByQuestion(int $questionId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT survey_id FROM survey_questions WHERE id = :id LIMIT 1',
            [':id' => $questionId]
        );
    }

    public function dashboardSummary(?array $user = null): array
    {
        $access = $this->buildSurveyAccessConstraint('s', $user);

        $surveyTotals = $this->db->fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'active') AS active_count,
                SUM(status = 'scheduled') AS scheduled_count,
                SUM(status = 'draft') AS draft_count,
                SUM(status = 'closed') AS closed_count
            FROM surveys s
            {$access['joins']}
            WHERE 1 = 1 {$access['where']}",
            $access['params']
        ) ?: [];

        $responseAccess = $this->buildSurveyAccessConstraint('s', $user);
        $totalResponses = (int) $this->db->fetchColumn(
            "SELECT COUNT(*)
             FROM survey_responses sr
             INNER JOIN surveys s ON s.id = sr.survey_id
             {$responseAccess['joins']}
             WHERE 1 = 1 {$responseAccess['where']}",
            $responseAccess['params']
        );
        $responsesToday = (int) $this->db->fetchColumn(
            "SELECT COUNT(*)
             FROM survey_responses sr
             INNER JOIN surveys s ON s.id = sr.survey_id
             {$responseAccess['joins']}
             WHERE sr.submitted_at >= CURDATE()
               AND sr.submitted_at < (CURDATE() + INTERVAL 1 DAY) {$responseAccess['where']}",
            $responseAccess['params']
        );

        $latestResponses = $this->db->fetchAll(
            "SELECT sr.id, sr.response_uuid, sr.submitted_at, s.name AS survey_name
             FROM survey_responses sr
             INNER JOIN surveys s ON s.id = sr.survey_id
             {$responseAccess['joins']}
             WHERE 1 = 1 {$responseAccess['where']}
             ORDER BY sr.submitted_at DESC
             LIMIT 8",
            $responseAccess['params']
        );

        return [
            'totals' => [
                'surveys' => (int) ($surveyTotals['total'] ?? 0),
                'active' => (int) ($surveyTotals['active_count'] ?? 0),
                'scheduled' => (int) ($surveyTotals['scheduled_count'] ?? 0),
                'draft' => (int) ($surveyTotals['draft_count'] ?? 0),
                'closed' => (int) ($surveyTotals['closed_count'] ?? 0),
                'responses' => $totalResponses,
                'responses_today' => $responsesToday,
            ],
            'surveys' => $this->listSurveys($user),
            'latest_responses' => $latestResponses,
        ];
    }

    public function listSurveys(?array $user = null): array
    {
        $access = $this->buildSurveyAccessConstraint('s', $user);
        $assignmentCountSelect = $this->hasAssignmentTable()
            ? 'COALESCE(assignment_counts.assigned_user_count, 0)'
            : '0';
        $assignmentJoin = $this->hasAssignmentTable()
            ? "LEFT JOIN (
                    SELECT survey_id, COUNT(*) AS assigned_user_count
                    FROM survey_user_assignments
                    GROUP BY survey_id
                ) assignment_counts ON assignment_counts.survey_id = s.id"
            : '';

        $rows = $this->db->fetchAll(
            "SELECT
                s.*,
                (
                    SELECT COUNT(*)
                    FROM survey_sections sec
                    WHERE sec.survey_id = s.id
                ) AS section_count,
                (
                    SELECT COUNT(*)
                    FROM survey_questions q
                    WHERE q.survey_id = s.id
                ) AS question_count,
                (
                    SELECT COUNT(*)
                    FROM survey_responses sr_count
                    WHERE sr_count.survey_id = s.id
                ) AS response_count,
                {$assignmentCountSelect} AS assigned_user_count
             FROM surveys s
             {$access['joins']}
             {$assignmentJoin}
             WHERE 1 = 1 {$access['where']}
             ORDER BY COALESCE(s.start_at, s.created_at) DESC, s.id DESC",
            $access['params']
        );

        return $this->decorateSurveyListingRows($rows);
    }

    public function listPublicSurveys(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                s.*,
                (
                    SELECT COUNT(*)
                    FROM survey_sections sec
                    WHERE sec.survey_id = s.id
                ) AS section_count,
                (
                    SELECT COUNT(*)
                    FROM survey_questions q
                    WHERE q.survey_id = s.id
                ) AS question_count,
                0 AS response_count,
                0 AS assigned_user_count
             FROM surveys s
             WHERE s.is_public = 1
             ORDER BY COALESCE(s.start_at, s.created_at) DESC, s.id DESC"
        );

        return $this->decorateSurveyListingRows($rows);
    }

    public function listSurveyOptions(?array $user = null): array
    {
        $access = $this->buildSurveyAccessConstraint('s', $user);

        return $this->db->fetchAll(
            "SELECT
                s.id,
                s.name,
                s.slug,
                s.start_at,
                s.created_at
             FROM surveys s
             {$access['joins']}
             WHERE 1 = 1 {$access['where']}
             ORDER BY COALESCE(s.start_at, s.created_at) DESC, s.id DESC",
            $access['params']
        );
    }

    public function getSurvey(int $surveyId, ?array $user = null): ?array
    {
        $access = $this->buildSurveyAccessConstraint('s', $user);
        $survey = $this->db->fetch(
            "SELECT s.*
             FROM surveys s
             {$access['joins']}
             WHERE s.id = :id {$access['where']}
             LIMIT 1",
            [':id' => $surveyId] + $access['params']
        );
        if (!$survey) {
            return null;
        }

        return $this->hydrateSurvey($survey);
    }

    public function getSurveyBySlug(string $slug, bool $publicOnly = false): ?array
    {
        $survey = $this->getSurveyRecordBySlug($slug);
        if (!$survey) {
            return null;
        }

        if ($publicOnly) {
            if (!(bool) $survey['is_public']) {
                return null;
            }

            $windowStatus = $this->resolveWindowStatus($survey);
            if (!in_array($windowStatus, ['active', 'closing_soon'], true)) {
                return null;
            }
        }

        return $this->hydrateSurvey($survey, $publicOnly);
    }

    public function saveSurvey(array $payload, int $userId, ?array $actor = null): int
    {
        $now = date('Y-m-d H:i:s');
        $surveyId = (int) ($payload['id'] ?? 0);
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));
        $slug = $slug !== '' ? Helpers::slugify($slug) : Helpers::slugify($name);

        if ($name === '') {
            throw new InvalidArgumentException('El nombre de la encuesta es obligatorio.');
        }

        $data = [
            'name' => $name,
            'slug' => $this->uniqueSurveySlug($slug, $surveyId),
            'description' => trim((string) ($payload['description'] ?? '')),
            'status' => $payload['status'] ?? 'draft',
            'is_public' => !empty($payload['is_public']) ? 1 : 0,
            'start_at' => $this->normalizeDateTime($payload['start_at'] ?? null),
            'end_at' => $this->normalizeDateTime($payload['end_at'] ?? null),
            'intro_title' => trim((string) ($payload['intro_title'] ?? '')),
            'intro_text' => trim((string) ($payload['intro_text'] ?? '')),
            'thank_you_text' => trim((string) ($payload['thank_you_text'] ?? '')),
            'settings_json' => Helpers::encodeJson($this->normalizeJsonField($payload['settings_json'] ?? ($payload['settings'] ?? []))),
            'updated_by' => $userId,
            'updated_at' => $now,
        ];

        if ($surveyId > 0) {
            $this->ensureSurveyWriteAccess($surveyId, $actor);
            $this->db->update('surveys', $data, 'id = :id', [':id' => $surveyId]);
            return $surveyId;
        }

        $data['created_by'] = $userId;
        $data['created_at'] = $now;
        $surveyId = $this->db->insert('surveys', $data);

        $actor ??= auth()->user();
        if ($actor && (string) ($actor['role'] ?? '') !== 'super_admin') {
            $this->ensureSurveyAssignment($surveyId, (int) $actor['id'], $userId);
        }

        return $surveyId;
    }

    public function deleteSurvey(int $surveyId, ?array $user = null): bool
    {
        $this->ensureSurveyWriteAccess($surveyId, $user);
        return $this->db->delete('surveys', 'id = :id', [':id' => $surveyId]) > 0;
    }

    public function saveSection(int $surveyId, array $payload, ?array $user = null): int
    {
        $this->ensureSurveyWriteAccess($surveyId, $user);
        $sectionId = (int) ($payload['id'] ?? 0);
        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('El título de la sección es obligatorio.');
        }

        $data = [
            'survey_id' => $surveyId,
            'title' => $title,
            'description' => trim((string) ($payload['description'] ?? '')),
            'sort_order' => max(1, (int) ($payload['sort_order'] ?? 1)),
            'settings_json' => Helpers::encodeJson($this->normalizeJsonField($payload['settings_json'] ?? ($payload['settings'] ?? []))),
        ];

        if ($sectionId > 0) {
            $this->db->update('survey_sections', $data, 'id = :id AND survey_id = :survey_id', [
                ':id' => $sectionId,
                ':survey_id' => $surveyId,
            ]);
            return $sectionId;
        }

        return $this->db->insert('survey_sections', $data);
    }

    public function deleteSection(int $sectionId, ?array $user = null): bool
    {
        $surveyId = $this->resolveSurveyIdBySection($sectionId);
        if ($surveyId <= 0) {
            return false;
        }

        $this->ensureSurveyWriteAccess($surveyId, $user);
        return $this->db->delete('survey_sections', 'id = :id', [':id' => $sectionId]) > 0;
    }

    public function saveQuestion(int $surveyId, array $payload, ?array $user = null): int
    {
        $this->ensureSurveyWriteAccess($surveyId, $user);
        $questionId = (int) ($payload['id'] ?? 0);
        $sectionId = (int) ($payload['section_id'] ?? 0);
        $prompt = trim((string) ($payload['prompt'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $questionType = trim((string) ($payload['question_type'] ?? 'single_choice'));

        if ($sectionId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una sección.');
        }

        if ($prompt === '' || $code === '') {
            throw new InvalidArgumentException('El código y el enunciado son obligatorios.');
        }

        $settings = $this->normalizeJsonField($payload['settings_json'] ?? ($payload['settings'] ?? []));
        $matrixConfig = trim((string) ($payload['matrix_config'] ?? ''));
        if ($matrixConfig !== '') {
            $settings['matrix'] = $this->normalizeJsonField($matrixConfig);
        }

        $data = [
            'survey_id' => $surveyId,
            'section_id' => $sectionId,
            'code' => strtoupper($code),
            'prompt' => $prompt,
            'help_text' => trim((string) ($payload['help_text'] ?? '')),
            'question_type' => $questionType,
            'is_required' => !empty($payload['is_required']) ? 1 : 0,
            'placeholder' => trim((string) ($payload['placeholder'] ?? '')),
            'sort_order' => max(1, (int) ($payload['sort_order'] ?? 1)),
            'visibility_rules_json' => Helpers::encodeJson($this->buildVisibilityRules($payload)),
            'validation_rules_json' => Helpers::encodeJson($this->normalizeJsonField($payload['validation_rules_json'] ?? ($payload['validation_rules'] ?? []))),
            'settings_json' => Helpers::encodeJson($settings),
        ];

        $startedTransaction = !$this->db->pdo()->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if ($questionId > 0) {
                $this->db->update('survey_questions', $data, 'id = :id AND survey_id = :survey_id', [
                    ':id' => $questionId,
                    ':survey_id' => $surveyId,
                ]);
            } else {
                $questionId = $this->db->insert('survey_questions', $data);
            }

            $this->db->delete('survey_question_options', 'question_id = :question_id', [':question_id' => $questionId]);

            if (in_array($questionType, ['single_choice', 'multiple_choice', 'rating'], true)) {
                $options = $this->normalizeOptions($payload['options'] ?? []);
                foreach ($options as $index => $option) {
                    $this->db->insert('survey_question_options', [
                        'question_id' => $questionId,
                        'option_code' => $option['code'],
                        'option_label' => $option['label'],
                        'option_value' => $option['value'],
                        'sort_order' => $index + 1,
                        'is_other_option' => !empty($option['is_other']) ? 1 : 0,
                    ]);
                }
            }

            if ($startedTransaction) {
                $this->db->commit();
            }
            return $questionId;
        } catch (Throwable $exception) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteQuestion(int $questionId, ?array $user = null): bool
    {
        $surveyId = $this->resolveSurveyIdByQuestion($questionId);
        if ($surveyId <= 0) {
            return false;
        }

        $this->ensureSurveyWriteAccess($surveyId, $user);
        return $this->db->delete('survey_questions', 'id = :id', [':id' => $questionId]) > 0;
    }

    public function importStructure(int $surveyId, string $format, string $payload, ?array $user = null): array
    {
        $this->ensureSurveyWriteAccess($surveyId, $user);
        $items = $format === 'csv'
            ? $this->parseCsvImport($payload)
            : $this->parseJsonImport($payload);

        $counts = ['sections' => 0, 'questions' => 0, 'options' => 0];

        $this->db->beginTransaction();

        try {
            foreach ($items as $sectionData) {
                $existingSection = $this->db->fetch(
                    'SELECT id FROM survey_sections WHERE survey_id = :survey_id AND title = :title LIMIT 1',
                    [':survey_id' => $surveyId, ':title' => $sectionData['title']]
                );

                if ($existingSection) {
                    $sectionId = (int) $existingSection['id'];
                } else {
                    $sectionId = $this->saveSection($surveyId, $sectionData, $user);
                    $counts['sections']++;
                }

                foreach ($sectionData['questions'] as $questionData) {
                    $questionData['section_id'] = $sectionId;
                    $questionId = $this->saveQuestion($surveyId, $questionData, $user);
                    $counts['questions']++;
                    $counts['options'] += count($this->normalizeOptions($questionData['options'] ?? []));
                }
            }

            $this->db->commit();
            return $counts;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function listResponses(?int $surveyId = null, array $filters = [], ?array $user = null): array
    {
        $where = ['1 = 1'];
        $params = [];
        $access = $this->buildSurveyAccessConstraint('s', $user);

        if ($surveyId) {
            $where[] = 'sr.survey_id = :survey_id';
            $params[':survey_id'] = $surveyId;
        }

        if (!empty($filters['from'])) {
            $where[] = 'sr.submitted_at >= :from';
            $params[':from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $where[] = 'sr.submitted_at <= :to';
            $params[':to'] = $filters['to'] . ' 23:59:59';
        }

        return $this->db->fetchAll(
            "SELECT
                sr.id,
                sr.response_uuid,
                sr.submitted_at,
                s.name AS survey_name,
                s.slug AS survey_slug,
                (
                    SELECT COUNT(*)
                    FROM response_answers ra
                    WHERE ra.response_id = sr.id
                ) AS answer_count
             FROM survey_responses sr
             INNER JOIN surveys s ON s.id = sr.survey_id
             {$access['joins']}
             WHERE " . implode(' AND ', $where) . $access['where'] . "
             ORDER BY sr.submitted_at DESC
             LIMIT 500",
            $params + $access['params']
        );
    }

    public function getResponse(int $responseId, ?array $user = null): ?array
    {
        $access = $this->buildSurveyAccessConstraint('s', $user);
        $response = $this->db->fetch(
            "SELECT sr.*, s.name AS survey_name, s.slug AS survey_slug
             FROM survey_responses sr
             INNER JOIN surveys s ON s.id = sr.survey_id
             {$access['joins']}
             WHERE sr.id = :id {$access['where']}
             LIMIT 1",
            [':id' => $responseId] + $access['params']
        );

        if (!$response) {
            return null;
        }

        $answers = $this->db->fetchAll(
            'SELECT * FROM response_answers WHERE response_id = :response_id ORDER BY id ASC',
            [':response_id' => $responseId]
        );

        $optionsByAnswer = [];
        $answerIds = array_map(static fn(array $answer): int => (int) $answer['id'], $answers);
        if ($answerIds !== []) {
            $placeholder = implode(',', array_fill(0, count($answerIds), '?'));
            $optionRows = $this->db->fetchAll(
                "SELECT response_answer_id, option_code, option_label
                 FROM response_answer_options
                 WHERE response_answer_id IN ($placeholder)
                 ORDER BY response_answer_id ASC, id ASC",
                $answerIds
            );

            foreach ($optionRows as $optionRow) {
                $optionsByAnswer[(int) $optionRow['response_answer_id']][] = [
                    'option_code' => $optionRow['option_code'],
                    'option_label' => $optionRow['option_label'],
                ];
            }
        }

        foreach ($answers as &$answer) {
            $answer['answer_json'] = Helpers::decodeJson($answer['answer_json'], null);
            $answer['options'] = $optionsByAnswer[(int) $answer['id']] ?? [];
        }
        unset($answer);

        $response['metadata'] = Helpers::decodeJson($response['metadata_json'], []);
        $sessionToken = '';
        if ($this->tableHasColumn('survey_responses', 'session_token')) {
            $sessionToken = (string) ($response['session_token'] ?? '');
        }
        if ($sessionToken === '') {
            $sessionToken = (string) ($response['metadata']['client']['session_token'] ?? '');
        }

        $response['capture_summary'] = $this->buildCaptureSummary($response);
        $response['access_logs'] = $this->fetchAccessLogs((int) $response['survey_id'], $responseId, $sessionToken);
        $response['answers'] = $answers;

        return $response;
    }

    public function trackSurveyAccess(string $slug, array $payload = []): array
    {
        $survey = $this->getSurveyRecordBySlug($slug);
        if (!$survey || !(bool) ($survey['is_public'] ?? false)) {
            return ['success' => true];
        }

        $this->recordSurveyAccess($survey, 'view', $payload);
        return ['success' => true];
    }

    public function analytics(int $surveyId, array $filters = [], ?array $user = null): array
    {
        $survey = $this->getSurvey($surveyId, $user);
        if (!$survey) {
            throw new InvalidArgumentException('Encuesta no encontrada.');
        }

        $reportScope = $this->normalizeAnalyticsReportScope($filters['report_scope'] ?? null);
        $analyticsQuestions = $this->filterAnalyticsQuestionsByScope($survey['questions_flat'], $reportScope);
        $analyticsQuestionCodes = array_fill_keys(array_map(static fn(array $question): string => (string) $question['code'], $analyticsQuestions), true);
        $analyticsSectionIds = [];
        foreach ($analyticsQuestions as $question) {
            $analyticsSectionIds[(int) $question['section_id']] = true;
        }
        $analyticsSections = array_values(array_filter(
            $survey['sections'],
            static fn(array $section): bool => isset($analyticsSectionIds[(int) $section['id']])
        ));

        $sectionTitles = [];
        foreach ($analyticsSections as $section) {
            $sectionTitles[(int) $section['id']] = $section['title'];
        }

        $questionMeta = [];
        foreach ($analyticsQuestions as $question) {
            $optionLabels = [];
            foreach ((array) ($question['options'] ?? []) as $option) {
                $optionCode = trim((string) ($option['code'] ?? ''));
                if ($optionCode === '') {
                    continue;
                }
                $optionLabels[$optionCode] = trim((string) ($option['label'] ?? $optionCode));
            }

            $questionMeta[$question['code']] = [
                'id' => (int) $question['id'],
                'code' => $question['code'],
                'title' => $this->sanitizeAnalyticsQuestionTitle((string) ($question['prompt'] ?? '')),
                'type' => $question['question_type'],
                'section_id' => (int) $question['section_id'],
                'section_title' => $sectionTitles[(int) $question['section_id']] ?? 'Sin sección',
                'settings' => $question['settings'] ?? [],
                'options' => $question['options'] ?? [],
                'option_labels' => $optionLabels,
            ];
        }

        $locationQuestion = $this->resolveAnalyticsLocationQuestion($survey);
        $selectedLocationValue = $this->normalizeAnalyticsFilterValue($filters['location'] ?? null);

        $baseWhere = ['sr.survey_id = :survey_id'];
        $baseParams = [':survey_id' => $surveyId];

        if (!empty($filters['from'])) {
            $baseWhere[] = 'sr.submitted_at >= :from';
            $baseParams[':from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $baseWhere[] = 'sr.submitted_at <= :to';
            $baseParams[':to'] = $filters['to'] . ' 23:59:59';
        }

        $baseResponses = $this->db->fetchAll(
            'SELECT sr.id, sr.submitted_at FROM survey_responses sr WHERE ' . implode(' AND ', $baseWhere) . ' ORDER BY sr.submitted_at ASC',
            $baseParams
        );
        $baseTotalResponses = count($baseResponses);

        $locationOptions = [];
        if ($this->analyticsQuestionSupportsLocationFilter($locationQuestion)) {
            $locationOptions = $this->fetchAnalyticsLocationOptions($locationQuestion, $baseWhere, $baseParams);
            if ($selectedLocationValue !== null && !in_array($selectedLocationValue, array_column($locationOptions, 'value'), true)) {
                $selectedLocationValue = null;
            }
        } else {
            $selectedLocationValue = null;
        }

        $locationSelectedLabel = 'Todos';
        foreach ($locationOptions as $option) {
            if ($option['value'] === $selectedLocationValue) {
                $locationSelectedLabel = $option['label'];
                break;
            }
        }

        $locationFilter = [
            'enabled' => $this->analyticsQuestionSupportsLocationFilter($locationQuestion),
            'question_code' => $locationQuestion['code'] ?? null,
            'question_title' => $locationQuestion['prompt'] ?? null,
            'question_type' => $locationQuestion['question_type'] ?? null,
            'selected_value' => $selectedLocationValue ?? 'all',
            'selected_label' => $locationSelectedLabel,
            'all_label' => 'Todos',
            'active_option_count' => count(array_filter($locationOptions, static fn(array $option): bool => (int) ($option['count'] ?? 0) > 0)),
            'options' => array_merge([[
                'value' => 'all',
                'label' => 'Todos',
                'count' => $baseTotalResponses,
            ]], $locationOptions),
        ];

        $locationDistribution = $this->buildAnalyticsLocationDistribution($locationOptions, $baseTotalResponses);

        $where = $baseWhere;
        $params = $baseParams;
        if ($selectedLocationValue !== null && $locationQuestion) {
            $this->appendAnalyticsLocationConstraint($where, $params, $locationQuestion, $selectedLocationValue);
        }

        $responses = $selectedLocationValue === null
            ? $baseResponses
            : $this->db->fetchAll(
                'SELECT sr.id, sr.submitted_at FROM survey_responses sr WHERE ' . implode(' AND ', $where) . ' ORDER BY sr.submitted_at ASC',
                $params
            );

        $responseIds = array_map(static fn(array $row): int => (int) $row['id'], $responses);
        $totalResponses = count($responses);

        $timeSeries = $this->expandTimeSeries($this->db->fetchAll(
            "SELECT DATE(sr.submitted_at) AS label, COUNT(*) AS value
             FROM survey_responses sr
             WHERE " . implode(' AND ', $where) . "
             GROUP BY DATE(sr.submitted_at)
             ORDER BY DATE(sr.submitted_at)",
            $params
        ));

        $questionStats = [];
        $answerCountMap = [];

        if ($responseIds !== []) {
            $placeholder = implode(',', array_fill(0, count($responseIds), '?'));

            $optionRows = $this->db->fetchAll(
                "SELECT
                    ra.question_code,
                    ra.question_prompt,
                    rao.option_code,
                    MIN(rao.option_label) AS option_label,
                    COUNT(*) AS total
                 FROM response_answer_options rao
                 INNER JOIN response_answers ra ON ra.id = rao.response_answer_id
                 WHERE ra.response_id IN ($placeholder)
                 GROUP BY ra.question_code, ra.question_prompt, rao.option_code
                 ORDER BY ra.question_code ASC, total DESC",
                $responseIds
            );

            $answerCountRows = $this->db->fetchAll(
                "SELECT question_code, COUNT(*) AS total
                 FROM response_answers
                 WHERE response_id IN ($placeholder)
                 GROUP BY question_code",
                $responseIds
            );

            $textRows = $this->db->fetchAll(
                "SELECT question_id, question_code, answer_text
                 FROM response_answers
                 WHERE response_id IN ($placeholder) AND answer_type IN ('text', 'textarea')",
                $responseIds
            );

            $matrixRows = $this->db->fetchAll(
                "SELECT question_id, question_code, answer_json
                 FROM response_answers
                 WHERE response_id IN ($placeholder) AND answer_type = 'matrix'",
                $responseIds
            );

            foreach ($answerCountRows as $row) {
                $code = (string) ($row['question_code'] ?? '');
                if (!isset($analyticsQuestionCodes[$code])) {
                    continue;
                }

                $answerCountMap[$code] = (int) $row['total'];
            }

            foreach ($optionRows as $row) {
                $code = $row['question_code'];
                if (!isset($analyticsQuestionCodes[$code])) {
                    continue;
                }

                $meta = $questionMeta[$code] ?? [
                    'title' => $this->sanitizeAnalyticsQuestionTitle((string) ($row['question_prompt'] ?? '')),
                    'section_title' => 'Sin sección',
                ];
                $questionStats[$code] ??= [
                    'code' => $code,
                    'type' => 'choice',
                    'title' => $meta['title'],
                    'section_title' => $meta['section_title'],
                    'responses' => $answerCountMap[$code] ?? 0,
                    'coverage_percentage' => $totalResponses > 0 ? round((($answerCountMap[$code] ?? 0) / $totalResponses) * 100, 1) : 0.0,
                    'options_map' => [],
                ];
                $count = (int) $row['total'];
                $responsesCount = max(1, $questionStats[$code]['responses']);
                $resolvedLabel = $this->resolveAnalyticsOptionLabel(
                    $meta,
                    (string) ($row['option_code'] ?? ''),
                    (string) ($row['option_label'] ?? '')
                );
                $bucketKey = $this->analyticsOptionBucketKey($resolvedLabel, (string) ($row['option_code'] ?? ''));
                $questionStats[$code]['options_map'][$bucketKey] ??= [
                    'code' => (string) ($row['option_code'] ?? ''),
                    'label' => $resolvedLabel,
                    'count' => 0,
                    'percentage' => 0.0,
                ];
                $questionStats[$code]['options_map'][$bucketKey]['count'] += $count;
            }

            foreach ($questionStats as $code => &$questionStat) {
                if (($questionStat['type'] ?? '') !== 'choice') {
                    continue;
                }

                $options = array_values($questionStat['options_map'] ?? []);
                foreach ($options as &$option) {
                    $option['percentage'] = round(($option['count'] / max(1, $questionStat['responses'])) * 100, 1);
                }
                unset($option);

                usort($options, static function (array $left, array $right): int {
                    return ((int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0))
                        ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
                });

                $questionStat['options'] = $options;
                unset($questionStat['options_map']);
            }
            unset($questionStat);

            $textByQuestion = [];
            foreach ($textRows as $row) {
                $textByQuestion[$row['question_code']][] = (string) $row['answer_text'];
            }

            foreach ($textByQuestion as $code => $texts) {
                if (!isset($analyticsQuestionCodes[$code])) {
                    continue;
                }

                $meta = $questionMeta[$code] ?? [
                    'title' => $this->findQuestionTitle($survey, $code),
                    'section_title' => 'Sin sección',
                ];
                $questionStats[$code] = [
                    'code' => $code,
                    'type' => 'text',
                    'title' => $meta['title'],
                    'section_title' => $meta['section_title'],
                    'responses' => count($texts),
                    'coverage_percentage' => $totalResponses > 0 ? round((count($texts) / $totalResponses) * 100, 1) : 0.0,
                    'keywords' => $this->topKeywords($texts),
                    'samples' => array_slice(array_values(array_filter($texts)), 0, 5),
                ];
            }

            if ($matrixRows !== []) {
                $matrixStats = [];

                foreach ($matrixRows as $row) {
                    $code = $row['question_code'];
                    $payload = Helpers::decodeJson($row['answer_json'], []);
                    foreach ($payload as $rowCode => $dimensions) {
                        foreach ((array) $dimensions as $dimensionCode => $valueCode) {
                            if ($valueCode === null || $valueCode === '') {
                                continue;
                            }
                            $matrixStats[$code][$rowCode][$dimensionCode][$valueCode] = ($matrixStats[$code][$rowCode][$dimensionCode][$valueCode] ?? 0) + 1;
                        }
                    }
                }

                foreach ($matrixStats as $code => $matrixData) {
                    if (!isset($analyticsQuestionCodes[$code])) {
                        continue;
                    }

                    $meta = $questionMeta[$code] ?? [
                        'title' => $this->findQuestionTitle($survey, $code),
                        'section_title' => 'Sin sección',
                        'settings' => [],
                    ];
                    $questionStats[$code] = [
                        'code' => $code,
                        'type' => 'matrix',
                        'title' => $meta['title'],
                        'section_title' => $meta['section_title'],
                        'responses' => $answerCountMap[$code] ?? 0,
                        'coverage_percentage' => $totalResponses > 0 ? round((($answerCountMap[$code] ?? 0) / $totalResponses) * 100, 1) : 0.0,
                        'matrix_meta' => $this->buildMatrixMeta($meta, $matrixData),
                        'matrix' => $matrixData,
                    ];
                }
            }
        }

        $coverage = [];
        $sectionStats = [];
        foreach ($analyticsSections as $section) {
            $sectionStats[(int) $section['id']] = [
                'id' => (int) $section['id'],
                'title' => $section['title'],
                'question_count' => 0,
                'response_sum' => 0,
                'coverage_sum' => 0.0,
                'average_coverage' => 0.0,
            ];
        }

        foreach ($analyticsQuestions as $question) {
            $code = $question['code'];
            $meta = $questionMeta[$code];
            $responsesCount = $answerCountMap[$code] ?? 0;
            $coveragePercentage = $totalResponses > 0 ? round(($responsesCount / $totalResponses) * 100, 1) : 0.0;

            $coverage[] = [
                'code' => $code,
                'title' => $meta['title'],
                'section_title' => $meta['section_title'],
                'type' => $meta['type'],
                'responses' => $responsesCount,
                'coverage_percentage' => $coveragePercentage,
            ];

            if (isset($sectionStats[$meta['section_id']])) {
                $sectionStats[$meta['section_id']]['question_count']++;
                $sectionStats[$meta['section_id']]['response_sum'] += $responsesCount;
                $sectionStats[$meta['section_id']]['coverage_sum'] += $coveragePercentage;
            }
        }

        foreach ($sectionStats as &$sectionStat) {
            $sectionStat['average_coverage'] = $sectionStat['question_count'] > 0
                ? round($sectionStat['coverage_sum'] / $sectionStat['question_count'], 1)
                : 0.0;
            unset($sectionStat['coverage_sum']);
        }
        unset($sectionStat);

        usort($coverage, static function (array $left, array $right): int {
            return ($right['coverage_percentage'] <=> $left['coverage_percentage'])
                ?: ($right['responses'] <=> $left['responses'])
                ?: strcmp($left['code'], $right['code']);
        });

        $orderedQuestionStats = [];
        foreach ($analyticsQuestions as $question) {
            $code = $question['code'];
            if (isset($questionStats[$code])) {
                $orderedQuestionStats[$code] = $questionStats[$code];
            }
        }

        $activeDays = count($timeSeries);
        $firstSubmittedAt = $responses[0]['submitted_at'] ?? null;
        $lastSubmittedAt = $responses !== [] ? $responses[count($responses) - 1]['submitted_at'] : null;
        $averagePerDay = $activeDays > 0 ? round($totalResponses / $activeDays, 1) : 0.0;

        $highlights = $this->buildAnalyticsHighlights(
            $totalResponses,
            $activeDays,
            $averagePerDay,
            $firstSubmittedAt,
            $lastSubmittedAt,
            $coverage,
            array_values($sectionStats),
            $orderedQuestionStats
        );

        return [
            'survey' => [
                'id' => $survey['id'],
                'name' => $survey['name'],
                'status' => $survey['status'],
                'status_label' => Helpers::statusLabel($survey['status']),
                'start_at' => $survey['start_at'],
                'end_at' => $survey['end_at'],
                'window_status' => $survey['window_status'],
            ],
            'summary' => [
                'responses' => $totalResponses,
                'questions' => count($analyticsQuestions),
                'sections' => count($analyticsSections),
                'active_days' => $activeDays,
                'average_per_day' => $averagePerDay,
                'first_submission_at' => $firstSubmittedAt,
                'last_submission_at' => $lastSubmittedAt,
                'location_label' => $locationFilter['selected_label'],
                'active_locations' => $locationFilter['active_option_count'],
                'report_scope' => $reportScope,
                'report_scope_label' => $this->analyticsReportScopeLabel($reportScope),
                'date_range' => [
                    'from' => $filters['from'] ?? null,
                    'to' => $filters['to'] ?? null,
                ],
            ],
            'location_filter' => $locationFilter,
            'location_distribution' => $locationDistribution,
            'time_series' => $timeSeries,
            'report_scope' => $reportScope,
            'report_scope_label' => $this->analyticsReportScopeLabel($reportScope),
            'coverage' => $coverage,
            'section_stats' => array_values($sectionStats),
            'question_stats' => $orderedQuestionStats,
            'highlights' => $highlights,
            'count_matrix' => $this->buildAnalyticsCountMatrix(
                $locationOptions,
                $locationFilter,
                $baseTotalResponses,
                $totalResponses,
                $coverage,
                $orderedQuestionStats
            ),
        ];
    }

    private function normalizeAnalyticsReportScope(mixed $value): string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        return in_array($normalized, ['special', 'secondary', 'separate', 'apartado'], true) ? 'special' : 'primary';
    }

    private function analyticsReportScopeLabel(string $scope): string
    {
        return $scope === 'special' ? 'Reporte aparte' : 'Dashboard principal';
    }

    private function filterAnalyticsQuestionsByScope(array $questions, string $scope): array
    {
        return array_values(array_filter($questions, function (array $question) use ($scope): bool {
            $isSecondary = $this->questionBelongsToSecondaryReport($question);
            return $scope === 'special' ? $isSecondary : !$isSecondary;
        }));
    }

    private function questionBelongsToSecondaryReport(array $question): bool
    {
        $settings = is_array($question['settings'] ?? null) ? $question['settings'] : [];
        $reportScope = strtolower(trim((string) ($settings['report_scope'] ?? '')));
        if (in_array($reportScope, ['special', 'secondary', 'separate', 'apartado'], true)) {
            return true;
        }

        $prompt = (string) ($question['prompt'] ?? $question['title'] ?? '');
        return stripos($prompt, self::SECONDARY_REPORT_TAG) !== false;
    }

    private function sanitizeAnalyticsQuestionTitle(string $title): string
    {
        $markerPosition = stripos($title, self::SECONDARY_REPORT_TAG);
        if ($markerPosition === false) {
            return trim($title);
        }

        return trim(preg_replace('/\s+/', ' ', substr($title, 0, $markerPosition)) ?: '');
    }

    private function resolveAnalyticsLocationQuestion(array $survey): ?array
    {
        $firstSection = $survey['sections'][0] ?? null;
        if (is_array($firstSection) && !empty($firstSection['questions'][0]) && is_array($firstSection['questions'][0])) {
            return $firstSection['questions'][0];
        }

        $fallback = $survey['questions_flat'][0] ?? null;
        return is_array($fallback) ? $fallback : null;
    }

    private function normalizeAnalyticsFilterValue(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        return in_array(strtolower($normalized), ['all', 'todos', '0'], true) ? null : $normalized;
    }

    private function analyticsQuestionSupportsLocationFilter(?array $question): bool
    {
        if (!$question) {
            return false;
        }

        return in_array((string) ($question['question_type'] ?? ''), ['single_choice', 'multiple_choice', 'rating', 'text', 'textarea'], true);
    }

    private function fetchAnalyticsLocationOptions(array $question, array $where, array $params): array
    {
        $type = (string) ($question['question_type'] ?? '');
        $questionCode = trim((string) ($question['code'] ?? ''));
        if ($questionCode === '') {
            return [];
        }

        if (in_array($type, ['single_choice', 'multiple_choice', 'rating'], true)) {
            $rows = $this->db->fetchAll(
                "SELECT
                    rao.option_code AS value,
                    MIN(rao.option_label) AS label,
                    COUNT(DISTINCT ra.response_id) AS total,
                    MIN(sr.submitted_at) AS first_submitted_at,
                    MAX(sr.submitted_at) AS last_submitted_at
                 FROM survey_responses sr
                 INNER JOIN response_answers ra ON ra.response_id = sr.id AND ra.question_code = :location_question_code
                 INNER JOIN response_answer_options rao ON rao.response_answer_id = ra.id
                 WHERE " . implode(' AND ', $where) . '
                 GROUP BY rao.option_code',
                [':location_question_code' => $questionCode] + $params
            );

            $countsByValue = [];
            foreach ($rows as $row) {
                $value = (string) ($row['value'] ?? '');
                if ($value === '') {
                    continue;
                }

                $countsByValue[$value] = [
                    'value' => $value,
                    'label' => $this->resolveAnalyticsOptionLabel($question, $value, (string) ($row['label'] ?? $value)),
                    'count' => (int) ($row['total'] ?? 0),
                    'first_submission_at' => $row['first_submitted_at'] ?? null,
                    'last_submission_at' => $row['last_submitted_at'] ?? null,
                ];
            }

            $options = [];
            foreach ((array) ($question['options'] ?? []) as $option) {
                $value = trim((string) ($option['code'] ?? ''));
                if ($value === '') {
                    continue;
                }

                $options[] = [
                    'value' => $value,
                    'label' => trim((string) ($option['label'] ?? $value)),
                    'count' => (int) ($countsByValue[$value]['count'] ?? 0),
                    'first_submission_at' => $countsByValue[$value]['first_submission_at'] ?? null,
                    'last_submission_at' => $countsByValue[$value]['last_submission_at'] ?? null,
                ];
                unset($countsByValue[$value]);
            }

            foreach ($countsByValue as $option) {
                $options[] = $option;
            }

            return $options;
        }

        if (in_array($type, ['text', 'textarea'], true)) {
            $rows = $this->db->fetchAll(
                "SELECT
                    TRIM(ra.answer_text) AS value,
                    TRIM(ra.answer_text) AS label,
                    COUNT(*) AS total,
                    MIN(sr.submitted_at) AS first_submitted_at,
                    MAX(sr.submitted_at) AS last_submitted_at
                 FROM survey_responses sr
                 INNER JOIN response_answers ra ON ra.response_id = sr.id AND ra.question_code = :location_question_code
                 WHERE " . implode(' AND ', $where) . " AND TRIM(COALESCE(ra.answer_text, '')) <> ''
                 GROUP BY TRIM(ra.answer_text)
                 ORDER BY TRIM(ra.answer_text) ASC",
                [':location_question_code' => $questionCode] + $params
            );

            return array_map(static fn(array $row): array => [
                'value' => (string) ($row['value'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'count' => (int) ($row['total'] ?? 0),
                'first_submission_at' => $row['first_submitted_at'] ?? null,
                'last_submission_at' => $row['last_submitted_at'] ?? null,
            ], array_values(array_filter($rows, static fn(array $row): bool => trim((string) ($row['value'] ?? '')) !== '')));
        }

        return [];
    }

    private function buildAnalyticsLocationDistribution(array $options, int $totalResponses): array
    {
        $activeOptions = array_values(array_filter($options, static fn(array $option): bool => (int) ($option['count'] ?? 0) > 0));

        usort($activeOptions, static function (array $left, array $right): int {
            return ((int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0))
                ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return array_map(static function (array $option) use ($totalResponses): array {
            $count = (int) ($option['count'] ?? 0);
            return [
                'value' => (string) ($option['value'] ?? ''),
                'label' => (string) ($option['label'] ?? ''),
                'count' => $count,
                'percentage' => $totalResponses > 0 ? round(($count / $totalResponses) * 100, 1) : 0.0,
            ];
        }, $activeOptions);
    }

    private function appendAnalyticsLocationConstraint(array &$where, array &$params, array $question, string $selectedValue): void
    {
        $questionCode = trim((string) ($question['code'] ?? ''));
        if ($questionCode === '' || $selectedValue === '') {
            return;
        }

        $type = (string) ($question['question_type'] ?? '');
        $params[':location_filter_question_code'] = $questionCode;
        $params[':location_filter_value'] = $selectedValue;

        if (in_array($type, ['single_choice', 'multiple_choice', 'rating'], true)) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM response_answers ra_filter
                INNER JOIN response_answer_options rao_filter ON rao_filter.response_answer_id = ra_filter.id
                WHERE ra_filter.response_id = sr.id
                  AND ra_filter.question_code = :location_filter_question_code
                  AND rao_filter.option_code = :location_filter_value
            )';
            return;
        }

        if (in_array($type, ['text', 'textarea'], true)) {
            $where[] = "EXISTS (
                SELECT 1
                FROM response_answers ra_filter
                WHERE ra_filter.response_id = sr.id
                  AND ra_filter.question_code = :location_filter_question_code
                  AND TRIM(COALESCE(ra_filter.answer_text, '')) = :location_filter_value
            )";
        }
    }

    private function buildAnalyticsCountMatrix(
        array $locationOptions,
        array $locationFilter,
        int $baseTotalResponses,
        int $filteredResponses,
        array $coverage,
        array $questionStats
    ): array {
        $selectedValue = (string) ($locationFilter['selected_value'] ?? 'all');
        $territorialRows = [];

        foreach ($locationOptions as $option) {
            $count = (int) ($option['count'] ?? 0);
            $value = (string) ($option['value'] ?? '');
            $territorialRows[] = [
                'value' => $value,
                'label' => (string) ($option['label'] ?? $value),
                'count' => $count,
                'percentage' => $baseTotalResponses > 0 ? round(($count / $baseTotalResponses) * 100, 1) : 0.0,
                'first_submission_at' => $option['first_submission_at'] ?? null,
                'last_submission_at' => $option['last_submission_at'] ?? null,
                'is_selected' => $selectedValue !== 'all' && $value !== '' && $value === $selectedValue,
                'has_activity' => $count > 0,
            ];
        }

        usort($territorialRows, static function (array $left, array $right): int {
            return ((int) ($right['is_selected'] ?? false) <=> (int) ($left['is_selected'] ?? false))
                ?: ((int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0))
                ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        $visibleTerritorialRows = $selectedValue === 'all'
            ? $territorialRows
            : array_values(array_filter($territorialRows, static fn(array $row): bool => (bool) ($row['is_selected'] ?? false)));

        $questionRows = [];
        $answeredQuestions = 0;

        foreach (array_values($coverage) as $index => $item) {
            $code = (string) ($item['code'] ?? '');
            $responses = (int) ($item['responses'] ?? 0);
            $coveragePercentage = (float) ($item['coverage_percentage'] ?? 0);
            $stat = $questionStats[$code] ?? null;

            if ($responses > 0) {
                $answeredQuestions++;
            }

            $summary = 'Sin respuestas dentro del filtro actual.';
            $highlightLabel = null;
            $highlightCount = null;
            $highlightPercentage = null;
            $optionBreakdown = [];
            $otherOptions = [];
            $otherOptionsSummary = 'Sin lectura adicional.';

            if (is_array($stat)) {
                if (($stat['type'] ?? '') === 'choice') {
                    $optionBreakdown = array_values(array_map(static fn(array $option): array => [
                        'label' => (string) ($option['label'] ?? ''),
                        'count' => (int) ($option['count'] ?? 0),
                        'percentage' => (float) ($option['percentage'] ?? 0),
                    ], array_filter(
                        (array) ($stat['options'] ?? []),
                        static fn(array $option): bool => trim((string) ($option['label'] ?? '')) !== ''
                    )));
                    $otherOptions = array_slice($optionBreakdown, 1);
                    $otherOptionsSummary = $this->summarizeAnalyticsOptionRows($otherOptions);

                    $topOption = $stat['options'][0] ?? null;
                    if (is_array($topOption)) {
                        $highlightLabel = (string) ($topOption['label'] ?? '');
                        $highlightCount = (int) ($topOption['count'] ?? 0);
                        $highlightPercentage = (float) ($topOption['percentage'] ?? 0);
                        $summary = sprintf(
                            '%s lidera con %s (%d).',
                            trim($highlightLabel) !== '' ? $highlightLabel : 'La opción principal',
                            number_format($highlightPercentage, 1) . '%',
                            $highlightCount
                        );
                    } elseif ($responses > 0) {
                        $summary = 'Con respuestas registradas, pero sin opciones agregadas suficientes.';
                    }
                } elseif (($stat['type'] ?? '') === 'matrix') {
                    $rowCount = count((array) ($stat['matrix_meta']['rows'] ?? []));
                    $dimensionCount = count((array) ($stat['matrix_meta']['dimensions'] ?? []));
                    $summary = $responses > 0
                        ? sprintf('%d fila(s) y %d dimensión(es) con actividad.', $rowCount, $dimensionCount)
                        : $summary;
                } elseif (($stat['type'] ?? '') === 'text') {
                    $keywordCount = count((array) ($stat['keywords'] ?? []));
                    $sampleCount = count((array) ($stat['samples'] ?? []));
                    $summary = $responses > 0
                        ? ($keywordCount > 0
                            ? sprintf('%d palabra(s) clave y %d muestra(s) destacadas.', $keywordCount, $sampleCount)
                            : sprintf('%d respuesta(s) abiertas registradas.', $responses))
                        : $summary;
                    $firstKeyword = $stat['keywords'][0] ?? null;
                    if (is_array($firstKeyword)) {
                        $highlightLabel = (string) ($firstKeyword['word'] ?? '');
                        $highlightCount = (int) ($firstKeyword['count'] ?? 0);
                    }
                }
            }

            $questionRows[] = [
                'position' => $index + 1,
                'code' => $code,
                'title' => (string) ($item['title'] ?? ''),
                'section_title' => (string) ($item['section_title'] ?? 'Sin sección'),
                'type' => (string) ($item['type'] ?? ''),
                'type_label' => $this->analyticsQuestionTypeLabel((string) ($item['type'] ?? '')),
                'responses' => $responses,
                'coverage_percentage' => $coveragePercentage,
                'summary' => $summary,
                'highlight_label' => $highlightLabel,
                'highlight_count' => $highlightCount,
                'highlight_percentage' => $highlightPercentage,
                'option_breakdown' => $optionBreakdown,
                'other_options' => $otherOptions,
                'other_options_summary' => $otherOptionsSummary,
                'has_activity' => $responses > 0,
            ];
        }

        return [
            'territorial' => [
                'enabled' => (bool) ($locationFilter['enabled'] ?? false),
                'selected_value' => $selectedValue,
                'selected_label' => (string) ($locationFilter['selected_label'] ?? 'Todos'),
                'selection_mode' => $selectedValue === 'all' ? 'all' : 'single',
                'total_responses' => $baseTotalResponses,
                'filtered_responses' => $filteredResponses,
                'rows' => $visibleTerritorialRows,
                'all_rows' => $territorialRows,
            ],
            'questions' => [
                'filtered_responses' => $filteredResponses,
                'total_questions' => count($questionRows),
                'answered_questions' => $answeredQuestions,
                'rows' => $questionRows,
            ],
        ];
    }

    private function analyticsQuestionTypeLabel(string $type): string
    {
        return match ($type) {
            'single_choice' => 'Selección única',
            'multiple_choice' => 'Selección múltiple',
            'rating' => 'Escala',
            'text', 'textarea' => 'Texto abierto',
            'matrix' => 'Matriz',
            default => ucfirst(str_replace('_', ' ', trim($type))),
        };
    }

    private function resolveAnalyticsOptionLabel(array $questionMeta, string $optionCode, string $fallbackLabel = ''): string
    {
        $normalizedCode = trim($optionCode);
        if ($normalizedCode !== '') {
            $optionLabels = is_array($questionMeta['option_labels'] ?? null) ? $questionMeta['option_labels'] : [];
            if (isset($optionLabels[$normalizedCode])) {
                return trim((string) $optionLabels[$normalizedCode]);
            }

            foreach ((array) ($questionMeta['options'] ?? []) as $option) {
                if (trim((string) ($option['code'] ?? '')) === $normalizedCode) {
                    return trim((string) ($option['label'] ?? $normalizedCode));
                }
            }
        }

        return trim($fallbackLabel) !== '' ? trim($fallbackLabel) : $normalizedCode;
    }

    private function analyticsOptionBucketKey(string $label, string $optionCode): string
    {
        $normalizedLabel = $this->normalizeAnalyticsOptionBucketLabel($label);
        if ($normalizedLabel !== '') {
            return 'label:' . $normalizedLabel;
        }

        $normalizedCode = trim($optionCode);
        return $normalizedCode !== '' ? 'code:' . $normalizedCode : 'label:sin-etiqueta';
    }

    private function normalizeAnalyticsOptionBucketLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/^[A-ZÁÉÍÓÚÑ]\.\s*/u', '', $normalized) ?: $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: $normalized;
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = strtr($normalized, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);

        return trim($normalized);
    }

    private function summarizeAnalyticsOptionRows(array $options): string
    {
        $normalized = array_values(array_filter(array_map(static function (array $option): array {
            return [
                'label' => trim((string) ($option['label'] ?? '')),
                'count' => (int) ($option['count'] ?? 0),
                'percentage' => (float) ($option['percentage'] ?? 0),
            ];
        }, $options), static fn(array $option): bool => $option['label'] !== ''));

        if ($normalized === []) {
            return 'Sin lectura adicional.';
        }

        return implode(' | ', array_map(
            static fn(array $option): string => sprintf(
                '%s · %s (%d)',
                $option['label'],
                number_format($option['percentage'], 1) . '%',
                $option['count']
            ),
            $normalized
        ));
    }

    public function downloadAnalyticsCountMatrixXlsx(array $analytics): never
    {
        $summary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
        $survey = is_array($analytics['survey'] ?? null) ? $analytics['survey'] : [];
        $locationFilter = is_array($analytics['location_filter'] ?? null) ? $analytics['location_filter'] : [];
        $countMatrix = is_array($analytics['count_matrix'] ?? null) ? $analytics['count_matrix'] : [];
        $territorial = is_array($countMatrix['territorial'] ?? null) ? $countMatrix['territorial'] : [];
        $questions = is_array($countMatrix['questions'] ?? null) ? $countMatrix['questions'] : [];

        $summaryRows = [
            ['Campo', 'Valor'],
            ['Encuesta', (string) ($survey['name'] ?? 'Encuesta')],
            ['Vista', (string) ($analytics['report_scope_label'] ?? $summary['report_scope_label'] ?? 'Dashboard principal')],
            ['Ubicación filtrada', (string) ($locationFilter['selected_label'] ?? 'Todos')],
            ['Desde', (string) (($summary['date_range']['from'] ?? null) ?: 'Sin límite')],
            ['Hasta', (string) (($summary['date_range']['to'] ?? null) ?: 'Sin límite')],
            ['Respuestas analizadas', (int) ($summary['responses'] ?? 0)],
            ['Preguntas parametrizadas', (int) ($summary['questions'] ?? 0)],
            ['Secciones', (int) ($summary['sections'] ?? 0)],
            ['Días con actividad', (int) ($summary['active_days'] ?? 0)],
            ['Promedio diario', (float) ($summary['average_per_day'] ?? 0)],
            ['Primera captura', (string) (($summary['first_submission_at'] ?? null) ?: 'Sin registro')],
            ['Última captura', (string) (($summary['last_submission_at'] ?? null) ?: 'Sin registro')],
            ['Generado', date('Y-m-d H:i:s')],
        ];

        $territorialRows = [[
            'Cantón / ciudad',
            'Conteo',
            '% rango',
            'Primera captura',
            'Última captura',
            'Estado',
        ]];

        foreach ((array) ($territorial['rows'] ?? []) as $row) {
            $territorialRows[] = [
                (string) ($row['label'] ?? ''),
                (int) ($row['count'] ?? 0),
                (float) ($row['percentage'] ?? 0),
                (string) (($row['first_submission_at'] ?? null) ?: 'Sin registro'),
                (string) (($row['last_submission_at'] ?? null) ?: 'Sin registro'),
                (bool) ($row['is_selected'] ?? false)
                    ? 'En foco'
                    : ((int) ($row['count'] ?? 0) > 0 ? 'Con actividad' : 'Sin actividad'),
            ];
        }

        $questionRows = [[
            '#',
            'Pregunta',
            'Sección',
            'Tipo',
            'Resp.',
            'Cobertura',
            'Lectura rápida',
            'Otras respuestas',
        ]];

        foreach ((array) ($questions['rows'] ?? []) as $row) {
            $questionRows[] = [
                (int) ($row['position'] ?? 0),
                trim((string) (($row['code'] ?? '') . '. ' . ($row['title'] ?? '')), '. '),
                (string) ($row['section_title'] ?? 'Sin sección'),
                (string) ($row['type_label'] ?? $row['type'] ?? ''),
                (int) ($row['responses'] ?? 0),
                (float) ($row['coverage_percentage'] ?? 0),
                $this->buildAnalyticsQuickReadingLabel(is_array($row) ? $row : []),
                (string) ($row['other_options_summary'] ?? 'Sin lectura adicional.'),
            ];
        }

        $filename = Helpers::slugify((string) ($survey['name'] ?? 'reporte')) . '-matriz-conteo';
        $scope = (string) ($analytics['report_scope'] ?? 'primary');
        if ($scope === 'special') {
            $filename .= '-reporte-aparte';
        }
        $filename .= '.xlsx';

        Helpers::downloadXlsx($filename, [
            ['name' => 'Resumen', 'rows' => $summaryRows],
            ['name' => 'Conteo territorial', 'rows' => $territorialRows],
            ['name' => 'Conteo por pregunta', 'rows' => $questionRows],
        ]);
    }

    public function electionHomologationAnalytics(int $surveyId, array $filters = [], ?array $user = null): array
    {
        $survey = $this->getSurvey($surveyId, $user);
        if (!$survey) {
            throw new InvalidArgumentException('Encuesta no encontrada.');
        }

        $questionMeta = $this->buildElectionHomologationQuestionMeta($survey);
        $locationQuestion = $this->resolveAnalyticsLocationQuestion($survey);
        $selectedLocationValue = $this->normalizeAnalyticsFilterValue($filters['location'] ?? null);

        $baseWhere = ['sr.survey_id = :survey_id'];
        $baseParams = [':survey_id' => $surveyId];

        if (!empty($filters['from'])) {
            $baseWhere[] = 'sr.submitted_at >= :from';
            $baseParams[':from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $baseWhere[] = 'sr.submitted_at <= :to';
            $baseParams[':to'] = $filters['to'] . ' 23:59:59';
        }

        $baseResponses = $this->db->fetchAll(
            'SELECT sr.id, sr.submitted_at FROM survey_responses sr WHERE ' . implode(' AND ', $baseWhere) . ' ORDER BY sr.submitted_at ASC',
            $baseParams
        );
        $baseTotalResponses = count($baseResponses);

        $locationOptions = [];
        if ($this->analyticsQuestionSupportsLocationFilter($locationQuestion)) {
            $locationOptions = $this->fetchAnalyticsLocationOptions($locationQuestion, $baseWhere, $baseParams);
            if ($selectedLocationValue !== null && !in_array($selectedLocationValue, array_column($locationOptions, 'value'), true)) {
                $selectedLocationValue = null;
            }
        } else {
            $selectedLocationValue = null;
        }

        $locationSelectedLabel = 'Todos';
        foreach ($locationOptions as $option) {
            if (($option['value'] ?? null) === $selectedLocationValue) {
                $locationSelectedLabel = (string) ($option['label'] ?? 'Todos');
                break;
            }
        }

        $locationFilter = [
            'enabled' => $this->analyticsQuestionSupportsLocationFilter($locationQuestion),
            'question_code' => $locationQuestion['code'] ?? null,
            'question_title' => $locationQuestion['prompt'] ?? null,
            'question_type' => $locationQuestion['question_type'] ?? null,
            'selected_value' => $selectedLocationValue ?? 'all',
            'selected_label' => $locationSelectedLabel,
            'all_label' => 'Todos',
            'active_option_count' => count(array_filter($locationOptions, static fn(array $option): bool => (int) ($option['count'] ?? 0) > 0)),
            'options' => array_merge([[
                'value' => 'all',
                'label' => 'Todos',
                'count' => $baseTotalResponses,
            ]], $locationOptions),
        ];

        $where = $baseWhere;
        $params = $baseParams;
        if ($selectedLocationValue !== null && $locationQuestion) {
            $this->appendAnalyticsLocationConstraint($where, $params, $locationQuestion, $selectedLocationValue);
        }

        $responses = $selectedLocationValue === null
            ? $baseResponses
            : $this->db->fetchAll(
                'SELECT sr.id, sr.submitted_at FROM survey_responses sr WHERE ' . implode(' AND ', $where) . ' ORDER BY sr.submitted_at ASC',
                $params
            );

        $totalResponses = count($responses);
        $questionPattern = '^Q(26|27|28|29|30)(_|$)';

        $familyStats = [];
        foreach ($questionMeta as $exactCode => $meta) {
            $familyKey = (string) ($meta['family']['key'] ?? '');
            if ($familyKey === '') {
                continue;
            }

            $officeKey = (string) ($meta['family']['office_key'] ?? '');
            $territoryValue = trim((string) ($meta['territory_value'] ?? ''));
            if ($selectedLocationValue !== null && $officeKey !== 'prefectura' && $territoryValue !== '' && $territoryValue !== $selectedLocationValue) {
                continue;
            }

            $familyStats[$familyKey] ??= [
                'key' => $familyKey,
                'base_code' => (string) ($meta['family']['base_code'] ?? ''),
                'label' => (string) ($meta['family']['label'] ?? ''),
                'office_key' => $officeKey,
                'office_label' => (string) ($meta['family']['office_label'] ?? ''),
                'ballot_key' => (string) ($meta['family']['ballot_key'] ?? ''),
                'ballot_label' => (string) ($meta['family']['ballot_label'] ?? ''),
                'sort_order' => (int) ($meta['family']['sort_order'] ?? 999),
                'question_codes' => [],
                'question_titles' => [],
                'territories' => [],
                'responses' => 0,
                'coverage_percentage' => 0.0,
                'first_submission_at' => null,
                'last_submission_at' => null,
                'options_map' => [],
                'options' => [],
                'top_option' => null,
                'summary' => 'Sin respuestas homologadas dentro del filtro actual.',
                'homologated_summary' => 'Sin lectura homologada.',
                'has_activity' => false,
            ];

            $familyStats[$familyKey]['question_codes'][$exactCode] = true;
            $familyStats[$familyKey]['question_titles'][trim((string) ($meta['title'] ?? $exactCode))] = true;
            $territoryLabel = trim((string) ($meta['territory_label'] ?? ''));
            if ($territoryLabel !== '') {
                $familyStats[$familyKey]['territories'][$territoryLabel] = true;
            }
        }

        if ($totalResponses > 0) {
            $queryParams = [':election_question_pattern' => $questionPattern] + $params;

            $answerCountRows = $this->db->fetchAll(
                "SELECT
                    ra.question_code,
                    MIN(ra.question_prompt) AS question_prompt,
                    COUNT(*) AS total,
                    MIN(sr.submitted_at) AS first_submitted_at,
                    MAX(sr.submitted_at) AS last_submitted_at
                 FROM survey_responses sr
                 INNER JOIN response_answers ra ON ra.response_id = sr.id
                 WHERE " . implode(' AND ', $where) . "
                   AND ra.question_code REGEXP :election_question_pattern
                 GROUP BY ra.question_code
                 ORDER BY ra.question_code ASC",
                $queryParams
            );

            foreach ($answerCountRows as $row) {
                $questionCode = trim((string) ($row['question_code'] ?? ''));
                $family = $this->resolveElectionHomologationFamily($questionCode);
                if ($family === null) {
                    continue;
                }

                $familyKey = $family['key'];
                $meta = $questionMeta[$questionCode] ?? [
                    'title' => $this->sanitizeAnalyticsQuestionTitle((string) ($row['question_prompt'] ?? $family['label'])),
                    'territory_label' => $family['office_key'] === 'prefectura' ? 'Provincial' : 'Sin territorio',
                    'family' => $family,
                ];

                $familyStats[$familyKey] ??= [
                    'key' => $familyKey,
                    'base_code' => (string) ($family['base_code'] ?? ''),
                    'label' => (string) ($family['label'] ?? ''),
                    'office_key' => (string) ($family['office_key'] ?? ''),
                    'office_label' => (string) ($family['office_label'] ?? ''),
                    'ballot_key' => (string) ($family['ballot_key'] ?? ''),
                    'ballot_label' => (string) ($family['ballot_label'] ?? ''),
                    'sort_order' => (int) ($family['sort_order'] ?? 999),
                    'question_codes' => [],
                    'question_titles' => [],
                    'territories' => [],
                    'responses' => 0,
                    'coverage_percentage' => 0.0,
                    'first_submission_at' => null,
                    'last_submission_at' => null,
                    'options_map' => [],
                    'options' => [],
                    'top_option' => null,
                    'summary' => 'Sin respuestas homologadas dentro del filtro actual.',
                    'homologated_summary' => 'Sin lectura homologada.',
                    'has_activity' => false,
                ];

                $familyStats[$familyKey]['question_codes'][$questionCode] = true;
                $familyStats[$familyKey]['question_titles'][trim((string) ($meta['title'] ?? $questionCode))] = true;

                $territoryLabel = trim((string) ($meta['territory_label'] ?? ''));
                if ($territoryLabel !== '') {
                    $familyStats[$familyKey]['territories'][$territoryLabel] = true;
                }

                $familyStats[$familyKey]['responses'] += (int) ($row['total'] ?? 0);

                $firstSubmittedAt = $row['first_submitted_at'] ?? null;
                $lastSubmittedAt = $row['last_submitted_at'] ?? null;

                if ($firstSubmittedAt !== null && ($familyStats[$familyKey]['first_submission_at'] === null || strcmp((string) $firstSubmittedAt, (string) $familyStats[$familyKey]['first_submission_at']) < 0)) {
                    $familyStats[$familyKey]['first_submission_at'] = $firstSubmittedAt;
                }

                if ($lastSubmittedAt !== null && ($familyStats[$familyKey]['last_submission_at'] === null || strcmp((string) $lastSubmittedAt, (string) $familyStats[$familyKey]['last_submission_at']) > 0)) {
                    $familyStats[$familyKey]['last_submission_at'] = $lastSubmittedAt;
                }
            }

            $optionRows = $this->db->fetchAll(
                "SELECT
                    ra.question_code,
                    MIN(ra.question_prompt) AS question_prompt,
                    rao.option_code,
                    MIN(rao.option_label) AS option_label,
                    COUNT(*) AS total
                 FROM survey_responses sr
                 INNER JOIN response_answers ra ON ra.response_id = sr.id
                 INNER JOIN response_answer_options rao ON rao.response_answer_id = ra.id
                 WHERE " . implode(' AND ', $where) . "
                   AND ra.question_code REGEXP :election_question_pattern
                 GROUP BY ra.question_code, rao.option_code
                 ORDER BY ra.question_code ASC, total DESC",
                $queryParams
            );

            foreach ($optionRows as $row) {
                $questionCode = trim((string) ($row['question_code'] ?? ''));
                $family = $this->resolveElectionHomologationFamily($questionCode);
                if ($family === null) {
                    continue;
                }

                $familyKey = $family['key'];
                $meta = $questionMeta[$questionCode] ?? [
                    'title' => $this->sanitizeAnalyticsQuestionTitle((string) ($row['question_prompt'] ?? $family['label'])),
                    'options' => [],
                    'option_labels' => [],
                    'family' => $family,
                ];
                $resolvedLabel = $this->resolveAnalyticsOptionLabel(
                    $meta,
                    (string) ($row['option_code'] ?? ''),
                    (string) ($row['option_label'] ?? '')
                );
                $bucket = $this->resolveElectionHomologationBucket((string) ($row['option_code'] ?? ''), $resolvedLabel);
                $count = (int) ($row['total'] ?? 0);

                $familyStats[$familyKey]['options_map'][$bucket['key']] ??= [
                    'key' => $bucket['key'],
                    'label' => $bucket['label'],
                    'tone' => $bucket['tone'],
                    'count' => 0,
                    'percentage' => 0.0,
                    'aliases' => [],
                    'raw_codes' => [],
                ];
                $familyStats[$familyKey]['options_map'][$bucket['key']]['count'] += $count;
                if ($resolvedLabel !== '') {
                    $familyStats[$familyKey]['options_map'][$bucket['key']]['aliases'][$resolvedLabel] = true;
                }
                $rawCode = trim((string) ($row['option_code'] ?? ''));
                if ($rawCode !== '') {
                    $familyStats[$familyKey]['options_map'][$bucket['key']]['raw_codes'][$rawCode] = true;
                }
            }
        }

        $officeStats = [];
        $equivalenceByOffice = [];

        foreach ($familyStats as &$familyStat) {
            $familyStat['coverage_percentage'] = $totalResponses > 0
                ? round(($familyStat['responses'] / $totalResponses) * 100, 1)
                : 0.0;
            $familyStat['has_activity'] = $familyStat['responses'] > 0;
            $familyStat['question_codes'] = array_values(array_keys($familyStat['question_codes']));
            sort($familyStat['question_codes']);
            $familyStat['question_titles'] = array_values(array_keys($familyStat['question_titles']));
            $familyStat['territories'] = array_values(array_keys($familyStat['territories']));
            sort($familyStat['territories']);
            $familyStat['territory_label'] = $familyStat['office_key'] === 'prefectura'
                ? 'Provincial'
                : (count($familyStat['territories']) > 1
                    ? 'Multicantón (' . count($familyStat['territories']) . ')'
                    : ($familyStat['territories'][0] ?? 'Sin territorio'));
            $familyStat['options'] = $this->finalizeElectionHomologationBuckets(
                (array) ($familyStat['options_map'] ?? []),
                (int) ($familyStat['responses'] ?? 0)
            );
            unset($familyStat['options_map']);

            $familyStat['top_option'] = $familyStat['options'][0] ?? null;
            $familyStat['summary'] = is_array($familyStat['top_option'])
                ? sprintf(
                    '%s lidera con %s (%d).',
                    (string) ($familyStat['top_option']['label'] ?? 'La principal'),
                    number_format((float) ($familyStat['top_option']['percentage'] ?? 0), 1) . '%',
                    (int) ($familyStat['top_option']['count'] ?? 0)
                )
                : 'Sin respuestas homologadas dentro del filtro actual.';
            $familyStat['homologated_summary'] = $this->summarizeElectionHomologationOptions(
                array_slice((array) ($familyStat['options'] ?? []), 0, 4)
            );

            $officeKey = (string) ($familyStat['office_key'] ?? '');
            if ($officeKey === '') {
                continue;
            }

            $officeStats[$officeKey] ??= [
                'key' => $officeKey,
                'label' => (string) ($familyStat['office_label'] ?? ucfirst($officeKey)),
                'sort_order' => $officeKey === 'prefectura' ? 1 : 2,
                'family_count' => 0,
                'responses' => 0,
                'coverage_sum' => 0.0,
                'average_coverage' => 0.0,
                'options_map' => [],
                'options' => [],
                'top_option' => null,
                'summary' => 'Sin respuestas homologadas.',
            ];
            $officeStats[$officeKey]['family_count']++;
            $officeStats[$officeKey]['responses'] += (int) ($familyStat['responses'] ?? 0);
            $officeStats[$officeKey]['coverage_sum'] += (float) ($familyStat['coverage_percentage'] ?? 0);

            foreach ((array) ($familyStat['options'] ?? []) as $option) {
                $officeStats[$officeKey]['options_map'][$option['key']] ??= [
                    'key' => $option['key'],
                    'label' => $option['label'],
                    'tone' => $option['tone'],
                    'count' => 0,
                    'percentage' => 0.0,
                    'aliases' => [],
                    'raw_codes' => [],
                ];
                $officeStats[$officeKey]['options_map'][$option['key']]['count'] += (int) ($option['count'] ?? 0);

                foreach ((array) ($option['aliases'] ?? []) as $alias) {
                    $officeStats[$officeKey]['options_map'][$option['key']]['aliases'][$alias] = true;
                }
                foreach ((array) ($option['raw_codes'] ?? []) as $rawCode) {
                    $officeStats[$officeKey]['options_map'][$option['key']]['raw_codes'][$rawCode] = true;
                }

                $equivalenceByOffice[$officeKey][$option['key']] ??= [
                    'key' => $option['key'],
                    'label' => $option['label'],
                    'tone' => $option['tone'],
                    'count' => 0,
                    'aliases' => [],
                    'raw_codes' => [],
                    'family_labels' => [],
                ];
                $equivalenceByOffice[$officeKey][$option['key']]['count'] += (int) ($option['count'] ?? 0);
                foreach ((array) ($option['aliases'] ?? []) as $alias) {
                    $equivalenceByOffice[$officeKey][$option['key']]['aliases'][$alias] = true;
                }
                foreach ((array) ($option['raw_codes'] ?? []) as $rawCode) {
                    $equivalenceByOffice[$officeKey][$option['key']]['raw_codes'][$rawCode] = true;
                }
                $equivalenceByOffice[$officeKey][$option['key']]['family_labels'][(string) ($familyStat['label'] ?? '')] = true;
            }
        }
        unset($familyStat);

        foreach ($officeStats as &$officeStat) {
            $officeStat['average_coverage'] = $officeStat['family_count'] > 0
                ? round($officeStat['coverage_sum'] / $officeStat['family_count'], 1)
                : 0.0;
            unset($officeStat['coverage_sum']);
            $officeStat['options'] = $this->finalizeElectionHomologationBuckets(
                (array) ($officeStat['options_map'] ?? []),
                (int) ($officeStat['responses'] ?? 0)
            );
            unset($officeStat['options_map']);
            $officeStat['top_option'] = $officeStat['options'][0] ?? null;
            $officeStat['summary'] = is_array($officeStat['top_option'])
                ? sprintf(
                    '%s concentra %s (%d).',
                    (string) ($officeStat['top_option']['label'] ?? 'El bloque principal'),
                    number_format((float) ($officeStat['top_option']['percentage'] ?? 0), 1) . '%',
                    (int) ($officeStat['top_option']['count'] ?? 0)
                )
                : 'Sin respuestas homologadas.';
        }
        unset($officeStat);

        uasort($officeStats, static function (array $left, array $right): int {
            return ((int) ($left['sort_order'] ?? 999) <=> (int) ($right['sort_order'] ?? 999))
                ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        uasort($familyStats, static function (array $left, array $right): int {
            return ((int) ($left['sort_order'] ?? 999) <=> (int) ($right['sort_order'] ?? 999))
                ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        $equivalenceRows = [];
        foreach ($officeStats as $officeKey => $officeStat) {
            $officeEquivalences = $equivalenceByOffice[$officeKey] ?? [];
            $officeRows = [];

            foreach ($officeEquivalences as $bucketKey => $bucketRow) {
                $aliases = array_values(array_keys((array) ($bucketRow['aliases'] ?? [])));
                sort($aliases);
                $rawCodes = array_values(array_keys((array) ($bucketRow['raw_codes'] ?? [])));
                sort($rawCodes);
                $familyLabels = array_values(array_keys((array) ($bucketRow['family_labels'] ?? [])));
                sort($familyLabels);

                $officeRows[] = [
                    'key' => $bucketKey,
                    'label' => (string) ($bucketRow['label'] ?? ''),
                    'tone' => (string) ($bucketRow['tone'] ?? 'candidate'),
                    'count' => (int) ($bucketRow['count'] ?? 0),
                    'percentage' => (int) ($officeStat['responses'] ?? 0) > 0
                        ? round(((int) ($bucketRow['count'] ?? 0) / (int) $officeStat['responses']) * 100, 1)
                        : 0.0,
                    'aliases' => $aliases,
                    'raw_codes' => $rawCodes,
                    'family_labels' => $familyLabels,
                ];
            }

            usort($officeRows, fn(array $left, array $right): int => $this->compareElectionHomologationBucketRows($left, $right));

            $equivalenceRows[] = [
                'office_key' => $officeKey,
                'office_label' => (string) ($officeStat['label'] ?? ucfirst($officeKey)),
                'rows' => $officeRows,
            ];
        }

        return [
            'survey' => [
                'id' => $survey['id'],
                'name' => $survey['name'],
                'status' => $survey['status'],
                'status_label' => Helpers::statusLabel($survey['status']),
                'start_at' => $survey['start_at'],
                'end_at' => $survey['end_at'],
                'window_status' => $survey['window_status'],
            ],
            'summary' => [
                'responses' => $totalResponses,
                'families' => count($familyStats),
                'offices' => count($officeStats),
                'active_families' => count(array_filter($familyStats, static fn(array $family): bool => (bool) ($family['has_activity'] ?? false))),
                'location_label' => $locationFilter['selected_label'],
                'first_submission_at' => $responses[0]['submitted_at'] ?? null,
                'last_submission_at' => $responses !== [] ? $responses[count($responses) - 1]['submitted_at'] : null,
                'date_range' => [
                    'from' => $filters['from'] ?? null,
                    'to' => $filters['to'] ?? null,
                ],
            ],
            'location_filter' => $locationFilter,
            'offices' => array_values($officeStats),
            'families' => array_values($familyStats),
            'equivalences' => $equivalenceRows,
        ];
    }

    private function buildAnalyticsQuickReadingLabel(array $row): string
    {
        $highlightLabel = trim((string) ($row['highlight_label'] ?? ''));
        $highlightCount = $row['highlight_count'] ?? null;
        $highlightPercentage = $row['highlight_percentage'] ?? null;

        if ($highlightLabel !== '' && $highlightPercentage !== null && $highlightPercentage !== '') {
            return sprintf(
                '%s · %s (%d)',
                $highlightLabel,
                number_format((float) $highlightPercentage, 1) . '%',
                (int) $highlightCount
            );
        }

        if ($highlightLabel !== '' && $highlightCount !== null && $highlightCount !== '') {
            return sprintf('%s · %d', $highlightLabel, (int) $highlightCount);
        }

        return (string) ($row['summary'] ?? 'Sin lectura rápida.');
    }

    private function buildElectionHomologationQuestionMeta(array $survey): array
    {
        $locationLabels = $this->buildElectionHomologationLocationLabelMap($survey);
        $meta = [];

        foreach ((array) ($survey['questions_flat'] ?? []) as $question) {
            $code = trim((string) ($question['code'] ?? ''));
            $family = $this->resolveElectionHomologationFamily($code);
            if ($family === null) {
                continue;
            }

            $optionLabels = [];
            foreach ((array) ($question['options'] ?? []) as $option) {
                $optionCode = trim((string) ($option['code'] ?? ''));
                if ($optionCode === '') {
                    continue;
                }

                $optionLabels[$optionCode] = trim((string) ($option['label'] ?? $optionCode));
            }

            $meta[$code] = [
                'code' => $code,
                'title' => $this->sanitizeAnalyticsQuestionTitle((string) ($question['prompt'] ?? '')),
                'options' => (array) ($question['options'] ?? []),
                'option_labels' => $optionLabels,
                'territory_value' => $this->resolveElectionHomologationTerritoryValue($question, (string) ($family['office_key'] ?? '')),
                'territory_label' => $this->resolveElectionHomologationTerritoryLabel($question, $locationLabels, (string) ($family['office_key'] ?? '')),
                'family' => $family,
            ];
        }

        return $meta;
    }

    private function buildElectionHomologationLocationLabelMap(array $survey): array
    {
        $labels = [];
        $locationQuestion = $this->resolveAnalyticsLocationQuestion($survey);
        if (!is_array($locationQuestion)) {
            return $labels;
        }

        foreach ((array) ($locationQuestion['options'] ?? []) as $option) {
            $code = trim((string) ($option['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $labels[$code] = trim((string) ($option['label'] ?? $code));
        }

        return $labels;
    }

    private function resolveElectionHomologationTerritoryValue(array $question, string $officeKey): ?string
    {
        if ($officeKey === 'prefectura') {
            return null;
        }

        foreach ((array) ($question['visibility_rules'] ?? []) as $rule) {
            if (trim((string) ($rule['question_code'] ?? '')) !== 'Q1') {
                continue;
            }

            if (trim((string) ($rule['operator'] ?? '')) !== 'equals') {
                continue;
            }

            $value = trim((string) ($rule['value'] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolveElectionHomologationTerritoryLabel(array $question, array $locationLabels, string $officeKey): string
    {
        if ($officeKey === 'prefectura') {
            return 'Provincial';
        }

        foreach ((array) ($question['visibility_rules'] ?? []) as $rule) {
            if (trim((string) ($rule['question_code'] ?? '')) !== 'Q1') {
                continue;
            }

            if (trim((string) ($rule['operator'] ?? '')) !== 'equals') {
                continue;
            }

            $value = trim((string) ($rule['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            return trim((string) ($locationLabels[$value] ?? $this->humanizeElectionHomologationCode($value)));
        }

        return 'Multicantón';
    }

    private function resolveElectionHomologationFamily(string $questionCode): ?array
    {
        $normalized = strtoupper(trim($questionCode));

        return match (true) {
            preg_match('/^Q26(?:_|$)/', $normalized) === 1 => [
                'key' => 'prefectura_a',
                'base_code' => 'Q26',
                'label' => 'Prefectura · Papeleta A',
                'office_key' => 'prefectura',
                'office_label' => 'Prefectura',
                'ballot_key' => 'A',
                'ballot_label' => 'Papeleta A',
                'sort_order' => 10,
            ],
            preg_match('/^Q27(?:_|$)/', $normalized) === 1 => [
                'key' => 'prefectura_b',
                'base_code' => 'Q27',
                'label' => 'Prefectura · Papeleta B',
                'office_key' => 'prefectura',
                'office_label' => 'Prefectura',
                'ballot_key' => 'B',
                'ballot_label' => 'Papeleta B',
                'sort_order' => 20,
            ],
            preg_match('/^Q28(?:_|$)/', $normalized) === 1 => [
                'key' => 'alcaldias_a',
                'base_code' => 'Q28',
                'label' => 'Alcaldías · Papeleta A',
                'office_key' => 'alcaldias',
                'office_label' => 'Alcaldías',
                'ballot_key' => 'A',
                'ballot_label' => 'Papeleta A',
                'sort_order' => 30,
            ],
            preg_match('/^Q29(?:_|$)/', $normalized) === 1 => [
                'key' => 'alcaldias_b',
                'base_code' => 'Q29',
                'label' => 'Alcaldías · Papeleta B',
                'office_key' => 'alcaldias',
                'office_label' => 'Alcaldías',
                'ballot_key' => 'B',
                'ballot_label' => 'Papeleta B',
                'sort_order' => 40,
            ],
            preg_match('/^Q30(?:_|$)/', $normalized) === 1 => [
                'key' => 'alcaldias_c',
                'base_code' => 'Q30',
                'label' => 'Alcaldías · Papeleta C',
                'office_key' => 'alcaldias',
                'office_label' => 'Alcaldías',
                'ballot_key' => 'C',
                'ballot_label' => 'Papeleta C',
                'sort_order' => 50,
            ],
            default => null,
        };
    }

    private function resolveElectionHomologationBucket(string $optionCode, string $label): array
    {
        $resolvedLabel = trim($label) !== '' ? trim($label) : $this->humanizeElectionHomologationCode($optionCode);
        $normalized = $this->normalizeAnalyticsOptionBucketLabel($resolvedLabel);
        $normalizedCode = strtoupper(trim($optionCode));

        if (
            str_contains($normalized, 'no sabe')
            || str_contains($normalized, 'no ha decidido')
            || str_contains($normalized, 'no contesta')
        ) {
            return ['key' => 'NO_SABE', 'label' => 'No sabe / no decide', 'tone' => 'neutral'];
        }

        if (
            str_contains($normalized, 'nulo')
            || str_contains($normalized, 'ninguno')
            || str_contains($normalized, 'blanco')
        ) {
            return ['key' => 'NULO', 'label' => 'Nulo / ninguno', 'tone' => 'neutral'];
        }

        if (str_starts_with($normalized, 'otro') || str_contains($normalized, 'alguien nuevo')) {
            return ['key' => 'OTRO', 'label' => 'Otro / nuevo', 'tone' => 'neutral'];
        }

        $organizationBuckets = [
            'RC' => ['revolucion ciudadana', '- rc', ' rc ', 'correa', 'correista'],
            'ADN' => ['adn'],
            'SI_PODEMOS' => ['si podemos'],
            'CAMINANTES' => ['caminantes'],
            'AVANZA' => ['avanza', 'machete'],
            'CONSTRUYE' => ['construye'],
            'PSC' => ['psc'],
            'CREO' => ['creo'],
            'SUMA' => ['suma'],
            'RETO' => ['reto'],
            'PACHAKUTIK' => ['pachakutik'],
        ];

        foreach ($organizationBuckets as $bucketKey => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalized, $pattern)) {
                    return [
                        'key' => $bucketKey,
                        'label' => match ($bucketKey) {
                            'RC' => 'RC',
                            'ADN' => 'ADN',
                            'SI_PODEMOS' => 'Sí Podemos',
                            'CAMINANTES' => 'Caminantes',
                            'AVANZA' => 'Avanza',
                            'CONSTRUYE' => 'Construye',
                            'PSC' => 'PSC',
                            'CREO' => 'CREO',
                            'SUMA' => 'SUMA',
                            'RETO' => 'Reto',
                            'PACHAKUTIK' => 'Pachakutik',
                            default => $resolvedLabel,
                        },
                        'tone' => 'party',
                    ];
                }
            }
        }

        if ($normalizedCode === 'RC5') {
            return ['key' => 'RC', 'label' => 'RC', 'tone' => 'party'];
        }

        $candidateLabel = $this->cleanElectionHomologationCandidateLabel($resolvedLabel);
        if ($candidateLabel === '') {
            $candidateLabel = $this->humanizeElectionHomologationCode($optionCode);
        }

        $candidateKey = strtoupper(str_replace(' ', '_', $this->normalizeAnalyticsOptionBucketLabel($candidateLabel)));
        $candidateKey = preg_replace('/[^A-Z0-9_]+/', '_', $candidateKey) ?: 'CANDIDATURA';
        $candidateKey = trim($candidateKey, '_');

        return [
            'key' => $candidateKey !== '' ? $candidateKey : 'CANDIDATURA',
            'label' => $candidateLabel !== '' ? $candidateLabel : $resolvedLabel,
            'tone' => 'candidate',
        ];
    }

    private function cleanElectionHomologationCandidateLabel(string $label): string
    {
        $clean = trim($label);
        if ($clean === '') {
            return '';
        }

        $clean = preg_replace('/^[A-ZÁÉÍÓÚÑ]\.\s*/u', '', $clean) ?: $clean;
        $clean = preg_replace('/^(candidato\s+de\s+|candidato\s+)/iu', '', $clean) ?: $clean;
        $clean = preg_replace('/\s*-\s*.*/u', '', $clean) ?: $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?: $clean;

        return trim($clean);
    }

    private function humanizeElectionHomologationCode(string $value): string
    {
        $normalized = trim(str_replace('_', ' ', strtolower($value)));
        if ($normalized === '') {
            return '';
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }

    private function finalizeElectionHomologationBuckets(array $bucketMap, int $responses): array
    {
        $buckets = [];

        foreach ($bucketMap as $bucket) {
            $aliases = array_values(array_keys((array) ($bucket['aliases'] ?? [])));
            sort($aliases);
            $rawCodes = array_values(array_keys((array) ($bucket['raw_codes'] ?? [])));
            sort($rawCodes);

            $buckets[] = [
                'key' => (string) ($bucket['key'] ?? ''),
                'label' => (string) ($bucket['label'] ?? ''),
                'tone' => (string) ($bucket['tone'] ?? 'candidate'),
                'count' => (int) ($bucket['count'] ?? 0),
                'percentage' => $responses > 0 ? round(((int) ($bucket['count'] ?? 0) / $responses) * 100, 1) : 0.0,
                'aliases' => $aliases,
                'raw_codes' => $rawCodes,
            ];
        }

        usort($buckets, fn(array $left, array $right): int => $this->compareElectionHomologationBucketRows($left, $right));

        return $buckets;
    }

    private function compareElectionHomologationBucketRows(array $left, array $right): int
    {
        $leftRank = $this->electionHomologationBucketRank((string) ($left['key'] ?? ''), (string) ($left['tone'] ?? 'candidate'));
        $rightRank = $this->electionHomologationBucketRank((string) ($right['key'] ?? ''), (string) ($right['tone'] ?? 'candidate'));

        return ($leftRank <=> $rightRank)
            ?: ((int) ($right['count'] ?? 0) <=> (int) ($left['count'] ?? 0))
            ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    }

    private function electionHomologationBucketRank(string $key, string $tone): int
    {
        return match ($key) {
            'NO_SABE' => 80,
            'NULO' => 90,
            'OTRO' => 100,
            default => $tone === 'party' ? 10 : 30,
        };
    }

    private function summarizeElectionHomologationOptions(array $options): string
    {
        $normalized = array_values(array_filter(array_map(static function (array $option): array {
            return [
                'label' => trim((string) ($option['label'] ?? '')),
                'count' => (int) ($option['count'] ?? 0),
                'percentage' => (float) ($option['percentage'] ?? 0),
            ];
        }, $options), static fn(array $option): bool => $option['label'] !== ''));

        if ($normalized === []) {
            return 'Sin lectura homologada.';
        }

        return implode(' | ', array_map(
            static fn(array $option): string => sprintf(
                '%s · %s (%d)',
                $option['label'],
                number_format($option['percentage'], 1) . '%',
                $option['count']
            ),
            $normalized
        ));
    }

    public function submitResponse(string $slug, array $payload): array
    {
        $survey = $this->getSurveyBySlug($slug, true);
        if (!$survey) {
            return [
                'success' => false,
                'message' => 'La encuesta no está disponible en este momento.',
            ];
        }

        $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
        $errors = [];
        $normalizedAnswers = [];

        foreach ($survey['questions_flat'] as $question) {
            if (!$this->questionIsVisible($question, $answers)) {
                continue;
            }

            $answer = $answers[$question['code']] ?? null;
            $normalized = $this->normalizeAnswer($question, $answer);

            if ($question['is_required'] && $normalized === null) {
                $errors[$question['code']] = 'Esta pregunta es obligatoria.';
                continue;
            }

            if ($normalized !== null) {
                $normalizedAnswers[(int) $question['id']] = [
                    'question' => $question,
                    'answer' => $normalized,
                ];
            }
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'message' => 'Existen preguntas obligatorias pendientes.',
                'errors' => $errors,
            ];
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $clientContext = $this->buildClientContext($payload);
        $metadata['client'] = array_filter([
            'session_token' => $clientContext['session_token'],
            'device_type' => $clientContext['device_type'],
            'device_os' => $clientContext['device_os'],
            'browser' => $clientContext['browser'],
            'screen_resolution' => $clientContext['screen_resolution'],
            'locale' => $clientContext['locale'],
            'referrer' => $clientContext['referrer'],
            'platform' => $clientContext['platform'],
            'timezone' => $clientContext['timezone'],
            'viewport' => $clientContext['viewport'],
            'forwarded_ip' => $clientContext['forwarded_ip'],
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
        $startedAt = $payload['started_at'] ?? null;

        $this->db->beginTransaction();

        try {
            $responseInsert = [
                'survey_id' => $survey['id'],
                'response_uuid' => $this->uuid(),
                'status' => 'completed',
                'metadata_json' => Helpers::encodeJson($metadata),
                'started_at' => $this->normalizeDateTime($startedAt) ?? date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'ip_address' => $clientContext['ip_address'],
                'user_agent' => $clientContext['user_agent'],
            ];

            foreach ([
                'session_token' => 'session_token',
                'device_type' => 'device_type',
                'device_os' => 'device_os',
                'browser' => 'browser',
                'screen_resolution' => 'screen_resolution',
                'locale' => 'locale',
                'referrer' => 'referrer',
            ] as $column => $contextKey) {
                if ($this->tableHasColumn('survey_responses', $column)) {
                    $responseInsert[$column] = $clientContext[$contextKey] ?? null;
                }
            }

            $responseId = $this->db->insert('survey_responses', $responseInsert);

            foreach ($normalizedAnswers as $item) {
                $question = $item['question'];
                $answer = $item['answer'];

                $answerId = $this->db->insert('response_answers', [
                    'response_id' => $responseId,
                    'question_id' => $question['id'],
                    'question_code' => $question['code'],
                    'question_prompt' => $question['prompt'],
                    'answer_type' => $answer['type'],
                    'answer_text' => $answer['text'],
                    'answer_json' => $answer['json'],
                ]);

                foreach ($answer['options'] as $option) {
                    $this->db->insert('response_answer_options', [
                        'response_answer_id' => $answerId,
                        'option_code' => $option['code'],
                        'option_label' => $option['label'],
                    ]);
                }
            }

            $this->recordSurveyAccess($survey, 'submit', $payload, $responseId, $clientContext);

            $this->db->commit();

            return [
                'success' => true,
                'message' => $survey['thank_you_text'] ?: 'Gracias por completar la encuesta.',
            ];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function seedSurvey(array $definition, int $userId, ?array $actor = null): int
    {
        $surveyId = $this->saveSurvey($definition, $userId, $actor);

        foreach ($definition['sections'] as $section) {
            $sectionId = $this->saveSection($surveyId, $section, $actor);
            foreach ($section['questions'] as $question) {
                $question['section_id'] = $sectionId;
                $this->saveQuestion($surveyId, $question, $actor);
            }
        }

        return $surveyId;
    }

    private function hydrateSurvey(array $survey, bool $publicOnly = false): array
    {
        $sections = $this->db->fetchAll(
            'SELECT * FROM survey_sections WHERE survey_id = :survey_id ORDER BY sort_order ASC, id ASC',
            [':survey_id' => $survey['id']]
        );

        $questions = $this->db->fetchAll(
            'SELECT * FROM survey_questions WHERE survey_id = :survey_id ORDER BY sort_order ASC, id ASC',
            [':survey_id' => $survey['id']]
        );

        $questionIds = array_map(static fn(array $question): int => (int) $question['id'], $questions);
        $optionsByQuestion = [];

        if ($questionIds !== []) {
            $placeholder = implode(',', array_fill(0, count($questionIds), '?'));
            $options = $this->db->fetchAll(
                "SELECT * FROM survey_question_options WHERE question_id IN ($placeholder) ORDER BY sort_order ASC, id ASC",
                $questionIds
            );
            foreach ($options as $option) {
                $optionsByQuestion[(int) $option['question_id']][] = [
                    'code' => $option['option_code'],
                    'label' => $option['option_label'],
                    'value' => $option['option_value'],
                    'is_other' => (bool) $option['is_other_option'],
                ];
            }
        }

        foreach ($questions as &$question) {
            $question['is_required'] = (bool) $question['is_required'];
            $question['visibility_rules'] = Helpers::decodeJson($question['visibility_rules_json'], []);
            $question['validation_rules'] = Helpers::decodeJson($question['validation_rules_json'], []);
            $question['settings'] = Helpers::decodeJson($question['settings_json'], []);
            $question['options'] = $optionsByQuestion[(int) $question['id']] ?? [];

            if ($publicOnly) {
                unset($question['visibility_rules_json'], $question['validation_rules_json'], $question['settings_json']);
            }
        }
        unset($question);

        $questionsBySection = [];
        foreach ($questions as $question) {
            $questionsBySection[(int) $question['section_id']][] = $question;
        }

        foreach ($sections as &$section) {
            $section['settings'] = Helpers::decodeJson($section['settings_json'], []);
            $section['questions'] = $questionsBySection[(int) $section['id']] ?? [];
            if ($publicOnly) {
                unset($section['settings_json']);
            }
        }
        unset($section);

        $survey['settings'] = Helpers::decodeJson($survey['settings_json'], []);
        $survey['sections'] = $sections;
        $survey['questions_flat'] = $questions;
        $survey['window_status'] = $this->resolveWindowStatus($survey);
        $survey['status_label'] = Helpers::statusLabel($survey['status']);

        if ($publicOnly) {
            unset(
                $survey['created_by'],
                $survey['updated_by'],
                $survey['settings_json']
            );
        }

        return $survey;
    }

    private function normalizeOptions(array|string $input): array
    {
        if (is_array($input)) {
            $options = $input;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', trim($input)) ?: [];
            $options = [];
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                [$code, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
                if ($label === null) {
                    $label = $code;
                    $code = 'OPT_' . ($index + 1);
                }

                $options[] = [
                    'code' => strtoupper(Helpers::slugify((string) $code)),
                    'label' => (string) $label,
                    'value' => (string) $label,
                ];
            }
        }

        return array_values(array_filter(array_map(static function (array $option, int $index): array {
            $code = trim((string) ($option['code'] ?? ''));
            $label = trim((string) ($option['label'] ?? ''));
            return [
                'code' => $code !== '' ? strtoupper($code) : 'OPT_' . ($index + 1),
                'label' => $label,
                'value' => trim((string) ($option['value'] ?? $label)),
                'is_other' => !empty($option['is_other']),
            ];
        }, $options, array_keys($options)), static fn(array $option): bool => $option['label'] !== ''));
    }

    private function normalizeJsonField(array|string|null $input): array
    {
        if (is_array($input)) {
            return $input;
        }

        if ($input === null) {
            return [];
        }

        return Helpers::decodeJson((string) $input, []);
    }

    private function buildVisibilityRules(array $payload): array
    {
        if (!empty($payload['visibility_rules'])) {
            return is_array($payload['visibility_rules']) ? $payload['visibility_rules'] : [];
        }

        if (!empty($payload['visibility_rules_json'])) {
            return $this->normalizeJsonField($payload['visibility_rules_json']);
        }

        $questionCode = trim((string) ($payload['visibility_question_code'] ?? ''));
        if ($questionCode === '') {
            return [];
        }

        return [[
            'question_code' => strtoupper($questionCode),
            'operator' => trim((string) ($payload['visibility_operator'] ?? 'equals')) ?: 'equals',
            'value' => trim((string) ($payload['visibility_value'] ?? '')),
        ]];
    }

    private function uniqueSurveySlug(string $slug, int $ignoreId = 0): string
    {
        $baseSlug = $slug;
        $suffix = 1;

        while (true) {
            $existing = $this->db->fetchColumn(
                'SELECT id FROM surveys WHERE slug = :slug AND id != :id LIMIT 1',
                [':slug' => $slug, ':id' => $ignoreId]
            );

            if (!$existing) {
                return $slug;
            }

            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }
    }

    private function getSurveyRecordBySlug(string $slug): ?array
    {
        $survey = $this->db->fetch('SELECT * FROM surveys WHERE slug = :slug LIMIT 1', [':slug' => $slug]);
        return $survey ?: null;
    }

    private function decorateSurveyListingRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['settings'] = Helpers::decodeJson($row['settings_json'], []);
            $row['public_url'] = url('public/index.php?survey=' . urlencode($row['slug']));
            $row['window_status'] = $this->resolveWindowStatus($row);
            $row['status_label'] = Helpers::statusLabel($row['status']);
        }
        unset($row);

        return $rows;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime((string) $value));
    }

    private function resolveWindowStatus(array $survey): string
    {
        $now = time();
        $start = !empty($survey['start_at']) ? strtotime((string) $survey['start_at']) : null;
        $end = !empty($survey['end_at']) ? strtotime((string) $survey['end_at']) : null;

        if (($survey['status'] ?? '') === 'closed') {
            return 'closed';
        }

        if ($start && $now < $start) {
            return 'scheduled';
        }

        if ($end && $now > $end) {
            return 'closed';
        }

        if ($end && (($end - $now) <= 172800)) {
            return 'closing_soon';
        }

        return ($survey['status'] ?? 'draft') === 'active' ? 'active' : 'draft';
    }

    private function parseJsonImport(string $payload): array
    {
        $decoded = Helpers::decodeJson($payload, []);
        if (isset($decoded['sections']) && is_array($decoded['sections'])) {
            return $decoded['sections'];
        }

        if (array_is_list($decoded)) {
            $grouped = [];
            foreach ($decoded as $row) {
                $sectionTitle = trim((string) ($row['section_title'] ?? 'General'));
                $grouped[$sectionTitle]['title'] = $sectionTitle;
                $grouped[$sectionTitle]['description'] = trim((string) ($row['section_description'] ?? ''));
                $grouped[$sectionTitle]['sort_order'] = max(1, (int) ($row['section_sort_order'] ?? 1));
                $grouped[$sectionTitle]['questions'][] = [
                    'code' => $row['code'] ?? '',
                    'prompt' => $row['prompt'] ?? '',
                    'help_text' => $row['help_text'] ?? '',
                    'question_type' => $row['question_type'] ?? 'single_choice',
                    'is_required' => !empty($row['is_required']),
                    'placeholder' => $row['placeholder'] ?? '',
                    'sort_order' => max(1, (int) ($row['sort_order'] ?? 1)),
                    'options' => $row['options'] ?? [],
                    'visibility_rules' => $row['visibility_rules'] ?? [],
                    'settings' => $row['settings'] ?? [],
                ];
            }

            return array_values($grouped);
        }

        throw new InvalidArgumentException('El JSON de importación no tiene un formato válido.');
    }

    private function parseCsvImport(string $payload): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($payload)) ?: [];
        if (count($lines) < 2) {
            throw new InvalidArgumentException('El CSV debe incluir encabezados y al menos una fila.');
        }

        $headers = str_getcsv(array_shift($lines));
        $grouped = [];

        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = array_combine($headers, str_getcsv($line));
            if (!is_array($row)) {
                throw new InvalidArgumentException('No se pudo leer la fila CSV ' . ($lineNumber + 2) . '.');
            }

            $sectionTitle = trim((string) ($row['section_title'] ?? 'General'));
            $grouped[$sectionTitle]['title'] = $sectionTitle;
            $grouped[$sectionTitle]['description'] = trim((string) ($row['section_description'] ?? ''));
            $grouped[$sectionTitle]['sort_order'] = max(1, (int) ($row['section_sort_order'] ?? 1));
            $grouped[$sectionTitle]['questions'][] = [
                'code' => $row['code'] ?? '',
                'prompt' => $row['prompt'] ?? '',
                'help_text' => $row['help_text'] ?? '',
                'question_type' => $row['question_type'] ?? 'single_choice',
                'is_required' => in_array(strtolower((string) ($row['is_required'] ?? '')), ['1', 'true', 'si', 'sí'], true),
                'placeholder' => $row['placeholder'] ?? '',
                'sort_order' => max(1, (int) ($row['sort_order'] ?? 1)),
                'options' => str_replace('|', PHP_EOL, (string) ($row['options'] ?? '')),
                'visibility_question_code' => $row['visibility_question_code'] ?? '',
                'visibility_operator' => $row['visibility_operator'] ?? 'equals',
                'visibility_value' => $row['visibility_value'] ?? '',
                'settings_json' => $row['settings_json'] ?? '',
            ];
        }

        return array_values($grouped);
    }

    private function questionIsVisible(array $question, array $answers): bool
    {
        $rules = $question['visibility_rules'] ?? [];
        if ($rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            $sourceCode = strtoupper((string) ($rule['question_code'] ?? ''));
            $operator = $rule['operator'] ?? 'equals';
            $expected = $rule['value'] ?? null;
            $actual = $answers[$sourceCode] ?? null;

            $result = match ($operator) {
                'not_equals' => $actual !== $expected,
                'contains' => is_array($actual) && in_array($expected, $actual, true),
                default => is_array($actual) ? in_array($expected, $actual, true) : (string) $actual === (string) $expected,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    private function normalizeAnswer(array $question, mixed $answer): ?array
    {
        $type = $question['question_type'];
        $optionsByCode = [];
        foreach ($question['options'] as $option) {
            $optionsByCode[$option['code']] = $option;
        }

        if (in_array($type, ['text', 'textarea'], true)) {
            $value = $this->normalizeAnswerTextValue($answer);
            return $value === null ? null : [
                'type' => $type === 'textarea' ? 'textarea' : 'text',
                'text' => $value,
                'json' => null,
                'options' => [],
            ];
        }

        if (in_array($type, ['single_choice', 'rating'], true)) {
            $value = $this->normalizeAnswerTextValue($answer);
            if ($value === null || !isset($optionsByCode[$value])) {
                return null;
            }

            return [
                'type' => $type,
                'text' => $optionsByCode[$value]['label'],
                'json' => null,
                'options' => [[
                    'code' => $value,
                    'label' => $optionsByCode[$value]['label'],
                ]],
            ];
        }

        if ($type === 'multiple_choice') {
            $values = array_values(array_unique(array_filter((array) $answer)));
            $selected = [];
            foreach ($values as $value) {
                if (isset($optionsByCode[$value])) {
                    $selected[] = [
                        'code' => $value,
                        'label' => $optionsByCode[$value]['label'],
                    ];
                }
            }

            if ($selected === []) {
                return null;
            }

            return [
                'type' => $type,
                'text' => implode(', ', array_column($selected, 'label')),
                'json' => Helpers::encodeJson(array_column($selected, 'code')),
                'options' => $selected,
            ];
        }

        if ($type === 'matrix') {
            if (!is_array($answer) || $answer === []) {
                return null;
            }

            return [
                'type' => 'matrix',
                'text' => null,
                'json' => Helpers::encodeJson($answer),
                'options' => [],
            ];
        }

        return null;
    }

    private function normalizeAnswerTextValue(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        if (is_object($value) && !method_exists($value, '__toString')) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function buildCaptureSummary(array $response): array
    {
        $metadata = is_array($response['metadata'] ?? null) ? $response['metadata'] : [];
        $client = is_array($metadata['client'] ?? null) ? $metadata['client'] : [];

        return [
            'ip_address' => $response['ip_address'] ?? null,
            'submitted_at' => $response['submitted_at'] ?? null,
            'started_at' => $response['started_at'] ?? null,
            'device_type' => $this->tableHasColumn('survey_responses', 'device_type')
                ? ($response['device_type'] ?? 'unknown')
                : ($client['device_type'] ?? 'unknown'),
            'device_os' => $this->tableHasColumn('survey_responses', 'device_os')
                ? ($response['device_os'] ?? null)
                : ($client['device_os'] ?? null),
            'browser' => $this->tableHasColumn('survey_responses', 'browser')
                ? ($response['browser'] ?? null)
                : ($client['browser'] ?? null),
            'screen_resolution' => $this->tableHasColumn('survey_responses', 'screen_resolution')
                ? ($response['screen_resolution'] ?? null)
                : ($client['screen_resolution'] ?? null),
            'locale' => $this->tableHasColumn('survey_responses', 'locale')
                ? ($response['locale'] ?? null)
                : ($client['locale'] ?? null),
            'referrer' => $this->tableHasColumn('survey_responses', 'referrer')
                ? ($response['referrer'] ?? null)
                : ($client['referrer'] ?? null),
            'session_token' => $this->tableHasColumn('survey_responses', 'session_token')
                ? ($response['session_token'] ?? null)
                : ($client['session_token'] ?? null),
        ];
    }

    private function fetchAccessLogs(int $surveyId, int $responseId, string $sessionToken = ''): array
    {
        if (!$this->hasSurveyAccessLogTable()) {
            return [];
        }

        $params = [
            ':survey_id' => $surveyId,
            ':response_id' => $responseId,
        ];

        $conditions = ['response_id = :response_id'];
        if ($sessionToken !== '') {
            $conditions[] = 'session_token = :session_token';
            $params[':session_token'] = $sessionToken;
        }

        $logs = $this->db->fetchAll(
            "SELECT event_type, occurred_at, ip_address, device_type, device_os, browser, screen_resolution, locale, referrer, session_token, metadata_json
             FROM survey_access_logs
             WHERE survey_id = :survey_id AND (" . implode(' OR ', $conditions) . ')
             ORDER BY occurred_at ASC, event_type ASC',
            $params
        );

        foreach ($logs as &$log) {
            $log['metadata'] = Helpers::decodeJson($log['metadata_json'], []);
        }
        unset($log);

        return $logs;
    }

    private function recordSurveyAccess(
        array $survey,
        string $eventType,
        array $payload = [],
        ?int $responseId = null,
        ?array $clientContext = null
    ): void {
        if (!$this->hasSurveyAccessLogTable()) {
            return;
        }

        if (!in_array($eventType, ['view', 'submit'], true)) {
            return;
        }

        $clientContext ??= $this->buildClientContext($payload);
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $metadata['logged_event'] = $eventType;

        $this->db->insert('survey_access_logs', [
            'survey_id' => (int) $survey['id'],
            'response_id' => $responseId,
            'session_token' => $clientContext['session_token'],
            'event_type' => $eventType,
            'occurred_at' => date('Y-m-d H:i:s'),
            'ip_address' => $clientContext['ip_address'],
            'forwarded_ip' => $clientContext['forwarded_ip'],
            'user_agent' => $clientContext['user_agent'],
            'device_type' => $clientContext['device_type'],
            'device_os' => $clientContext['device_os'],
            'browser' => $clientContext['browser'],
            'screen_resolution' => $clientContext['screen_resolution'],
            'locale' => $clientContext['locale'],
            'referrer' => $clientContext['referrer'],
            'metadata_json' => Helpers::encodeJson($metadata),
        ]);
    }

    private function buildClientContext(array $payload = []): array
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $headerUserAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $metadataUserAgent = trim((string) ($metadata['userAgent'] ?? ''));
        $userAgent = substr($metadataUserAgent !== '' ? $metadataUserAgent : $headerUserAgent, 0, 255);
        $platform = trim((string) ($metadata['platform'] ?? ''));

        return [
            'ip_address' => $this->detectClientIp(),
            'forwarded_ip' => substr(trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')), 0, 255) ?: null,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'device_type' => $this->detectDeviceType($userAgent, $metadata),
            'device_os' => $this->detectDeviceOs($userAgent, $platform),
            'browser' => $this->detectBrowser($userAgent),
            'screen_resolution' => substr(trim((string) ($metadata['screenResolution'] ?? $metadata['screen'] ?? '')), 0, 40) ?: null,
            'locale' => substr(trim((string) ($metadata['locale'] ?? '')), 0, 20) ?: null,
            'referrer' => substr(trim((string) ($metadata['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''))), 0, 255) ?: null,
            'session_token' => substr(trim((string) ($metadata['sessionToken'] ?? '')), 0, 64) ?: null,
            'platform' => $platform !== '' ? $platform : null,
            'timezone' => trim((string) ($metadata['timezone'] ?? '')) ?: null,
            'viewport' => trim((string) ($metadata['viewport'] ?? '')) ?: null,
        ];
    }

    private function detectClientIp(): ?string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null,
            $_SERVER['HTTP_CLIENT_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }

            $parts = array_map('trim', explode(',', (string) $candidate));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        return null;
    }

    private function detectDeviceType(string $userAgent, array $metadata = []): string
    {
        $reported = strtolower(trim((string) ($metadata['deviceType'] ?? '')));
        if (in_array($reported, ['desktop', 'mobile', 'tablet', 'bot', 'unknown'], true)) {
            return $reported;
        }

        $ua = strtolower($userAgent);
        if ($ua === '') {
            return 'unknown';
        }

        if (preg_match('/bot|crawler|spider|slurp|curl|wget|facebookexternalhit|preview/i', $ua)) {
            return 'bot';
        }

        if (preg_match('/ipad|tablet|playbook|silk|kindle|android(?!.*mobile)/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/mobi|iphone|ipod|android.*mobile|windows phone|blackberry/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function detectDeviceOs(string $userAgent, string $platform = ''): ?string
    {
        $normalizedPlatform = strtolower($platform);
        if (str_contains($normalizedPlatform, 'android')) {
            return 'Android';
        }
        if (str_contains($normalizedPlatform, 'iphone') || str_contains($normalizedPlatform, 'ipad') || str_contains($normalizedPlatform, 'ios')) {
            return 'iOS';
        }
        if (str_contains($normalizedPlatform, 'mac')) {
            return 'macOS';
        }
        if (str_contains($normalizedPlatform, 'win')) {
            return 'Windows';
        }
        if (str_contains($normalizedPlatform, 'linux')) {
            return 'Linux';
        }

        $ua = strtolower($userAgent);
        return match (true) {
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad'), str_contains($ua, 'ipod') => 'iOS',
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os'), str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'cros') => 'ChromeOS',
            str_contains($ua, 'linux') => 'Linux',
            default => null,
        };
    }

    private function detectBrowser(string $userAgent): ?string
    {
        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'edg/') => 'Edge',
            str_contains($ua, 'opr/'), str_contains($ua, 'opera') => 'Opera',
            str_contains($ua, 'samsungbrowser/') => 'Samsung Internet',
            str_contains($ua, 'chrome/') && !str_contains($ua, 'edg/') && !str_contains($ua, 'opr/') => 'Chrome',
            str_contains($ua, 'firefox/') => 'Firefox',
            str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/') => 'Safari',
            default => null,
        };
    }

    private function hasSurveyAccessLogTable(): bool
    {
        if ($this->surveyAccessLogTableAvailable !== null) {
            return $this->surveyAccessLogTableAvailable;
        }

        $this->surveyAccessLogTableAvailable = $this->tableExists('survey_access_logs');
        return $this->surveyAccessLogTableAvailable;
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
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $quoted = $this->db->pdo()->quote($table);
            $this->tableExistsCache[$table] = (bool) $this->db->fetchColumn("SHOW TABLES LIKE {$quoted}");
        } catch (Throwable) {
            $this->tableExistsCache[$table] = false;
        }

        return $this->tableExistsCache[$table];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!isset($this->tableColumnsCache[$table])) {
            try {
                $rows = $this->db->fetchAll('SHOW COLUMNS FROM `' . $table . '`');
                $this->tableColumnsCache[$table] = array_map(static fn(array $row): string => (string) $row['Field'], $rows);
            } catch (Throwable) {
                $this->tableColumnsCache[$table] = [];
            }
        }

        return in_array($column, $this->tableColumnsCache[$table], true);
    }

    private function expandTimeSeries(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $countsByDate = [];
        foreach ($rows as $row) {
            $countsByDate[(string) $row['label']] = (int) $row['value'];
        }

        $dates = array_keys($countsByDate);
        sort($dates);

        $cursor = strtotime($dates[0] . ' 00:00:00');
        $end = strtotime($dates[count($dates) - 1] . ' 00:00:00');
        $series = [];

        while ($cursor !== false && $cursor <= $end) {
            $label = date('Y-m-d', $cursor);
            $series[] = [
                'label' => $label,
                'value' => $countsByDate[$label] ?? 0,
            ];
            $cursor = strtotime('+1 day', $cursor);
        }

        return $series;
    }

    private function buildMatrixMeta(array $questionMeta, array $matrixData): array
    {
        $settingsMatrix = is_array($questionMeta['settings']['matrix'] ?? null)
            ? $questionMeta['settings']['matrix']
            : [];

        $rows = [];
        foreach ((array) ($settingsMatrix['rows'] ?? []) as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'label' => trim((string) ($row['label'] ?? $code)) ?: $code,
            ];
        }

        if ($rows === []) {
            foreach (array_keys($matrixData) as $rowCode) {
                $rows[] = [
                    'code' => (string) $rowCode,
                    'label' => (string) $rowCode,
                ];
            }
        }

        $dimensions = [];
        foreach ((array) ($settingsMatrix['dimensions'] ?? []) as $dimension) {
            $dimensionCode = trim((string) ($dimension['code'] ?? ''));
            if ($dimensionCode === '') {
                continue;
            }

            $options = [];
            foreach ((array) ($dimension['options'] ?? []) as $option) {
                $optionCode = trim((string) ($option['code'] ?? ''));
                if ($optionCode === '') {
                    continue;
                }

                $options[] = [
                    'code' => $optionCode,
                    'label' => trim((string) ($option['label'] ?? $optionCode)) ?: $optionCode,
                ];
            }

            $dimensions[] = [
                'code' => $dimensionCode,
                'label' => trim((string) ($dimension['label'] ?? $dimensionCode)) ?: $dimensionCode,
                'options' => $options,
            ];
        }

        if ($dimensions === []) {
            $dimensionCodes = [];
            foreach ($matrixData as $rowData) {
                foreach (array_keys((array) $rowData) as $dimensionCode) {
                    $dimensionCodes[$dimensionCode] = true;
                }
            }

            foreach (array_keys($dimensionCodes) as $dimensionCode) {
                $optionCodes = [];
                foreach ($matrixData as $rowData) {
                    foreach (array_keys((array) ($rowData[$dimensionCode] ?? [])) as $optionCode) {
                        $optionCodes[$optionCode] = true;
                    }
                }

                $options = [];
                foreach (array_keys($optionCodes) as $optionCode) {
                    $options[] = [
                        'code' => (string) $optionCode,
                        'label' => (string) $optionCode,
                    ];
                }

                $dimensions[] = [
                    'code' => (string) $dimensionCode,
                    'label' => (string) $dimensionCode,
                    'options' => $options,
                ];
            }
        }

        return [
            'rows' => $rows,
            'dimensions' => $dimensions,
        ];
    }

    private function buildAnalyticsHighlights(
        int $totalResponses,
        int $activeDays,
        float $averagePerDay,
        ?string $firstSubmittedAt,
        ?string $lastSubmittedAt,
        array $coverage,
        array $sectionStats,
        array $questionStats
    ): array {
        if ($totalResponses === 0) {
            return [[
                'tone' => 'muted',
                'title' => 'Sin respuestas suficientes',
                'value' => 'N = 0',
                'description' => 'Aún no existen registros válidos para construir hallazgos ejecutivos.',
            ]];
        }

        $highlights = [[
            'tone' => 'primary',
            'title' => 'Muestra analizada',
            'value' => 'N = ' . $totalResponses,
            'description' => sprintf(
                '%d día(s) con actividad y %s respuestas promedio por día.',
                $activeDays,
                number_format($averagePerDay, 1)
            ),
        ]];

        if ($firstSubmittedAt && $lastSubmittedAt) {
            $highlights[] = [
                'tone' => 'neutral',
                'title' => 'Ventana observada',
                'value' => date('d/m/Y', strtotime($firstSubmittedAt)) . ' a ' . date('d/m/Y', strtotime($lastSubmittedAt)),
                'description' => 'Periodo efectivo de levantamiento dentro de los filtros aplicados.',
            ];
        }

        $topCoverage = array_values(array_filter($coverage, static fn(array $item): bool => $item['responses'] > 0))[0] ?? null;
        if ($topCoverage) {
            $highlights[] = [
                'tone' => 'success',
                'title' => 'Mayor cobertura',
                'value' => $topCoverage['code'] . ' · ' . number_format($topCoverage['coverage_percentage'], 1) . '%',
                'description' => $topCoverage['title'],
            ];
        }

        $sectionsWithCoverage = array_values(array_filter($sectionStats, static fn(array $item): bool => $item['question_count'] > 0));
        usort($sectionsWithCoverage, static fn(array $left, array $right): int => $right['average_coverage'] <=> $left['average_coverage']);
        $topSection = $sectionsWithCoverage[0] ?? null;
        if ($topSection) {
            $highlights[] = [
                'tone' => 'warning',
                'title' => 'Sección más completa',
                'value' => number_format($topSection['average_coverage'], 1) . '%',
                'description' => $topSection['title'],
            ];
        }

        $dominantChoice = null;
        foreach ($questionStats as $stat) {
            if (($stat['type'] ?? null) !== 'choice' || empty($stat['options'])) {
                continue;
            }

            $topOption = $stat['options'][0];
            if ($dominantChoice === null || $topOption['percentage'] > $dominantChoice['percentage']) {
                $dominantChoice = [
                    'code' => $stat['code'],
                    'title' => $stat['title'],
                    'label' => $topOption['label'],
                    'percentage' => $topOption['percentage'],
                ];
            }
        }

        if ($dominantChoice) {
            $highlights[] = [
                'tone' => 'success',
                'title' => 'Preferencia dominante',
                'value' => $dominantChoice['label'] . ' · ' . number_format($dominantChoice['percentage'], 1) . '%',
                'description' => $dominantChoice['code'] . '. ' . $dominantChoice['title'],
            ];
        }

        return array_slice($highlights, 0, 6);
    }

    private function topKeywords(array $texts): array
    {
        $stopWords = [
            'de', 'la', 'el', 'los', 'las', 'y', 'o', 'que', 'en', 'un', 'una', 'para',
            'con', 'sin', 'por', 'del', 'al', 'se', 'es', 'no', 'si', 'sí', 'muy', 'mas',
        ];

        $frequencies = [];

        foreach ($texts as $text) {
            $normalized = mb_strtolower($text, 'UTF-8');
            $normalized = preg_replace('/[^a-záéíóúñü0-9\s]/u', ' ', $normalized) ?: '';
            $parts = preg_split('/\s+/u', trim($normalized)) ?: [];

            foreach ($parts as $part) {
                if ($part === '' || mb_strlen($part, 'UTF-8') < 3 || in_array($part, $stopWords, true)) {
                    continue;
                }
                $frequencies[$part] = ($frequencies[$part] ?? 0) + 1;
            }
        }

        arsort($frequencies);

        $result = [];
        foreach (array_slice($frequencies, 0, 15, true) as $word => $count) {
            $result[] = ['word' => $word, 'count' => $count];
        }

        return $result;
    }

    private function findQuestionTitle(array $survey, string $code): string
    {
        foreach ($survey['questions_flat'] as $question) {
            if ($question['code'] === $code) {
                return $question['prompt'];
            }
        }

        return $code;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
