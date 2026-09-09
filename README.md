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

### Phase 02: Purchase Request Management
- Schema migration (`database/02_purchase_requests_schema.sql`) for requests, multi-item line items, and audit history.
- Auto-generated requisition sequence (`PR-000001`, `PR-000002`).
- Multi-item dynamic input grid with client and server-side validation.
- Lifecycle: `draft` -> `pending_approval` -> `approved` / `rejected` / `cancelled`.

### Phase 03: Supplier & Quotation Management
- Schema migration (`database/03_suppliers_quotations_schema.sql`) creating `suppliers`, `supplier_contacts`, `quotations`, `quotation_items`, and `quotation_history`.
- **Supplier Directory (`modules/suppliers/`)**:
  - Full CRUD operations with auto-sequenced vendor codes (`SUP-000001`).
  - Company profiles, tax/VAT registration, addresses, banking details, and internal notes.
  - Multi-contact person support with primary contact indicator.
- **Quotation Management (`modules/quotations/`)**:
  - Linked to approved Purchase Requests.
  - Auto-sequenced quotation identifiers (`QT-000001`).
  - Multi-item pricing grid with live client-side subtotal, tax %, shipping, and grand totals.
  - Side-by-side **Quotation Comparison Matrix (`compare.php`)** with lowest-bid indicator.
  - Winning bid selection (`select.php`) with automated rejection of competing bids in a single transaction.

### Phase 04: Purchase Order Management
- Schema migration (`database/04_purchase_orders_schema.sql`) creating `purchase_orders`, `purchase_order_items`, and `purchase_order_history`.
- **Creation Pathways (`modules/purchase_orders/`)**:
  - Direct conversion from selected winning Quotation (auto-populating vendor, PR reference, line items, and agreed pricing).
  - Standalone PO generation for direct supplier procurement.
- Auto-sequenced PO numbering (`PO-000001`).
- Lifecycle: `draft` -> `pending_approval` -> `approved` -> `sent` -> `partially_received` / `fully_received` / `cancelled` / `closed`.
- A4 printable purchase order document (`print.php`) with clean print CSS and signature blocks.

### Phase 05: Goods Receiving / GRN Management
- Schema migration (`database/05_goods_receiving_schema.sql`) creating `goods_receipts`, `goods_receipt_items`, and `goods_receipt_history`.
- **Core Goods Receiving Rules (`modules/goods_receiving/`)**:
  - Intake initiates strictly against Purchase Orders in `sent` or `partially_received` status.
  - Auto-sequenced Goods Receipt numbering (`GRN-000001`, `GRN-000002`).
  - Accepted intake (`received_qty`) vs damaged/defective tracking (`rejected_qty`).
  - Live calculations for previously accepted quantities, remaining balances, and line completion status.
  - Strict server-side prevention of over-receiving across multiple partial shipments.
  - State machine: `draft` -> `posted`.
  - **Draft GRNs:** Modifiable and soft-deletable (`deleted_at = NOW()`), does not affect PO cumulative counts.
  - **Posted GRNs:** Permanent, immutable receiving document. Modifying or deleting is strictly prevented.
  - **Automated PO Status Transitions:** Posting a GRN automatically updates the PO status to `partially_received` or `fully_received` based on cumulative accepted items.
  - Standalone A4 printable GRN document (`print.php`) with delivery notes, carrier info, inspection remarks, and signature boxes.

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

### 3. Purchase Order State Machine
```
   [Selected Quotation / Direct PO]
                 │
                 ▼
              (Draft) ──────────────────► [Delete Draft]
                 │
                 ▼ [Submit for Approval]
        (Pending Approval) ─────────────► [Reject PO]
                 │
                 ▼ [Approve PO]
             (Approved) ────────────────► [Cancel PO]
                 │
                 ▼ [Send to Supplier]
               (Sent) ──────────────────► [Cancel PO]
                 │
                 ▼ [Post Goods Receipt (GRN)]
   ┌─────────────────────────────┐
   │                             │
   ▼                             ▼
(Partially Received)      (Fully Received)
   │                             │
   ▼ [Post Final GRN]            ▼ [Close PO]
(Fully Received)              (Closed)
```

### 4. Goods Receiving (GRN) State Machine
```
   [PO in 'Sent' or 'Partially Received']
                    │
                    ▼
          [Create Goods Receipt]
                    │
                    ▼
                 (Draft) ───────────────► [Soft Delete Draft]
                    │
                    ▼ [Post GRN (Locked / Immutable)]
                 (Posted)
                    │
                    ▼
      [Recalculate PO Fulfillment]
         ├─ Partial Items Accepted  ──► Updates PO to 'Partially Received'
         └─ All Items 100% Accepted ──► Updates PO to 'Fully Received'
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

   # 4. Import Phase 04 Purchase Orders Schema
   Get-Content "C:\xampp\htdocs\procurement-mgt\database\04_purchase_orders_schema.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root procurement_mgt

   # 5. Import Phase 05 Goods Receiving Schema
   Get-Content "C:\xampp\htdocs\procurement-mgt\database\05_goods_receiving_schema.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root procurement_mgt
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
│   ├── 04_purchase_orders_schema.sql
│   ├── 05_goods_receiving_schema.sql
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
│   ├── suppliers/
│   ├── quotations/
│   ├── purchase_orders/
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
│   │   ├── send.php
│   │   ├── cancel.php
│   │   ├── print.php
│   │   └── history.php
│   ├── goods_receiving/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── store.php
│   │   ├── view.php
│   │   ├── edit.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   ├── post.php
│   │   ├── print.php
│   │   └── history.php
│   └── users/
│       └── index.php
│
├── .htaccess
├── index.php
└── README.md
```