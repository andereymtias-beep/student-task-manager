
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

$errors = [];
$title = '';
$description = '';
$subject = '';
$due_date = '';
$priority = 'Medium';

function e_add($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid request. Refresh the page and try again.';
    }

    if ($title === '' || strlen($title) > 200) {
        $errors[] = 'Task title is required and must not exceed 200 characters.';
    }

    if (strlen($description) > 10000) {
        $errors[] = 'Description is too long.';
    }

    if (strlen($subject) > 100) {
        $errors[] = 'Subject must not exceed 100 characters.';
    }

    if (!in_array($priority, ['Low', 'Medium', 'High'], true)) {
        $errors[] = 'Select a valid priority.';
    }

    if ($due_date !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $due_date);
        if (!$date || $date->format('Y-m-d') !== $due_date) {
            $errors[] = 'Enter a valid due date.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO tasks
                (user_id, title, description, subject, due_date, priority)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            (int) $_SESSION['user_id'],
            $title,
            $description !== '' ? $description : null,
            $subject !== '' ? $subject : null,
            $due_date !== '' ? $due_date : null,
            $priority
        ]);

        $_SESSION['flash_message'] = 'Task added successfully.';
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Task | Student Task Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main class="container py-5">
    <div class="auth-card mx-auto" style="max-width: 650px;">
        <h1 class="h3 mb-2">Add New Task</h1>
        <p class="text-secondary mb-4">Enter your assignment details below.</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e_add($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="add.php">
            <input type="hidden" name="csrf_token" value="<?= e_add($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
                <label for="title" class="form-label">Task Title *</label>
                <input type="text" class="form-control" id="title" name="title"
                       maxlength="200" value="<?= e_add($title) ?>" required>
            </div>

            <div class="mb-3">
                <label for="subject" class="form-label">Subject</label>
                <input type="text" class="form-control" id="subject" name="subject"
                       maxlength="100" value="<?= e_add($subject) ?>">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description"
                          rows="4" maxlength="10000"><?= e_add($description) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="due_date" class="form-label">Due Date</label>
                <input type="date" class="form-control" id="due_date" name="due_date"
                       value="<?= e_add($due_date) ?>">
            </div>

            <div class="mb-4">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <?php foreach (['Low', 'Medium', 'High'] as $p): ?>
                        <option value="<?= e_add($p) ?>" <?= $priority === $p ? 'selected' : '' ?>>
                            <?= e_add($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Task</button>
                <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
