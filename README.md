
# StudyFlow — Student Task Manager

StudyFlow is a PHP and MySQL web application designed to help students manage academic tasks, deadlines, and productivity.

## Features

- User registration and login
- Secure password hashing
- Create, edit, delete, and complete tasks
- Task search and filtering
- Task priority and status tracking
- Dashboard statistics and progress tracking
- Calendar and upcoming deadlines
- Deadline notifications
- Profile and password settings
- Productivity analytics and charts
- Task completion timestamps

## Technology Stack

- PHP 8+
- MySQL / MariaDB
- PDO
- HTML5, CSS3, JavaScript
- Bootstrap 5
- Bootstrap Icons
- Chart.js
- XAMPP

## Local Installation

1. Install and start Apache and MySQL in XAMPP.
2. Place this project inside the `htdocs` directory.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Create a database named `student_task_manager`.
5. Import or execute the database schema required by the application.
6. Copy `config/database.example.php` to `config/database.php`.
7. Configure your local database credentials.
8. Open `http://localhost/student-task-manager/` in your browser.
9. Register an account and start using StudyFlow.

**Note:** The database schema must include the `users`, `tasks`, and `notifications` tables, including the `completed_at` column in `tasks`.

## Security

- Passwords are hashed before storage.
- Database queries use PDO prepared statements.
- Task operations should verify task ownership.
- State-changing requests use CSRF protection.
- Local credentials must not be committed to GitHub.

## Project Structure

```text
student-task-manager/
├── assets/
│   ├── css/
│   └── js/
├── config/
├── tasks/
├── index.php
├── login.php
├── logout.php
├── register.php
├── README.md
└── .gitignore
```

## License

Add a license file if you intend to distribute or allow reuse of this project.
