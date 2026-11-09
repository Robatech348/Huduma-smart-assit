<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    json_response(['error' => 'Use POST.'], 405);
}
csrf_check();
$id = input_int('id');
$helpful = input('helpful') === '1' ? 1 : 0;
// Only allow rating answers given in this session.
$allowed = $_SESSION['assistant_logs'] ?? [];
if ($id > 0 && in_array($id, $allowed, true)) {
    q('UPDATE assistant_logs SET helpful = ? WHERE id = ?', [$helpful, $id]);
}
json_response(['ok' => true]);
