<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/assistant.php';

if (!is_post()) {
    json_response(['error' => 'Use POST.'], 405);
}
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    json_response(['error' => 'Your session expired. Please refresh the page.'], 419);
}
if (rate_limited('assistant', 20, 300)) {
    json_response(['error' => 'You are asking questions very quickly. Please wait a few minutes and try again.'], 429);
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
$question = trim((string)($body['question'] ?? ''));
if ($question === '' || mb_strlen($question) > 500) {
    json_response(['error' => 'Please type a question of up to 500 characters.'], 422);
}
$history = is_array($body['history'] ?? null) ? $body['history'] : [];

$result = Assistant::answer($question, $history);
if ($result['log_id']) {
    $_SESSION['assistant_logs'] = array_slice(array_merge($_SESSION['assistant_logs'] ?? [], [$result['log_id']]), -50);
}
json_response($result);
