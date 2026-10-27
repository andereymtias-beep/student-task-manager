
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Mark one notification as read.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);

    if ($notificationId) {
        $update = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = ? AND user_id = ?'
        );
        $update->execute([$notificationId, $userId]);
    }

    header('Location: notifications.php');
    exit;
}

// Retrieve only this student's notifications.
$query = $pdo->prepare(
    'SELECT n.id, n.task_id, n.type, n.message, n.is_read,
            n.created_at, t.due_date
     FROM notifications n
     LEFT JOIN tasks t ON t.id = n.task_id
     WHERE n.user_id = ?
     ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC'
);
$query->execute([$userId]);
$notifications = $query->fetchAll();

$unreadCount = 0;
foreach ($notifications as $notification) {
    if (!(int) $notification['is_read']) {
        $unreadCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications | StudyFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .notifications-page {
            max-width: 900px;
            margin: 0 auto;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .notifications-header h1 {
            font-size: 28px;
            font-weight: 750;
            color: #172033;
        }

        .notifications-header p {
            color: #718096;
            margin: 6px 0 0;
        }

        .notification-card {
            background: #fff;
            border: 1px solid #e8edf3;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .notification-card.unread {
            border-left: 4px solid #2563eb;
            background: #f8fbff;
        }

        .notification-message {
            font-weight: 650;
            color: #172033;
            overflow-wrap: anywhere;
        }

        .notification-detail {
            color: #718096;
            font-size: 13px;
            margin-top: 6px;
        }

        .notification-empty {
            text-align: center;
            padding: 48px 20px;
            background: #fff;
            border: 1px solid #e8edf3;
            border-radius: 16px;
            color: #718096;
        }

        @media (max-width: 600px) {
            .notification-card {
                align-items: flex-start;
                flex-direction: column;
            }

            .notifications-header h1 {
                font-size: 23px;
            }
        }
    </style>
</head>
<body class="app-body">
<div class="app-layout">
    <aside class="app-sidebar">
        <a href="dashboard.php" class="sidebar-brand">
            <span class="brand-icon">S</span>
            <span>StudyFlow</span>
        </a>

        <div class="sidebar-label">WORKSPACE</div>
        <nav>
            <a href="dashboard.php" class="sidebar-link">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
            <a href="calendar.php" class="sidebar-link">
                <i class="bi bi-calendar3"></i>
                <span>Calendar</span>
            </a>
            <a href="add.php" class="sidebar-link">
                <i class="bi bi-plus-circle"></i>
                <span>Add New Task</span>
            </a>
            <a href="notifications.php" class="sidebar-link active">
                <i class="bi bi-bell"></i>
                <span>Notifications</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-profile">
                <div class="profile-avatar">
                    <?= e(strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1))) ?>
                </div>
                <div class="profile-details">
                    <strong><?= e($_SESSION['user_name'] ?? 'Student') ?></strong>
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
        <header class="app-topbar">
            <div>
                <span class="topbar-eyebrow">STUDENT WORKSPACE</span>
                <div class="topbar-title">Notifications</div>
            </div>
            <a href="dashboard.php" class="secondary-action">
                ← Back to Dashboard
            </a>
        </header>

        <div class="app-content">
            <div class="notifications-page">
                <div class="notifications-header">
                    <div>
                        <h1>Notifications</h1>
                        <p>
                            You have <?= $unreadCount ?> unread
                            notification<?= $unreadCount === 1 ? '' : 's' ?>.
                        </p>
                    </div>
                </div>

                <?php if (!$notifications): ?>
                    <div class="notification-empty">
                        <h2>You're all caught up!</h2>
                        <p>No notifications yet. Deadline alerts will appear here.</p>
                        <a href="dashboard.php" class="primary-action">
                            Return to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <article class="notification-card <?= !(int) $notification['is_read'] ? 'unread' : '' ?>">
                            <div>
                                <div class="notification-message">
                                    <?= e($notification['message']) ?>
                                </div>
                                <div class="notification-detail">
                                    <?php
                                    $typeLabels = [
                                        'overdue' => 'Overdue task',
                                        'due_today' => 'Due today',
                                        'due_tomorrow' => 'Due tomorrow'
                                    ];
                                    echo e($typeLabels[$notification['type']] ?? 'Task reminder');
                                    ?>
                                    <?php if (!empty($notification['due_date'])): ?>
                                        · Deadline: <?= e($notification['due_date']) ?>
                                    <?php endif; ?>
                                    · <?= e($notification['created_at']) ?>
                                </div>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (!empty($notification['task_id']) && !empty($notification['due_date'])): ?>
                                    <a class="btn btn-outline-primary btn-sm"
                                       href="edit.php?id=<?= (int) $notification['task_id'] ?>">
                                        View Task
                                    </a>
                                <?php endif; ?>

                                <?php if (!(int) $notification['is_read']): ?>
                                    <form method="POST" action="notifications.php">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="notification_id"
                                               value="<?= (int) $notification['id'] ?>">
                                        <button type="submit"
                                                class="btn btn-primary btn-sm">
                                            Mark as Read
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge text-bg-light">Read</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
