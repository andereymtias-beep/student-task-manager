
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$token = $_POST['csrf_token'] ?? '';

if (!isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Invalid request token. Return to the dashboard and try again.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid task ID.');
}

$stmt = $pdo->prepare(
    'DELETE FROM tasks WHERE id = ? AND user_id = ?'
);
$stmt->execute([$id, (int) $_SESSION['user_id']]);

$_SESSION['flash_message'] = $stmt->rowCount() > 0
    ? 'Task deleted successfully.'
    : 'Task not found.';

header('Location: dashboard.php');
exit;
