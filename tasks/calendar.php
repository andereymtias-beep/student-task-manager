
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function calendar_e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Student';
$today = new DateTimeImmutable('today');

// Validate the requested month, using YYYY-MM.
$requestedMonth = $_GET['month'] ?? date('Y-m');

if (!is_string($requestedMonth) ||
    !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth)) {
    $requestedMonth = date('Y-m');
}

$monthDate = DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $requestedMonth . '-01'
);

if (!$monthDate || $monthDate->format('Y-m') !== $requestedMonth) {
    $monthDate = new DateTimeImmutable('first day of this month');
}

$monthStart = $monthDate->modify('first day of this month');
$monthEnd = $monthDate->modify('last day of this month');

$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$currentMonth = date('Y-m');

// Fetch tasks with a deadline in the selected month.
// Each query is restricted to the logged-in user's account.
$stmt = $pdo->prepare(
    "SELECT id, title, subject, due_date, priority, status
     FROM tasks
     WHERE user_id = ?
       AND due_date >= ?
       AND due_date <= ?
     ORDER BY due_date ASC, priority DESC, title ASC"
);

$stmt->execute([
    $userId,
    $monthStart->format('Y-m-d'),
    $monthEnd->format('Y-m-d')
]);

$tasksByDate = [];

foreach ($stmt->fetchAll() as $task) {
    $tasksByDate[$task['due_date']][] = $task;
}

// Fetch tasks that have no deadline.
$undatedStmt = $pdo->prepare(
    "SELECT id, title, subject, priority, status
     FROM tasks
     WHERE user_id = ?
       AND due_date IS NULL
     ORDER BY created_at DESC"
);

$undatedStmt->execute([$userId]);
$undatedTasks = $undatedStmt->fetchAll();

// Monday-first calendar grid.
$daysInMonth = (int) $monthStart->format('t');
$startWeekday = (int) $monthStart->format('N');
$totalCells = (int) (ceil(($startWeekday + $daysInMonth - 1) / 7) * 7);
$cellDate = $monthStart->modify('-' . ($startWeekday - 1) . ' days');

$monthTaskCount = 0;
foreach ($tasksByDate as $dayTasks) {
    $monthTaskCount += count($dayTasks);
}

$pendingCount = 0;
$completedCount = 0;
$overdueCount = 0;

foreach ($tasksByDate as $dayTasks) {
    foreach ($dayTasks as $task) {
        if ($task['status'] === 'Completed') {
            $completedCount++;
        } else {
            $pendingCount++;

            if ($task['due_date'] < $today->format('Y-m-d')) {
                $overdueCount++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Calendar | Student Task Manager</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="calendar-page">

<nav class="navbar bg-white border-bottom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary"
           href="dashboard.php">
            Student Task Manager
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php"
               class="btn btn-outline-primary btn-sm">
                Dashboard
            </a>

            <a href="add.php" class="btn btn-primary btn-sm">
                + Add Task
            </a>

            <form action="../logout.php" method="POST" class="m-0">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= calendar_e($_SESSION['csrf_token'] ?? '') ?>"
                >
                <button type="submit"
                        class="btn btn-outline-danger btn-sm">
                    Logout
                </button>
            </form>
        </div>
    </div>
</nav>

<main class="container py-4 py-md-5">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Assignment Calendar</h1>
            <p class="text-secondary mb-0">
                Welcome, <?= calendar_e($userName) ?>.
                Keep track of your deadlines.
            </p>
        </div>

        <a href="add.php" class="btn btn-primary">
            Create New Task
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="calendar-stat">
                <span>Tasks this month</span>
                <strong><?= $monthTaskCount ?></strong>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="calendar-stat">
                <span>Pending</span>
                <strong><?= $pendingCount ?></strong>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="calendar-stat">
                <span>Completed</span>
                <strong><?= $completedCount ?></strong>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="calendar-stat">
                <span>Overdue</span>
                <strong><?= $overdueCount ?></strong>
            </div>
        </div>
    </div>

    <section class="calendar-panel">

        <div class="calendar-toolbar">
            <div>
                <h2 class="h4 fw-bold mb-1">
                    <?= calendar_e($monthStart->format('F Y')) ?>
                </h2>
                <p class="text-secondary small mb-0">
                    Select a task to edit its details.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary btn-sm"
                   href="?month=<?= calendar_e($previousMonth) ?>"
                   aria-label="Previous month">
                    &larr; Previous
                </a>

                <a class="btn btn-outline-primary btn-sm"
                   href="?month=<?= calendar_e($currentMonth) ?>">
                    Today
                </a>

                <a class="btn btn-outline-secondary btn-sm"
                   href="?month=<?= calendar_e($nextMonth) ?>"
                   aria-label="Next month">
                    Next &rarr;
                </a>
            </div>
        </div>

        <div class="calendar-legend">
            <span><i class="legend-dot dot-pending"></i> Pending</span>
            <span><i class="legend-dot dot-completed"></i> Completed</span>
            <span><i class="legend-dot dot-overdue"></i> Overdue</span>
        </div>

        <div class="calendar-grid">
            <?php foreach (
                ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                as $weekday
            ): ?>
                <div class="calendar-weekday">
                    <?= $weekday ?>
                </div>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $totalCells; $i++): ?>
                <?php
                $date = $cellDate->modify('+' . $i . ' days');
                $dateKey = $date->format('Y-m-d');
                $inMonth = $date->format('Y-m') === $monthStart->format('Y-m');
                $isToday = $dateKey === $today->format('Y-m-d');
                $dayTasks = $tasksByDate[$dateKey] ?? [];
                ?>

                <div class="calendar-day
                    <?= !$inMonth ? 'outside-month' : '' ?>
                    <?= $isToday ? 'is-today' : '' ?>">

                    <div class="calendar-day-number">
                        <span><?= $date->format('j') ?></span>

                        <?php if ($isToday): ?>
                            <span class="today-label">Today</span>
                        <?php endif; ?>
                    </div>

                    <div class="calendar-day-tasks">
                        <?php foreach ($dayTasks as $task): ?>
                            <?php
                            $taskClass = 'task-pending';

                            if ($task['status'] === 'Completed') {
                                $taskClass = 'task-completed';
                            } elseif ($dateKey < $today->format('Y-m-d')) {
                                $taskClass = 'task-overdue';
                            }
                            ?>

                            <a
                                class="calendar-task <?= $taskClass ?>"
                                href="edit.php?id=<?= (int) $task['id'] ?>"
                                title="<?= calendar_e($task['title']) ?>"
                            >
                                <span class="calendar-task-title">
                                    <?= calendar_e($task['title']) ?>
                                </span>

                                <span class="calendar-task-priority">
                                    <?= calendar_e($task['priority']) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <?php if ($monthTaskCount === 0): ?>
            <div class="calendar-empty">
                No tasks with deadlines this month.
                <a href="add.php">Add a task</a> to get started.
            </div>
        <?php endif; ?>

    </section>

    <section class="calendar-undated mt-4">
        <h2 class="h5 fw-bold mb-3">Tasks without deadlines</h2>

        <?php if (!$undatedTasks): ?>
            <p class="text-secondary mb-0">
                All your tasks have a deadline, or there are no tasks yet.
            </p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($undatedTasks as $task): ?>
                    <a
                        href="edit.php?id=<?= (int) $task['id'] ?>"
                        class="list-group-item list-group-item-action
                               d-flex justify-content-between align-items-center gap-3"
                    >
                        <span>
                            <span class="d-block fw-semibold">
                                <?= calendar_e($task['title']) ?>
                            </span>
                            <small class="text-secondary">
                                <?= calendar_e($task['subject'] ?: 'No subject') ?>
                            </small>
                        </span>

                        <span class="badge <?= $task['status'] === 'Completed'
                            ? 'text-bg-success' : 'text-bg-warning' ?>">
                            <?= calendar_e($task['status']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>
</body>
</html>
