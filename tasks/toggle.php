
<?php
session_start();

/*
 * StudyFlow task completion toggle.
 * Requires an authenticated user, valid CSRF token,
 * and ownership of the task being updated.
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Validate CSRF token.
$sessionToken = $_SESSION['csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !is_string($submittedToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid security token. Please return to the dashboard and try again.');
}

// Validate task ID.
$taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
$userId = (int) $_SESSION['user_id'];

if (!$taskId || $taskId < 1) {
    header('Location: dashboard.php');
    exit;
}

try {
    // Fetch only a task owned by the authenticated user.
    $select = $pdo->prepare(
        "SELECT id, status
         FROM tasks
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );
    $select->execute([$taskId, $userId]);
    $task = $select->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        exit('Task not found.');
    }

    if ($task['status'] === 'Completed') {
        // Reopening a task clears its completion timestamp.
        $update = $pdo->prepare(
            "UPDATE tasks
             SET status = 'Pending',
                 completed_at = NULL
             WHERE id = ? AND user_id = ?"
        );
    } else {
        // Completing a task records the current date and time.
        $update = $pdo->prepare(
            "UPDATE tasks
             SET status = 'Completed',
                 completed_at = NOW()
             WHERE id = ? AND user_id = ?"
        );
    }

    $update->execute([$taskId, $userId]);

    header('Location: dashboard.php');
    exit;

} catch (PDOException $exception) {
    error_log('StudyFlow task toggle failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to update the task right now. Please try again later.');
}
