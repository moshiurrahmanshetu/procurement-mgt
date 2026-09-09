# Procurement Management CMS — Complete Enterprise Edition

A modern, secure, database-driven, and responsive **Procurement Management Content Management System (CMS)** built with **Raw PHP 8.x**, **MySQL/MariaDB PDO**, **Bootstrap 5**, **Bootstrap Icons**, and **Vanilla JavaScript**.

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Key Architectural Features](#key-architectural-features)
4. [Complete Procurement Lifecycle & Workflow](#complete-procurement-lifecycle--workflow)
5. [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
6. [Modules Catalog](#modules-catalog)
7. [Reports & Analytics Hub](#reports--analytics-hub)
8. [System Configuration & Localization](#system-configuration--localization)
9. [Project Directory Structure](#project-directory-structure)
10. [Installation & Setup Guide](#installation--setup-guide)
11. [Database Import Order](#database-import-order)
12. [Default Development Credentials](#default-development-credentials)
13. [Security Architecture](#security-architecture)
14. [Automated Verification & Testing](#automated-verification--testing)
15. [Marketplace & Production Deployment Notes](#marketplace--production-deployment-notes)

---

## Project Overview

The **Procurement Management CMS** provides end-to-end corporate procurement automation without dependencies on heavy PHP frameworks or external JavaScript libraries. It streamlines the entire requisition-to-fulfillment lifecycle:
- Department requisition composition and manager approval workflows.
- Comprehensive vendor directory management with multi-contact profiles.
- Competitive supplier bidding and comparative quotation matrices.
- Formal Purchase Order issuance, approval gates, and supplier dispatch.
- Warehouse Goods Receiving (GRN) tracking with over-receiving prevention and partial shipment management.
- Dynamic analytical reporting with native streaming CSV exports and A4 browser-based printouts.
- System-wide company branding, localization, currency management, user administration, and immutable audit logs.

---

## Technology Stack

- **Backend Language:** Raw PHP 8.0+ / 8.1+ / 8.2+ / 8.3+ (Pure native PHP, strict PDO prepared statements, no Laravel/Symfony/Blade dependencies).
- **Database Engine:** MySQL 5.7+ / MariaDB 10.4+ (InnoDB, UTF-8 `utf8mb4_unicode_ci`, foreign keys with cascading integrity).
- **Frontend Framework:** HTML5, CSS3, Bootstrap 5.3.3 (Local assets, responsive layout).
- **Iconography:** Bootstrap Icons 1.11.3 (Local web fonts).
- **Client Scripting:** Vanilla JavaScript (ES6+, zero jQuery/Node/React/Vue dependencies).
- **Web Server:** Apache 2.4+ (XAMPP / LAMP / LEMP / cPanel / Shared & Cloud hosting).

---

## Key Architectural Features

- **Framework-Free Performance:** Blazing fast native PHP architecture utilizing structured modular separation (`config/`, `includes/`, `auth/`, `modules/`).
- **Dynamic Path Detection:** Fully portable runtime detection of `BASE_URL` and `ROOT_PATH` ensuring zero hardcoded local Windows/Unix paths.
- **Pure Native Reporting:** Browser-based print stylesheets (`@media print`) and native streaming CSV data export (`fputcsv()`) without third-party PDF or spreadsheet packages.
- **Enterprise Security:** Cryptographic CSRF validation tokens on all POST mutations, server-side MIME verification with `finfo`, strict session lifecycle management, and output escaping.
- **Multi-Phase State Machines:** Strict status guards enforcing business logic across Requisitions, Quotes, POs, and GRNs.

---

## Complete Procurement Lifecycle & Workflow

```
[1. Requester]
      │
      ▼
 Create Requisition (Draft) ──► Submit PR ──► (Pending Approval)
                                                   │
[2. Manager]                                       ▼
 Reject (with reason) ◄─────────────────── Approve Requisition
                                                   │
[3. Procurement Officer]                           ▼
 Issue RFQ / Record Vendor Quotation ──► (Under Review)
                                                   │
                                                   ▼
 Quotation Comparison Matrix ────────────► Award Winning Bid (Selected)
                                                   │
                                                   ▼
 Convert Quote to Purchase Order (Draft) ─► Submit PO (Pending Approval)
                                                   │
[4. Manager]                                       ▼
 Reject PO ◄────────────────────────────── Approve Purchase Order
                                                   │
[5. Procurement Officer]                           ▼
 Dispatched to Supplier ────────────────► (Sent Status)
                                                   │
[6. Receiving Officer]                             ▼
 Create Goods Receipt (GRN) ─────────────► Inspect & Record Accepted/Defects
                                                   │
                                                   ▼
 Post GRN (Locked) ──────────────────────► PO: Partially Received
                                                   │
                                                   ▼ (Remaining Balance Shipment)
 Post Final GRN (Locked) ────────────────► PO: Fully Received (Closed)
```

---

## Role-Based Access Control (RBAC)

The system includes 4 predefined enterprise roles:

| Role | Description | Access Rights |
|---|---|---|
| **Administrator** | System Owner & Tech Admin | Full system control, User Management CRUD, System Settings, Activity Logs, All Procurement Modules, All Reports. |
| **Manager** | Department & Executive Approver | Requisition Approvals, Purchase Order Approvals, Quotation Evaluations, Activity Logs, Procurement Reports. |
| **Procurement Officer** | Sourcing & Purchasing Buyer | Sourcing Suppliers, Recording Quotes, Comparing Bids, Issuing Purchase Orders, Inspecting Goods Receipts, Reports. |
| **Requester** | Employee / Department Staff | Requisition Creation & Submission, Tracking Requisitions, Scoped Self-Service Reports. |

---

## Modules Catalog

### 1. Authentication & Security (`/auth/`)
- User Login with bcrypt password verification, Session fixation prevention (`session_regenerate_id()`), and `HttpOnly` cookie security.
- Forgot Password and Crypto-Token Password Reset lifecycle.
- Access Denied guard pages for unauthorized direct URL manipulation.

### 2. Purchase Requests (`/modules/purchase_requests/`)
- Dynamic multi-item requisition input grid with client and server validations.
- Sequence generation: `PR-000001`, `PR-000002`.
- Priority levels: `Low`, `Medium`, `High`, `Urgent`.
- Workflow: `draft` -> `pending_approval` -> `approved` / `rejected` / `cancelled`.

### 3. Suppliers & Vendors (`/modules/suppliers/`)
- Vendor directory with tax identification numbers, banking routing details, and payment terms.
- Sequence generation: `SUP-000001`.
- Multi-contact management with primary representative flag.
- Status management: `active`, `inactive`, `blacklisted`.

### 4. Quotations & Bidding (`/modules/quotations/`)
- Itemized pricing grids with auto-calculated subtotals, tax rates, shipping, and discounts.
- Side-by-side **Quotation Comparison Matrix (`compare.php`)** with lowest price indicator.
- Single-transaction bid awarding (`select.php`) with automated rejection of competing bids.

### 5. Purchase Orders (`/modules/purchase_orders/`)
- Direct 1-click generation from awarded Quotations or standalone vendor contracts.
- Sequence generation: `PO-000001`.
- Approval gates, delivery terms, address mapping, and formal A4 printable purchase orders (`print.php`).

### 6. Goods Receiving / GRN (`/modules/goods_receiving/`)
- Warehouse intake strictly validated against Purchase Orders in `sent` or `partially_received` state.
- Sequence generation: `GRN-000001`.
- Accepted vs rejected tracking with server-side over-receiving blocks across multiple partial deliveries.
- Posted GRN immutability and automated PO status transitions.

### 7. User Management (`/modules/users/`)
- Full Administrator CRUD: Create user, edit details, assign role, toggle active/inactive status, and soft deletion.
- **Last Active Administrator Protection:** Hard safety checks preventing demotion, deactivation, or deletion of the sole active administrator.

### 8. System Activity Logs (`/modules/activity_logs/`)
- Comprehensive audit trail with searchable actions, user attribution, descriptions, IP addresses, and user-agent logging.

### 9. User Profile (`/modules/profile/`)
- Personal profile editing, bcrypt password updates, and secure avatar upload/removal with `finfo` server-side validation.

---

## Reports & Analytics Hub (`/modules/reports/`)

The CMS provides 5 analytical reporting engines with filter controls, summary statistic cards, native CSV downloads, and A4 print templates:

1. **Purchase Request Report (`purchase_requests.php`):** Requisition tracking, department demand, urgency priorities, and approval metrics.
2. **Supplier & Vendor Report (`suppliers.php`):** Vendor performance, quotation win ratios, total PO counts, and committed spend.
3. **Quotation & Bidding Report (`quotations.php`):** Comparative bidding audits, price submissions, and awarded contract allocations.
4. **Purchase Order Report (`purchase_orders.php`):** Committed financial spend, approved orders, and dispatch progress.
5. **Goods Receiving Report (`goods_receiving.php`):** Warehouse receipt logs, shipment inspection records, accepted vs defective goods counts.

---

## System Configuration & Localization (`/modules/settings/`)

Administrators can configure global system defaults:
- **Company Information:** Company Name, Procurement Email, Telephone, Official Business Address.
- **Localization:** Currency Symbol (e.g. `$`, `€`, `£`, `৳`, `₹`, `¥`), System Date Format (`d M Y`, `Y-m-d`, `m/d/Y`, `d/m/Y`), and Timezone.
- **Branding & Logo:** Official company logo upload, preview, and safe replacement with automatic rendering in navigation, page titles, and printable documents.

---

## Project Directory Structure

```
procurement-mgt/
│
├── assets/                          # Static Frontend Assets
│   ├── css/                         # Bootstrap, Bootstrap Icons, style.css, responsive.css
│   ├── js/                          # Bootstrap bundle, sidebar.js, app.js
│   └── uploads/                     # Secure upload storage (avatars, logos)
│       ├── avatars/
│       └── logos/
│
├── auth/                            # Authentication Controllers & Views
│   ├── login.php
│   ├── logout.php
│   ├── forgot-password.php
│   ├── reset-password.php
│   └── access-denied.php
│
├── config/                          # Centralized Configuration
│   ├── config.php                   # Database connection credentials & debug flags
│   ├── constants.php                # Dynamic URLs, root paths, and upload rules
│   └── database.php                 # PDO database connector singleton
│
├── database/                        # Sequential SQL Migrations (01 to 06)
│   ├── 01_auth_schema.sql
│   ├── 02_purchase_requests_schema.sql
│   ├── 03_suppliers_quotations_schema.sql
│   ├── 04_purchase_orders_schema.sql
│   ├── 05_goods_receiving_schema.sql
│   ├── 06_reports_settings_schema.sql
│   └── README.md
│
├── includes/                        # System Foundation & Helpers
│   ├── init.php                     # Global bootstrap script
│   ├── auth.php                     # Authentication & RBAC enforcement
│   ├── csrf.php                     # CSRF token generator & validator
│   ├── flash.php                    # Flash notification system
│   ├── functions.php                # Settings, formatters, avatar & logo helpers
│   ├── session.php                  # Secure session handler
│   ├── header.php                   # HTML Master Header
│   ├── navbar.php                   # Top navigation bar
│   ├── sidebar.php                  # Responsive collapsible sidebar
│   └── footer.php                   # HTML Master Footer & scripts
│
├── modules/                         # Core Business Modules
│   ├── dashboard/                   # Executive dashboard & procurement pipeline
│   ├── purchase_requests/           # Requisition lifecycle CRUD & history
│   ├── suppliers/                   # Vendor directory & contact management
│   ├── quotations/                  # Vendor quotes & comparison matrix
│   ├── purchase_orders/             # Purchase order issuance & print layouts
│   ├── goods_receiving/             # Warehouse GRN intake & defect tracking
│   ├── reports/                     # Reports hub, 5 analytical reports, CSV & print
│   ├── settings/                    # System branding, localization & logo settings
│   ├── users/                       # User management full CRUD & last-admin guards
│   ├── activity_logs/               # System audit log viewer
│   └── profile/                     # User profile, password & avatar management
│
├── .htaccess                        # Apache configuration & security rules
├── index.php                        # Root entry router (redirects to login/dashboard)
└── README.md                        # Complete Documentation
```

---

## Marketplace Installation & Setup Guide

### Method A: Web-Based Installation Wizard (Recommended)
1. Extract the project ZIP into your web server directory (e.g. `C:\xampp\htdocs\procurement-mgt\` or `/var/www/html/procurement/`).
2. Create an empty MySQL / MariaDB database (e.g., in cPanel or phpMyAdmin).
3. Open your browser and navigate to the application URL:
   ```
   http://localhost/procurement-mgt/
   ```
   *(The system automatically detects uninstalled status and opens `/installer/`).*
4. **Step 1 — Requirements:** Review server prerequisites (PHP 8.0+, PDO MySQL, JSON, Sessions, Writable directories).
5. **Step 2 — Database:** Enter your database host, port, name, username, and password. Click **"Test Connection"** to verify live connectivity.
6. **Step 3 — Import:** Select the bundled master schema (`database/install.sql`) and click **"Import & Continue"**.
7. **Step 4 — Administrator:** Set up your primary administrator account (Full Name, Username, Email, and Secure Password).
8. **Step 5 — Complete:** The installer writes `config/config.php`, verifies live connection, applies the permanent security lock (`config/installed.lock`), and redirects to login!

---

### Method B: Manual CLI / phpMyAdmin Setup (Developers)

1. Create a MySQL database named `procurement_mgt`.
2. Import the bundled master schema file:
   ```bash
   mysql -u root -p procurement_mgt < database/install.sql
   ```
   *(Or import `database/01_auth_schema.sql` through `database/06_reports_settings_schema.sql` sequentially).*
3. Configure `config/config.php` with your database credentials.
4. Create the lock file to mark installation complete:
   ```bash
   touch config/installed.lock
   ```

---

## Master Database Schema

The production-ready master schema is bundled in:
```
database/install.sql
```
*(Individual migration files `01_auth_schema.sql` through `06_reports_settings_schema.sql` are preserved for modular developer reference).*

---

## Manual Reinstall Procedure

To re-run the web installer from scratch:
1. Back up your existing data.
2. Delete the installation lock file: `rm config/installed.lock`.
3. Drop/empty the database.
4. Navigate to `/installer/` in your browser.


---

## Default Development Credentials

| Role | Username | Email | Password |
|---|---|---|---|
| **Administrator** | `admin` | `admin@example.com` | `admin123` |

*(Additional accounts for Managers, Procurement Officers, and Requesters can be created via the User Management module).*

---

## Security Architecture

1. **Prepared Statements:** 100% of database interactions use PDO prepared statements with bound parameters, preventing SQL injection.
2. **CSRF Protection:** Cryptographic tokens generated per session and validated on every state-changing POST request.
3. **XSS Escaping:** All user-supplied outputs sanitized via HTML entities (`e()` helper).
4. **File Upload Hardening:** Server-side MIME verification using `finfo`, strict extension whitelisting, file size limits (2MB), unique randomized filenames, and `.htaccess` file execution prevention.
5. **Secure Authentication:** Passwords hashed with bcrypt (`PASSWORD_BCRYPT`). Session regeneration on login to prevent session fixation.
6. **Last Admin Safeguard:** Server-side enforcement preventing the removal or deactivation of the final administrator account.

---

## Automated Verification & Testing

The codebase includes automated test suites covering syntax linting, database structure, settings caching, user management safeguards, reports aggregations, and full end-to-end business workflows.

Run the test suite via CLI / PowerShell:
```powershell
php scratch/verify_phase06.php
```

**Expected Result:** `29 PASSED, 0 FAILED`

---

## Marketplace & Production Deployment Notes

- **Portability:** All asset and page URLs use `BASE_URL` and `ROOT_PATH`. The CMS can be deployed on root domains (`https://example.com/`) or subdirectories (`https://example.com/procure/`).
- **Production Mode:** Set `define('DEV_MODE', false);` in `config/config.php` for live production environments.
- **Directory Permissions:** Ensure `assets/uploads/avatars/` and `assets/uploads/logos/` are writable (`0755` on Linux/Unix).
- **HTTPS:** The system dynamically detects SSL/HTTPS and configures secure cookie flags accordingly.

---

*Procurement Management CMS — Designed for Enterprise Procurement Efficiency.*