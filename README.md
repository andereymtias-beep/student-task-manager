# 📚 StudyFlow – Student Task Manager

**StudyFlow** is a web-based Student Task Management System designed to help students organize academic tasks, track deadlines, monitor progress, and manage their daily school activities efficiently.

## ✨ Features

- **Dashboard** – View an overview of tasks and academic activities.
- **Task Management** – Add, edit, and delete tasks.
- **Task Completion** – Mark tasks as completed or pending.
- **Calendar View** – View scheduled tasks according to their due dates.
- **Task Analytics** – Monitor task completion and productivity.
- **Overdue Task Monitoring** – Identify pending tasks that have passed their deadlines.
- **Notifications** – Manage task-related reminders and notifications.
- **User Authentication** – Secure registration and login.
- **Profile Management** – Manage your account information.

## 🛠️ Technologies Used

| Technology | Purpose |
|---|---|
| HTML5 | Page structure |
| CSS3 | Styling and layout |
| JavaScript | Interactive features |
| Bootstrap | Responsive interface |
| PHP | Backend development |
| MySQL | Database management |
| XAMPP | Local development server |
| Visual Studio Code | Code editor |

## 📂 Project Structure

```text
student-task-manager/
│
├── assets/
│   └── css/
│       └── style.css
│
├── config/
│   └── database.example.php
│
├── tasks/
│   ├── add.php
│   ├── analytics.php
│   ├── calendar.php
│   ├── dashboard.php
│   ├── delete.php
│   ├── edit.php
│   ├── notifications.php
│   ├── profile.php
│   └── toggle.php
│
├── index.php
├── login.php
├── logout.php
├── register.php
└── README.md
```

*Note: The structure above highlights the main project files. Other files and folders may also be included.*

## ⚙️ Installation and Setup

### 1. Clone the Repository

```bash
git clone https://github.com/andereymtias-beep/student-task-manager.git
```

### 2. Move the Project

Place the project folder inside your XAMPP `htdocs` directory.

```text
C:\xampp\htdocs\student-task-manager
```

### 3. Start XAMPP

Start the following services:

- Apache
- MySQL

### 4. Create the Database

1. Open phpMyAdmin at `http://localhost/phpmyadmin`.
2. Create a new MySQL database.
3. Import the project's SQL database file, if
