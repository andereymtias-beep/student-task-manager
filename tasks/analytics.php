
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];

// Total task statistics
$statsQuery = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Completed'), 0) AS completed,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(
            status = 'Pending'
            AND due_date IS NOT NULL
            AND due_date < CURDATE()
        ), 0) AS overdue
    FROM tasks
    WHERE user_id = ?
");
$statsQuery->execute([$userId]);
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

$total = (int) $stats['total'];
$completed = (int) $stats['completed'];
$pending = (int) $stats['pending'];
$overdue = (int) $stats['overdue'];

$completionRate = $total > 0
    ? round(($completed / $total) * 100)
    : 0;

// Task priority distribution
$priorityQuery = $pdo->prepare("
    SELECT priority, COUNT(*) AS total
    FROM tasks
    WHERE user_id = ?
    GROUP BY priority
");
$priorityQuery->execute([$userId]);

$priorityCounts = [
    'Low' => 0,
    'Medium' => 0,
    'High' => 0
];

foreach ($priorityQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $priorityCounts[$row['priority']] = (int) $row['total'];
}


/*
 * Tasks actually completed during the last 7 calendar days,
 * based on completed_at rather than created_at.
 */
$weeklyQuery = $pdo->prepare("
    SELECT DATE(completed_at) AS task_day, COUNT(*) AS total
    FROM tasks
    WHERE user_id = ?
      AND status = 'Completed'
      AND completed_at IS NOT NULL
      AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND completed_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(completed_at)
    ORDER BY task_day ASC
");
$weeklyQuery->execute([$userId]);

$weeklyCounts = [];

foreach ($weeklyQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $weeklyCounts[$row['task_day']] = (int) $row['total'];
}

// Generate labels and values for today and the previous six days.
$weeklyLabels = [];
$weeklyData = [];

for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));

    $weeklyLabels[] = date('D', strtotime($day));
    $weeklyData[] = $weeklyCounts[$day] ?? 0;
}

$chartWeekly = [
    'labels' => $weeklyLabels,
    'data' => $weeklyData
];


function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$currentPage = basename($_SERVER['PHP_SELF']);
$userName = $_SESSION['user_name'] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productivity Analytics | StudyFlow</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .analytics-content {
            padding: 28px;
        }

        .analytics-stat,
        .analytics-card {
            background: #fff;
            border: 1px solid #e9edf3;
            border-radius: 16px;
            padding: 22px;
            height: 100%;
        }

        .analytics-stat .stat-value {
            font-size: 30px;
            font-weight: 700;
            margin: 10px 0 4px;
        }

        .analytics-stat .stat-label {
            color: #687386;
            font-size: 14px;
        }

        .analytics-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 21px;
        }

        .analytics-card h5 {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .analytics-card p {
            font-size: 13px;
            color: #687386;
        }

        .chart-container {
            position: relative;
            height: 280px;
        }

        .analytics-progress {
            height: 9px;
            border-radius: 10px;
            background: #edf0f5;
            overflow: hidden;
        }

        .analytics-progress-bar {
            height: 100%;
            background: #4f46e5;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .analytics-content {
                padding: 16px;
            }

            .chart-container {
                height: 240px;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-journal-check"></i>
            <span>StudyFlow</span>
        </div>

        <div class="sidebar-section">MENU</div>

        <a href="dashboard.php"
           class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i>
            <span>Dashboard</span>
        </a>

        <a href="add.php" class="sidebar-link">
            <i class="bi bi-plus-circle"></i>
            <span>Add Task</span>
        </a>

        <a href="calendar.php"
           class="sidebar-link <?= $currentPage === 'calendar.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar3"></i>
            <span>Calendar</span>
        </a>

        <a href="notifications.php"
           class="sidebar-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
            <i class="bi bi-bell"></i>
            <span>Notifications</span>
        </a>

        <a href="analytics.php"
           class="sidebar-link <?= $currentPage === 'analytics.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart"></i>
            <span>Analytics</span>
        </a>

        <div class="sidebar-section">ACCOUNT</div>

        <a href="profile.php"
           class="sidebar-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person"></i>
            <span>Profile & Settings</span>
        </a>

        <form action="../logout.php" method="POST" class="mt-3">
            <?php if (!empty($_SESSION['csrf_token'])): ?>
                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">
            <?php endif; ?>

            <button type="submit" class="sidebar-link border-0 bg-transparent w-100 text-start">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </button>
        </form>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h4 class="mb-1">Productivity Analytics</h4>
                <small class="text-muted">
                    Track your academic progress and task completion.
                </small>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="text-end">
                    <div class="fw-semibold"><?= e($userName) ?></div>
                    <small class="text-muted">Student</small>
                </div>
                <div class="rounded-circle bg-light p-2">
                    <i class="bi bi-person-circle fs-4"></i>
                </div>
            </div>
        </header>

        <section class="analytics-content">

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="analytics-stat">
                        <div class="analytics-icon">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <div class="stat-value"><?= $total ?></div>
                        <div class="stat-label">Total Tasks</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="analytics-stat">
                        <div class="analytics-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-value"><?= $completed ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="analytics-stat">
                        <div class="analytics-icon">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-value"><?= $pending ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="analytics-stat">
                        <div class="analytics-icon">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                        <div class="stat-value"><?= $overdue ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                </div>

            </div>

            <div class="analytics-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5>Overall Completion Rate</h5>
                        <p class="mb-0">Your completed tasks compared with all tasks.</p>
                    </div>
                    <strong class="fs-3"><?= $completionRate ?>%</strong>
                </div>

                <div class="analytics-progress">
                    <div class="analytics-progress-bar"
                         style="width: <?= $completionRate ?>%"></div>
                </div>
            </div>

            <div class="row g-3 mb-4">

                <div class="col-lg-6">
                    <div class="analytics-card">
                        <h5>Task Status</h5>
                        <p>Compare completed tasks with unfinished tasks.</p>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="analytics-card">
                        <h5>Priority Distribution</h5>
                        <p>See how your tasks are distributed by priority.</p>
                        <div class="chart-container">
                            <canvas id="priorityChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="analytics-card">
                        <h5>Weekly Productivity</h5>
                        <p>
                            Tasks marked completed and originally created
                            during each of the last seven days.
                        </p>
                        <div class="chart-container">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>

            <div class="text-muted small">
                Analytics are based on your account's task records.
            </div>

        </section>
    </main>
</div>

<script>
const statusData = <?= json_encode(
    $chartStatus,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const priorityData = <?= json_encode(
    $chartPriority,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const weeklyData = <?= json_encode(
    $chartWeekly,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusData.labels,
        datasets: [{
            data: statusData.data,
            backgroundColor: ['#16a34a', '#f59e0b'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

new Chart(document.getElementById('priorityChart'), {
    type: 'bar',
    data: {
        labels: priorityData.labels,
        datasets: [{
            label: 'Tasks',
            data: priorityData.data,
            backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        },
        plugins: {
            legend: { display: false }
        }
    }
});

new Chart(document.getElementById('weeklyChart'), {
    type: 'line',
    data: {
        labels: weeklyData.labels,
        datasets: [{
            label: 'Completed tasks',
            data: weeklyData.data,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});
</script>

</body>
</html>
