
<?php
/*
 * Requires:
 *   $pdo    - existing PDO database connection
 *   $userId - authenticated user's ID
 */

if (!isset($pdo, $userId) || !($pdo instanceof PDO)) {
    throw new RuntimeException(
        'Notification synchronization requires a PDO connection and user ID.'
    );
}

$userId = (int) $userId;

if ($userId <= 0) {
    throw new RuntimeException('Invalid user ID.');
}

// Remove notifications that no longer match the task's current state.
// A read notification is preserved if its alert is still valid.
$cleanup = $pdo->prepare(
    "DELETE n
     FROM notifications n
     LEFT JOIN tasks t
       ON t.id = n.task_id
      AND t.user_id = n.user_id
     WHERE n.user_id = ?
       AND (
           t.id IS NULL
           OR t.status <> 'Pending'
           OR t.due_date IS NULL
           OR (
               n.type = 'overdue'
               AND t.due_date >= CURDATE()
           )
           OR (
               n.type = 'due_today'
               AND t.due_date <> CURDATE()
           )
           OR (
               n.type = 'due_tomorrow'
               AND t.due_date <> DATE_ADD(CURDATE(), INTERVAL 1 DAY)
           )
       )"
);

$cleanup->execute([$userId]);

// Get the user's pending tasks that are overdue, due today, or due tomorrow.
$query = $pdo->prepare(
    "SELECT id, title, due_date
     FROM tasks
     WHERE user_id = ?
       AND status = 'Pending'
       AND due_date IS NOT NULL
       AND due_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
);

$query->execute([$userId]);
$deadlineTasks = $query->fetchAll();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$save = $pdo->prepare(
    "INSERT INTO notifications
        (user_id, task_id, type, message)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        message = VALUES(message)"
);

foreach ($deadlineTasks as $task) {
    if ($task['due_date'] < $today) {
        $type = 'overdue';
        $message = 'Overdue task: ' . $task['title'];
    } elseif ($task['due_date'] === $today) {
        $type = 'due_today';
        $message = 'Due today: ' . $task['title'];
    } elseif ($task['due_date'] === $tomorrow) {
        $type = 'due_tomorrow';
        $message = 'Due tomorrow: ' . $task['title'];
    } else {
        continue;
    }

    $save->execute([
        $userId,
        (int) $task['id'],
        $type,
        $message
    ]);
}
