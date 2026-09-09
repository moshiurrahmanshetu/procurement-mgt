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

#### Phase 03: Supplier Management & Quotation Management
- Separate schema migration (`database/03_suppliers_quotations_schema.sql`) creating `suppliers`, `supplier_contacts`, `quotations`, `quotation_items`, and `quotation_history`.
- **Supplier Directory (`modules/suppliers/`)**:
  - Full CRUD operations with auto-sequenced vendor codes (`SUP-000001`, `SUP-000002`).
  - Company profiles, tax/VAT registration, addresses, banking details, and internal notes.
  - Multi-contact person support with primary contact indicator and role designations.
  - Complete vendor quotation history and awarded contract statistics.
  - Soft-delete safeguarding historical bids and quotations for audit compliance.
- **Quotation Management (`modules/quotations/`)**:
  - Creation of quotations linked to **approved** Purchase Requests only.
  - Auto-sequenced quotation identifiers (`QT-000001`, `QT-000002`).
  - Duplicate bid prevention (one quotation per supplier per PR).
  - Multi-item pricing grid with live client-side subtotal, tax %, shipping, other charges, and grand total calculations, coupled with strict server-side validation.
  - State machine transitions: `draft` -> `submitted` -> `under_review` -> `selected` / `rejected`.
  - **Quotation Comparison Matrix (`compare.php`)**: Side-by-side evaluation matrix with lowest-bid indicator, line item price comparisons, and variance against PR estimated budgets.
  - **Winning Quotation Selection (`select.php`)**: Single winning bid selection by Administrator or Manager that automatically and safely marks all competing bids for that PR as `rejected` in a single database transaction.
  - **Rejection with Reason (`reject.php`)**: Rejection logging requiring mandatory justification comments.
  - **Chronological Audit Trail (`history.php`)**: Full state-change timeline and audit logs.

---

## Workflow State Machines

### 1. Purchase Request State Machine
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

### 2. Supplier Quotation State Machine
```
   [Approved PR]
         │
         ▼
 [Create Quotation]
         │
         ▼
      (Draft) ───────────────► [Delete Draft]
         │
         ▼ [Submit]
    (Submitted)
         │
         ▼ [Mark Under Review]
   (Under Review) ────────────► [Reject Bid (Mandatory Reason)]
         │
         ▼ [Award Winning Bid]
     (Selected) ───► [Automatically Rejects Competing Bids for PR]
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

   # 3. Import Phase 03 Suppliers & Quotations Schema
   Get-Content "C:\xampp\htdocs\procurement-mgt\database\03_suppliers_quotations_schema.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root procurement_mgt
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
│   ├── js/
│   ├── images/
│   └── uploads/
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
│   ├── 03_suppliers_quotations_schema.sql
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
│   ├── suppliers/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── store.php
│   │   ├── view.php
│   │   ├── edit.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   ├── store-contact.php
│   │   └── delete-contact.php
│   ├── quotations/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── store.php
│   │   ├── view.php
│   │   ├── edit.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   ├── submit.php
│   │   ├── review.php
│   │   ├── select.php
│   │   ├── reject.php
│   │   ├── compare.php
│   │   └── history.php
│   └── users/
│       └── index.php
│
├── .htaccess
├── index.php
└── README.md
```