
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

$errors = [];
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);

// Load the current profile.
$query = $pdo->prepare(
    'SELECT name, email, password FROM users WHERE id = ? LIMIT 1'
);
$query->execute([$userId]);
$user = $query->fetch();

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$name = $user['name'];
$email = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Name is required and must not exceed 100 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150) {
            $errors[] = 'Enter a valid email address (maximum 150 characters).';
        }

        if (!$errors) {
            $duplicate = $pdo->prepare(
                'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1'
            );
            $duplicate->execute([$email, $userId]);

            if ($duplicate->fetch()) {
                $errors[] = 'That email address is already registered.';
            } else {
                try {
                    $update = $pdo->prepare(
                        'UPDATE users SET name = ?, email = ? WHERE id = ?'
                    );
                    $update->execute([$name, $email, $userId]);

                    $_SESSION['user_name'] = $name;
                    $_SESSION['profile_success'] = 'Your profile was updated successfully.';

                    header('Location: profile.php');
                    exit;
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $errors[] = 'Unable to update your profile. Please try again.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!is_string($currentPassword) ||
            !password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Your current password is incorrect.';
        }

        if (!is_string($newPassword) || strlen($newPassword) < 8) {
            $errors[] = 'Your new password must contain at least 8 characters.';
        }

        if (!is_string($confirmPassword) ||
            $newPassword !== $confirmPassword) {
            $errors[] = 'The new passwords do not match.';
        }

        if (!$errors) {
            try {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $pdo->prepare(
                    'UPDATE users SET password = ? WHERE id = ?'
                );
                $update->execute([$newHash, $userId]);

                $_SESSION['profile_success'] =
                    'Your password was changed successfully.';

                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Unable to change your password. Please try again.';
            }
        }
    } else {
        $errors[] = 'Invalid request.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile & Settings | StudyFlow</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">
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
            <a href="notifications.php" class="sidebar-link">
                <i class="bi bi-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="profile.php" class="sidebar-link active">
                <i class="bi bi-person-gear"></i>
                <span>Profile & Settings</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-profile">
                <div class="profile-avatar">
                    <?= e(strtoupper(substr($user['name'], 0, 1))) ?>
                </div>
                <div class="profile-details">
                    <strong><?= e($user['name']) ?></strong>
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
                <div class="topbar-title">Profile & Settings</div>
            </div>
            <a href="dashboard.php" class="secondary-action">
                ← Back to Dashboard
            </a>
        </header>

        <div class="app-content">
            <section class="welcome-section">
                <div>
                    <span class="welcome-label">ACCOUNT MANAGEMENT</span>
                    <h1>Your Profile</h1>
                    <p>Manage your personal information and account security.</p>
                </div>
            </section>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status">
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="profile-settings-grid">
                <section class="profile-settings-card">
                    <div class="settings-card-heading">
                        <i class="bi bi-person-circle"></i>
                        <div>
                            <h2>Personal Information</h2>
                            <p>Update the name and email associated with your account.</p>
                        </div>
                    </div>

                    <form method="POST" action="profile.php">
                        <input type="hidden" name="csrf_token"
                               value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" name="name"
                                   class="form-control" maxlength="100"
                                   autocomplete="name" required
                                   value="<?= e($name) ?>">
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email"
                                   class="form-control" maxlength="150"
                                   autocomplete="email" required
                                   value="<?= e($email) ?>">
                        </div>

                        <button type="submit" class="primary-action">
                            Save Changes
                        </button>
                    </form>
                </section>

                <section class="profile-settings-card">
                    <div class="settings-card-heading">
                        <i class="bi bi-shield-lock"></i>
                        <div>
                            <h2>Change Password</h2>
                            <p>Choose a strong password to protect your account.</p>
                        </div>
                    </div>

                    <form method="POST" action="profile.php">
                        <input type="hidden" name="csrf_token"
                               value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">
                                Current Password
                            </label>
                            <input type="password" id="current_password"
                                   name="current_password" class="form-control"
                                   autocomplete="current-password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">
                                New Password
                            </label>
                            <input type="password" id="new_password"
                                   name="new_password" class="form-control"
                                   minlength="8" autocomplete="new-password"
                                   required>
                            <div class="form-text">At least 8 characters.</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">
                                Confirm New Password
                            </label>
                            <input type="password" id="confirm_password"
                                   name="confirm_password" class="form-control"
                                   minlength="8" autocomplete="new-password"
                                   required>
                        </div>

                        <button type="submit" class="primary-action">
                            Update Password
                        </button>
                    </form>
                </section>
            </div>

            <footer class="app-footer">
                Student Task Manager · Stay focused, stay organized.
            </footer>
        </div>
    </main>
</div>
</body>
</html>
