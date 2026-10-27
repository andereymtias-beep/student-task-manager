
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

function e_edit($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userId = (int) $_SESSION['user_id'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid task ID.');
}

$stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    exit('Task not found.');
}

$errors = [];
$title = $task['title'];
$description = $task['description'] ?? '';
$subject = $task['subject'] ?? '';
$due_date = $task['due_date'] ?? '';
$priority = $task['priority'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $priority = $_POST['priority'] ?? '';

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
        $update = $pdo->prepare(
            'UPDATE tasks
             SET title = ?, description = ?, subject = ?,
                 due_date = ?, priority = ?
             WHERE id = ? AND user_id = ?'
        );

        $update->execute([
            $title,
            $description !== '' ? $description : null,
            $subject !== '' ? $subject : null,
            $due_date !== '' ? $due_date : null,
            $priority,
            $id,
            $userId
        ]);

        $_SESSION['flash_message'] = 'Task updated successfully.';
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
    <title>Edit Task | Student Task Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main class="container py-5">
    <div class="auth-card mx-auto" style="max-width: 650px;">
        <h1 class="h3 mb-4">Edit Task</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e_edit($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?= (int) $id ?>">
            <input type="hidden" name="csrf_token" value="<?= e_edit($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
                <label for="title" class="form-label">Task Title *</label>
                <input type="text" id="title" name="title" class="form-control"
                       maxlength="200" value="<?= e_edit($title) ?>" required>
            </div>

            <div class="mb-3">
                <label for="subject" class="form-label">Subject</label>
                <input type="text" id="subject" name="subject" class="form-control"
                       maxlength="100" value="<?= e_edit($subject) ?>">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control"
                          rows="4" maxlength="10000"><?= e_edit($description) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="due_date" class="form-label">Due Date</label>
                <input type="date" id="due_date" name="due_date" class="form-control"
                       value="<?= e_edit($due_date) ?>">
            </div>

            <div class="mb-4">
                <label for="priority" class="form-label">Priority</label>
                <select id="priority" name="priority" class="form-select">
                    <?php foreach (['Low', 'Medium', 'High'] as $p): ?>
                        <option value="<?= e_edit($p) ?>" <?= $priority === $p ? 'selected' : '' ?>>
                            <?= e_edit($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</main>
</body>
</html>
