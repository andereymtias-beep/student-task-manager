
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Student';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Dashboard statistics for the logged-in user
$statsQuery = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'Completed'), 0) AS completed,
        COALESCE(SUM(
            status = 'Pending'
            AND due_date IS NOT NULL
            AND due_date < CURDATE()
        ), 0) AS overdue
     FROM tasks
     WHERE user_id = ?"
);
$statsQuery->execute([$userId]);
$stats = $statsQuery->fetch();

// Progress calculations based on all tasks belonging to this user.
$totalTasks = (int) ($stats['total'] ?? 0);
$completedTasks = (int) ($stats['completed'] ?? 0);
$completionPercentage = $totalTasks > 0
    ? (int) round(($completedTasks / $totalTasks) * 100)
    : 0;

// Search and filters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';

$sql = 'SELECT * FROM tasks WHERE user_id = ?';
$params = [$userId];

if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR subject LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (in_array($status, ['Pending', 'Completed'], true)) {
    $sql .= ' AND status = ?';
    $params[] = $status;
}

if (in_array($priority, ['Low', 'Medium', 'High'], true)) {
    $sql .= ' AND priority = ?';
    $params[] = $priority;
}

$sql .= " ORDER BY
    CASE WHEN status = 'Pending'
        AND due_date < CURDATE() THEN 0 ELSE 1 END,
    due_date IS NULL,
    due_date ASC,
    id DESC";

$taskQuery = $pdo->prepare($sql);
$taskQuery->execute($params);
$tasks = $taskQuery->fetchAll();


require_once __DIR__ . '/sync-notifications.php';
require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];

$deadlineNotificationQuery = $pdo->prepare(
    "SELECT id, title, due_date
     FROM tasks
     WHERE user_id = ?
       AND status = 'Pending'
       AND due_date IS NOT NULL
       AND due_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
);

$deadlineNotificationQuery->execute([$userId]);
$deadlineNotificationTasks = $deadlineNotificationQuery->fetchAll();

$saveNotification = $pdo->prepare(
    "INSERT INTO notifications (user_id, task_id, type, message)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE message = VALUES(message)"
);

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

foreach ($deadlineNotificationTasks as $notificationTask) {
    if ($notificationTask['due_date'] < $today) {
        $notificationType = 'overdue';
        $notificationMessage = 'Overdue task: ' . $notificationTask['title'];
    } elseif ($notificationTask['due_date'] === $today) {
        $notificationType = 'due_today';
        $notificationMessage = 'Due today: ' . $notificationTask['title'];
    } elseif ($notificationTask['due_date'] === $tomorrow) {
        $notificationType = 'due_tomorrow';
        $notificationMessage = 'Due tomorrow: ' . $notificationTask['title'];
    } else {
        continue;
    }

    $saveNotification->execute([
        $userId,
        (int) $notificationTask['id'],
        $notificationType,
        $notificationMessage
    ]);
}


$deadlineQuery = $pdo->prepare(
    "SELECT id, title, subject, due_date, priority
     FROM tasks
     WHERE user_id = ?
       AND status = 'Pending'
       AND due_date IS NOT NULL
       AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY due_date ASC
     LIMIT 5"
);

$deadlineQuery->execute([$userId]);
$upcomingDeadlines = $deadlineQuery->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard | Student Task Manager</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="app-body">

<div class="app-layout">

    <aside class="app-sidebar">
        <a href="dashboard.php" class="sidebar-brand">
            <span class="brand-icon">S</span>
            <span>StudyFlow</span>
        </a>

        <div class="sidebar-label">WORKSPACE</div>


<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<a href="dashboard.php"
   class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
    <i class="bi bi-grid-1x2"></i>
    <span>Dashboard</span>
</a>

<a href="calendar.php"
   class="sidebar-link <?= $currentPage === 'calendar.php' ? 'active' : '' ?>">
    <i class="bi bi-calendar3"></i>
    <span>Calendar</span>
</a>

<a href="add.php"
   class="sidebar-link <?= $currentPage === 'add.php' ? 'active' : '' ?>">
    <i class="bi bi-plus-circle"></i>
    <span>Add New Task</span>
</a>

        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-profile">
                <div class="profile-avatar">
                    <?= e(strtoupper(substr($userName, 0, 1))) ?>
                </div>

                <div class="profile-details">
                    <strong><?= e($userName) ?></strong>
                    <small>Student Account</small>
                </div>
            </div>

            <form action="../logout.php" method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">

                <button type="submit" class="sidebar-logout">
                    ⇥ <span>Log Out</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="app-main">


<?php
$unreadQuery = $pdo->prepare(
    "SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0"
);
$unreadQuery->execute([$userId]);
$unreadCount = (int) $unreadQuery->fetchColumn();
?>

<a href="notifications.php" class="notification-bell"
   aria-label="Notifications">
    <span aria-hidden="true">♧</span>
    <?php if ($unreadCount > 0): ?>
        <span class="notification-count">
            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
        </span>
    <?php endif; ?>
</a>


        <header class="app-topbar">
            <div>
                <span class="topbar-eyebrow">STUDENT WORKSPACE</span>
                <div class="topbar-title">My Dashboard</div>
            </div>

            <div class="topbar-user">
                <div class="profile-avatar small-avatar">
                    <?= e(strtoupper(substr($userName, 0, 1))) ?>
                </div>
                <span><?= e($userName) ?></span>
            </div>
        </header>

        <div class="app-content">

            <section class="welcome-section">
                <div>
                    <span class="welcome-label">YOUR PERSONAL PLANNER</span>
                    <h1>Welcome back, <?= e($userName) ?>!</h1>
                    <p>Stay organized, meet your deadlines, and make progress every day.</p>
                </div>

                <a href="add.php" class="primary-action">
                    <span>＋</span> Create New Task
                </a>
            </section>

<section class="deadline-panel">
    <div class="deadline-heading">
        <div>
            <h2>Upcoming Deadlines</h2>
            <p>Keep track of assignments that need your attention.</p>
        </div>
        <span class="deadline-count">
            <?= count($upcomingDeadlines) ?> tasks
        </span>
    </div>

    <?php if (empty($upcomingDeadlines)): ?>
        <div class="deadline-empty">
            You're all caught up! No deadlines in the next 7 days.
        </div>
    <?php else: ?>
        <div class="deadline-list">
            <?php foreach ($upcomingDeadlines as $deadline): ?>
                <?php
                $isPastDue = $deadline['due_date'] < date('Y-m-d');
                $isDueToday = $deadline['due_date'] === date('Y-m-d');
                ?>
                <a class="deadline-item"
                   href="edit.php?id=<?= (int) $deadline['id'] ?>">
                    <div class="deadline-task-info">
                        <strong><?= e($deadline['title']) ?></strong>
                        <span><?= e($deadline['subject'] ?: 'General task') ?></span>
                    </div>

                    <div class="deadline-meta">
                        <span class="deadline-priority priority-<?= strtolower(e($deadline['priority'])) ?>">
                            <?= e($deadline['priority']) ?>
                        </span>

                        <span class="<?= $isPastDue ? 'deadline-overdue' : 'deadline-date' ?>">
                            <?php if ($isPastDue): ?>
                                Overdue ·
                            <?php elseif ($isDueToday): ?>
                                Due today ·
                            <?php endif; ?>
                            <?= e($deadline['due_date']) ?>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show"
                     role="alert">
                    <?= e($_SESSION['flash_message']) ?>
                    <button type="button" class="btn-close"
                            data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <section class="dashboard-stats">

                <article class="dashboard-stat">
                    <div class="stat-top">
                        <span>Total Tasks</span>
                        <span class="stat-symbol symbol-blue">▤</span>
                    </div>
                    <div class="stat-number"><?= (int) $stats['total'] ?></div>
                    <div class="stat-description">All your assignments</div>
                </article>

                <article class="dashboard-stat">
                    <div class="stat-top">
                        <span>Pending</span>
                        <span class="stat-symbol symbol-amber">◷</span>
                    </div>
                    <div class="stat-number"><?= (int) $stats['pending'] ?></div>
                    <div class="stat-description">Tasks to work on</div>
                </article>

                <article class="dashboard-stat">
                    <div class="stat-top">
                        <span>Completed</span>
                        <span class="stat-symbol symbol-green">✓</span>
                    </div>
                    <div class="stat-number"><?= (int) $stats['completed'] ?></div>
                    <div class="stat-description">Successfully finished</div>
                </article>

                <article class="dashboard-stat">
                    <div class="stat-top">
                        <span>Overdue</span>
                        <span class="stat-symbol symbol-red">!</span>
                    </div>
                    <div class="stat-number"><?= (int) $stats['overdue'] ?></div>
                    <div class="stat-description">Past-due pending tasks</div>
                </article>

            </section>

<section class="progress-panel">
    <div class="progress-panel-heading">
        <div>
            <h2>My Progress</h2>
            <p>Track how much of your workload you've completed.</p>
        </div>

        <div class="progress-percentage">
            <?= $completionPercentage ?>%
        </div>
    </div>

    <div
        class="progress-track"
        role="progressbar"
        aria-label="Task completion progress"
        aria-valuenow="<?= $completionPercentage ?>"
        aria-valuemin="0"
        aria-valuemax="100"
    >
        <div
            class="progress-fill"
            style="width: <?= $completionPercentage ?>%;"
        ></div>
    </div>


<div class="col-md-6 col-xl-4">
    <div class="card h-100 shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-3 bg-primary-subtle text-primary p-3">
                    <i class="bi bi-bar-chart-line fs-4"></i>
                </div>
                <div>
                    <h5 class="card-title mb-1">Productivity Analytics</h5>
                    <p class="text-muted small mb-0">
                        Track your progress and completed tasks.
                    </p>
                </div>
            </div>

            <a href="analytics.php" class="btn btn-outline-primary">
                View Analytics
                <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>


    <div class="progress-footer">
        <span>
            <?= $completedTasks ?> of <?= $totalTasks ?> tasks completed
        </span>
        <span>
            <?= $totalTasks - $completedTasks ?> remaining
        </span>
    </div>
</section>

            <section class="modern-task-panel">

                <div class="panel-heading">
                    <div>
                        <h2>My Assignments</h2>
                        <p>Manage your tasks and upcoming deadlines.</p>
                    </div>

                    <a href="calendar.php" class="secondary-action">
                        View Calendar →
                    </a>
                </div>

                <form method="GET" action="dashboard.php"
                      class="modern-filter-form">

                    <div class="filter-search">
                        <label for="search">Search assignments</label>
                        <input type="search" id="search" name="search"
                               class="form-control"
                               value="<?= e($search) ?>"
                               placeholder="Search title or subject">
                    </div>

                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All statuses</option>
                            <option value="Pending"
                                <?= $status === 'Pending' ? 'selected' : '' ?>>
                                Pending
                            </option>
                            <option value="Completed"
                                <?= $status === 'Completed' ? 'selected' : '' ?>>
                                Completed
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-select">
                            <option value="">All priorities</option>
                            <?php foreach (['Low', 'Medium', 'High'] as $p): ?>
                                <option value="<?= e($p) ?>"
                                    <?= $priority === $p ? 'selected' : '' ?>>
                                    <?= e($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="primary-action">Filter</button>
                        <a href="dashboard.php" class="reset-action">Reset</a>
                    </div>
                </form>

                <?php if (!$tasks): ?>

                    <div class="modern-empty-state">
                        <div class="empty-state-icon">▤</div>
                        <h3>No assignments found</h3>
                        <p>Add a task or adjust your search filters.</p>
                        <a href="add.php" class="primary-action">
                            Create Your First Task
                        </a>
                    </div>

                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table modern-task-table align-middle">
                            <thead>
                                <tr>
                                    <th>ASSIGNMENT</th>
                                    <th>DUE DATE</th>
                                    <th>PRIORITY</th>
                                    <th>STATUS</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                $isOverdue = $task['status'] === 'Pending'
                                    && $task['due_date'] !== null
                                    && $task['due_date'] < date('Y-m-d');

                                $priorityClass = [
                                    'Low' => 'priority-low',
                                    'Medium' => 'priority-medium',
                                    'High' => 'priority-high'
                                ][$task['priority']] ?? 'priority-medium';
                                ?>

                                <tr>
                                    <td>
                                        <div class="assignment-title">
                                            <?= e($task['title']) ?>
                                        </div>

                                        <div class="assignment-subject">
                                            <?= e($task['subject'] ?: 'General task') ?>
                                        </div>

                                        <?php if ($isOverdue): ?>
                                            <span class="overdue-label">Overdue</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="<?= $isOverdue ? 'date-overdue' : 'date-normal' ?>">
                                            <?= $task['due_date']
                                                ? e($task['due_date'])
                                                : 'No deadline' ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="modern-priority <?= e($priorityClass) ?>">
                                            <?= e($task['priority']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="modern-status <?= $task['status'] === 'Completed'
                                            ? 'status-completed'
                                            : 'status-pending' ?>">
                                            <?= e($task['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="modern-actions">
                                            <a class="action-link"
                                               href="edit.php?id=<?= (int) $task['id'] ?>">
                                                Edit
                                            </a>

                                            <form action="toggle.php" method="POST">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= e($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id"
                                                       value="<?= (int) $task['id'] ?>">

                                                <button class="action-link action-complete"
                                                        type="submit">
                                                    <?= $task['status'] === 'Completed'
                                                        ? 'Reopen'
                                                        : 'Complete' ?>
                                                </button>
                                            </form>

                                            <form action="delete.php" method="POST"
                                                  class="delete-form">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= e($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id"
                                                       value="<?= (int) $task['id'] ?>">

                                                <button class="action-link action-delete"
                                                        type="submit">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </section>





            <footer class="app-footer">
                Student Task Manager · Stay focused, stay organized.
            </footer>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
