
<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: tasks/dashboard.php');
} else {
    header('Location: login.php');
}

exit;
