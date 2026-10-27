
<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: tasks/dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    }

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Enter a name (maximum 100 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $check = $pdo->prepare(
            'SELECT id FROM users WHERE email = ?'
        );
        $check->execute([$email]);

        if ($check->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)'
            );

            try {
                $insert->execute([
                    $name,
                    $email,
                    $hashed_password
                ]);

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['registration_success'] =
                    'Account created successfully. Please log in.';

                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'This email is already registered.';
                } else {
                    error_log($e->getMessage());
                    $errors[] = 'Registration failed. Please try again.';
                }
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
    <title>Create Account | Student Task Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<main class="container py-5">
    <div class="auth-card mx-auto">
        <h1 class="h3 text-center mb-2">Create an Account</h1>
        <p class="text-secondary text-center mb-4">
            Organize your assignments and deadlines.
        </p>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" id="name" name="name"
                       class="form-control" maxlength="100"
                       value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="name" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email"
                       class="form-control" maxlength="150"
                       value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="email" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password"
                       class="form-control" minlength="8"
                       autocomplete="new-password" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    Confirm Password
                </label>
                <input type="password" id="confirm_password"
                       name="confirm_password" class="form-control"
                       minlength="8" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                Register
            </button>
        </form>

        <p class="text-center mt-4 mb-0">
            Already have an account?
            <a href="login.php">Log in</a>
        </p>
    </div>
</main>
</body>
</html>
