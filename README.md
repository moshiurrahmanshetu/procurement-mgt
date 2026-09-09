# Procurement Management CMS

A business-grade, secure, and responsive Procurement Management Content Management System built with **Raw PHP** and **MySQL PDO**.

---

## Technology Stack

- **Backend:** Raw PHP 8.x (No frameworks, pure native modular architecture)
- **Database:** MySQL 5.7+ / MariaDB 10.4+ using PDO prepared statements
- **Frontend:** HTML5, CSS3, Vanilla JavaScript, Bootstrap 5.3.3, Bootstrap Icons 1.11.3
- **Server Environment:** XAMPP (Apache + MySQL)

---

## Implemented Phases Overview

### Phase 01: Core Authentication & Architecture
- Clean Raw PHP architecture organized across `config/`, `includes/`, `auth/`, and `modules/`.
- Database schema & seed (`database/01_auth_schema.sql`) with roles, users, password resets, and activity logs.
- Secure login, logout, password resets, and session management (`HttpOnly`, `SameSite=Lax`).
- Role-based access control (`Administrator`, `Procurement Officer`, `Manager`, `Requester`).
- Enterprise master layout, collapsible sidebar with `localStorage` persistence, and dynamic user initials avatar fallback.
- User profile editing, secure avatar upload/removal with `finfo` server-side MIME detection, and bcrypt password changes.

### Phase 02: Purchase Request Module
- Separate schema migration (`database/02_purchase_requests_schema.sql`) creating `departments`, `purchase_requests`, `purchase_request_items`, and `purchase_request_history`.
- Auto-sequenced human-friendly request numbers (`PR-000001`, `PR-000002`) generated via auto-increment transactions.
- **Requisition Workflow**:
  - **Requester**: Create multi-item requisition, save as Draft, edit/delete draft, submit for approval, cancel.
  - **Manager**: Review pending requisitions, approve, or reject with mandatory reason.
  - **Procurement Officer**: Monitor all purchase requisitions, search, and filter.
  - **Administrator**: Full administrative control across all requisitions and workflow states.
- Dynamic line items grid with client-side live calculations and strict server-side recalculation.
- Search by Request No and purpose; filters by status, priority, department, date range, and requester.
- Server-side pagination preserving active filters (20 per page).
- Chronological workflow audit trail (`purchase_request_history`) with status change timelines.
- Real-time Purchase Request metrics and recent requisitions table integrated into the main Dashboard.

---

## Purchase Request Workflow State Machine

```
   [Requester / Staff]
          │
          ▼
    [Create Request]
          │
          ▼
       (Draft) ──────────────► [Delete Draft]
          │
          ▼ [Submit]
 (Pending Approval) ─────────► [Cancel Request]
    │           │
    │ [Approve] │ [Reject (Mandatory Reason)]
    ▼           ▼
(Approved)   (Rejected)
```

---

## Database Import & Installation Guide (XAMPP)

### 1. Place Project in XAMPP
Ensure the project is located at:
```
C:\xampp\htdocs\procurement-mgt\
```

### 2. Import Database Schemas (In Order)
1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Run in PowerShell:
   ```powershell
   # 1. Import Phase 01 Auth Schema
   Get-Content "C:\xampp\htdocs\procurement-mgt\database\01_auth_schema.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root

   # 2. Import Phase 02 Purchase Requests Schema
   Get-Content "C:\xampp\htdocs\procurement-mgt\database\02_purchase_requests_schema.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root procurement_mgt
   ```

### 3. Verify Database Configuration
Configuration is located in `config/config.php`:
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
│   │   └── logo/
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
│   ├── 02_purchase_requests_schema.sql
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
│   ├── purchase_requests/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── store.php
│   │   ├── view.php
│   │   ├── edit.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   ├── submit.php
│   │   ├── approve.php
│   │   ├── reject.php
│   │   ├── cancel.php
│   │   └── history.php
│   └── users/
│       └── index.php
│
├── .htaccess
├── index.php
└── README.md
```