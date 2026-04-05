<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$payload = array_merge($_GET, $_POST, Helpers::requestJson());

try {
    switch ($action) {
        case 'track':
            $surveySlug = (string) ($payload['survey'] ?? $payload['slug'] ?? '');
            $result = surveys()->trackSurveyAccess($surveySlug, $payload);
            json_response($result, 200);

        case 'submit':
            $surveySlug = (string) ($payload['survey'] ?? $payload['slug'] ?? '');
            $result = surveys()->submitResponse($surveySlug, $payload);
            json_response($result, !empty($result['success']) ? 200 : 422);

        default:
            json_response(['success' => false, 'message' => 'Acción no soportada.'], 400);
    }
} catch (Throwable $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
}
