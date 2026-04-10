<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

if (!Database::isInstalled()) {
    json_response(['success' => false, 'message' => 'El sistema no está instalado.'], 503);
}

Helpers::startSession();

if (!auth()->check()) {
    json_response(['success' => false, 'message' => 'Sesión no válida.'], 401);
}

$authUser = auth()->user();
$requirePermission = static function (bool $allowed, string $message): void {
    if (!$allowed) {
        json_response(['success' => false, 'message' => $message], 403);
    }
};

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = Helpers::requestJson();
$payload = array_merge($_GET, $_POST, $input);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload[CSRF_TOKEN_NAME] ?? null;
    if (!auth()->validateCsrf($token)) {
        json_response(['success' => false, 'message' => 'Token CSRF inválido.'], 419);
    }
}

try {
    switch ($action) {
        case 'save_survey':
            $requirePermission(auth()->canManageSurveys(), 'No tiene permisos para administrar encuestas.');
            $surveyId = surveys()->saveSurvey($payload, (int) auth()->id(), $authUser);
            json_response(['success' => true, 'message' => 'Encuesta guardada.', 'survey_id' => $surveyId]);

        case 'delete_survey':
            $requirePermission(auth()->canManageSurveys(), 'No tiene permisos para administrar encuestas.');
            surveys()->deleteSurvey((int) ($payload['id'] ?? 0), $authUser);
            json_response(['success' => true, 'message' => 'Encuesta eliminada.']);

        case 'save_section':
            $requirePermission(auth()->canManageSurveys(), 'No tiene permisos para administrar encuestas.');
            $surveyId = (int) ($payload['survey_id'] ?? 0);
            $sectionId = surveys()->saveSection($surveyId, $payload, $authUser);
            json_response(['success' => true, 'message' => 'Sección guardada.', 'section_id' => $sectionId]);

        case 'delete_section':
            $requirePermission(auth()->canManageSurveys(), 'No tiene permisos para administrar encuestas.');
            surveys()->deleteSection((int) ($payload['id'] ?? 0), $authUser);
            json_response(['success' => true, 'message' => 'Sección eliminada.']);

        case 'save_question':
            $requirePermission(auth()->canManageSurveys(), 'No tiene permisos para administrar encuestas.');
            $surveyId = (int) ($payload['survey_id'] ?? 0);
            $questionId = surveys()->saveQuestion($surveyId, $payload, $authUser);
            json_response(['success' => true, 'message' => 'Pregunta guardada.', 'question_id' => $questionId]);

        case 'delete_question':
            $requirePermission(auth()->canManageSurveys(), 'No tiene permisos para administrar encuestas.');
            surveys()->deleteQuestion((int) ($payload['id'] ?? 0), $authUser);
            json_response(['success' => true, 'message' => 'Pregunta eliminada.']);

        case 'import_questions':
            $requirePermission(auth()->canManageSurveys(), 'No tiene permisos para administrar encuestas.');
            $counts = surveys()->importStructure(
                (int) ($payload['survey_id'] ?? 0),
                (string) ($payload['format'] ?? 'json'),
                (string) ($payload['payload'] ?? ''),
                $authUser
            );
            json_response(['success' => true, 'message' => 'Carga masiva completada.', 'counts' => $counts]);

        case 'responses':
            $requirePermission(auth()->canAccessInsights(), 'No tiene permisos para consultar respuestas.');
            $surveyId = !empty($payload['survey_id']) ? (int) $payload['survey_id'] : null;
            $responses = surveys()->listResponses($surveyId, [
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
            ], $authUser);
            json_response(['success' => true, 'data' => $responses]);

        case 'response_detail':
            $requirePermission(auth()->canAccessInsights(), 'No tiene permisos para consultar respuestas.');
            $response = surveys()->getResponse((int) ($payload['id'] ?? 0), $authUser);
            if (!$response) {
                json_response(['success' => false, 'message' => 'Respuesta no encontrada.'], 404);
            }
            json_response(['success' => true, 'data' => $response]);

        case 'stats':
            $requirePermission(auth()->canAccessInsights(), 'No tiene permisos para consultar reportes.');
            $surveyId = (int) ($payload['survey_id'] ?? 0);
            $stats = surveys()->analytics($surveyId, [
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
                'location' => $payload['location'] ?? null,
                'report_scope' => $payload['report_scope'] ?? null,
            ], $authUser);
            json_response(['success' => true, 'data' => $stats]);

        case 'stats_export_xlsx':
            $requirePermission(auth()->canAccessInsights(), 'No tiene permisos para consultar reportes.');
            $surveyId = (int) ($payload['survey_id'] ?? 0);
            $stats = surveys()->analytics($surveyId, [
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
                'location' => $payload['location'] ?? null,
                'report_scope' => $payload['report_scope'] ?? null,
            ], $authUser);
            surveys()->downloadAnalyticsCountMatrixXlsx($stats);

        case 'save_user':
            $requirePermission(auth()->canManageUsers(), 'No tiene permisos para administrar usuarios.');
            $userId = users()->saveUser($payload, (int) auth()->id());
            json_response(['success' => true, 'message' => 'Usuario guardado.', 'user_id' => $userId]);

        case 'delete_user':
            $requirePermission(auth()->canManageUsers(), 'No tiene permisos para administrar usuarios.');
            users()->deleteUser((int) ($payload['id'] ?? 0), (int) auth()->id());
            json_response(['success' => true, 'message' => 'Usuario eliminado.']);

        default:
            json_response(['success' => false, 'message' => 'Acción no soportada.'], 400);
    }
} catch (Throwable $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
}
