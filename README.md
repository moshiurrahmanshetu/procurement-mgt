# Procurement Management CMS

A business-grade, secure, and responsive Procurement Management Content Management System built with **Raw PHP** and **MySQL PDO**.

---

## Technology Stack

- **Backend:** Raw PHP 8.x (No frameworks, pure native architecture)
- **Database:** MySQL 5.7+ / MariaDB 10.4+ using PDO prepared statements
- **Frontend:** HTML5, CSS3, Vanilla JavaScript, Bootstrap 5.3.3, Bootstrap Icons 1.11.3
- **Server Environment:** XAMPP (Apache + MySQL)

---

## Phase 01 Features Completed

- **Clean Native Architecture**: Strict Raw PHP with organized `config/`, `includes/`, `auth/`, and `modules/` directories.
- **Database Schema & Seed**: Independently importable SQL schema (`database/01_auth_schema.sql`) with InnoDB, `utf8mb4`, and foreign keys.
- **Robust Authentication**:
  - Secure login with username or email using `password_verify()`.
  - Secure session handling with strict cookie settings (`HttpOnly`, `SameSite=Lax`).
  - Single-use password reset tokens (SHA-256 hashed).
  - Activity audit logging for all authentication and profile events.
- **Role-Based Access Control Foundation**:
  - Roles: `Administrator`, `Procurement Officer`, `Manager`, `Requester`.
  - Access control helpers (`requireLogin()`, `requireRole()`, `userHasRole()`).
  - Professional 403 Access Denied page for unauthorized access attempts.
- **CSRF Protection**: Universal CSRF token generation and validation on all state-changing POST forms.
- **Master UI & Collapsible Sidebar**:
  - Professional corporate color system using CSS variables (slate sidebar `#0f172a`, neutral body `#f8fafc`, primary accent `#2563eb`).
  - Fully collapsible sidebar with state persistence using `localStorage`.
  - Mobile drawer navigation with backdrop overlay.
  - Fallback avatar generator with user initials (never displays broken images).
- **Dashboard**:
  - Real database metrics (user role, account status, last login time, total active users).
  - System foundation diagnostic status card.
  - Recent activity audit trail from `activity_logs`.
- **User Profile & Security Management**:
  - Update profile details (full name, username, email) with uniqueness validation.
  - Secure avatar upload supporting JPG, PNG, WEBP (max 2MB) with server-side `finfo` MIME validation, unique randomized filenames, and previous file cleanup.
  - One-click avatar removal restoring the initials fallback.
  - Password change with current password verification and bcrypt hashing.
- **Users Management Module**:
  - Administrator-only overview table of all registered system accounts, roles, and statuses.

---

## Installation & Setup Guide (XAMPP)

### 1. Place Project in XAMPP
Ensure the project folder is placed in:
```
C:\xampp\htdocs\procurement-mgt\
```

### 2. Import Database Schema
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open **phpMyAdmin** at [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/) or use the MySQL CLI.
3. Import the SQL file:
   ```
   database/01_auth_schema.sql
   ```
   *Via PowerShell:*
   ```powershell
   Get-Content "c:\xampp\htdocs\procurement-mgt\database\01_auth_schema.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root
   ```

### 3. Verify Database Configuration
Database connection parameters are located in `config/config.php`:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'procurement_mgt');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Access the Application
Open your web browser and visit:
```
http://localhost/procurement-mgt/
```

---

## Default Administrator Credentials

| Field | Value |
| :--- | :--- |
| **Username** | `admin` |
| **Email** | `admin@example.com` |
| **Password** | `admin123` |
| **Role** | `Administrator` |

---

## Project Structure

```
procurement-mgt/
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   ├── bootstrap-icons.css
│   │   ├── style.css
│   │   ├── responsive.css
│   │   └── auth.css
│   ├── js/
│   │   ├── bootstrap.bundle.min.js
│   │   ├── app.js
│   │   └── sidebar.js
│   ├── images/
│   │   ├── logo/
│   │   ├── avatars/
│   │   └── placeholders/
│   └── uploads/
│       └── avatars/
│
├── auth/
│   ├── login.php
│   ├── logout.php
│   ├── forgot-password.php
│   ├── reset-password.php
│   └── access-denied.php
│
├── config/
│   ├── config.php
│   ├── constants.php
│   └── database.php
│
├── database/
│   ├── 01_auth_schema.sql
│   └── README.md
│
├── includes/
│   ├── init.php
│   ├── session.php
│   ├── auth.php
│   ├── functions.php
│   ├── csrf.php
│   ├── flash.php
│   ├── header.php
│   ├── sidebar.php
│   ├── navbar.php
│   └── footer.php
│
├── modules/
│   ├── dashboard/
│   │   └── index.php
│   ├── profile/
│   │   ├── index.php
│   │   ├── update-profile.php
│   │   ├── change-password.php
│   │   └── update-avatar.php
│   └── users/
│       └── index.php
│
├── .htaccess
├── index.php
└── README.md
```