# Database Documentation — Complete Procurement Management CMS

## Database Name
`procurement_mgt` (MySQL 5.7+ / MariaDB 10.4+, Character Set: `utf8mb4`, Collation: `utf8mb4_unicode_ci`)

---

## Complete Sequential Import Order

To initialize the entire Procurement Management CMS from scratch, import all SQL files in the exact sequence below:

```
1. database/01_auth_schema.sql
2. database/02_purchase_requests_schema.sql
3. database/03_suppliers_quotations_schema.sql
4. database/04_purchase_orders_schema.sql
5. database/05_goods_receiving_schema.sql
6. database/06_reports_settings_schema.sql
```

---

## Import Instructions

### Option 1: phpMyAdmin
1. Open [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. Create database `procurement_mgt` (if not already created).
3. Click **Import** from the top menu.
4. Import each file in the order specified above (01 through 06).

### Option 2: MySQL Command Line (XAMPP / Linux)
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\01_auth_schema.sql
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\02_purchase_requests_schema.sql
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\03_suppliers_quotations_schema.sql
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\04_purchase_orders_schema.sql
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\05_goods_receiving_schema.sql
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\06_reports_settings_schema.sql
```

---

## Complete Schema & Table Catalog (21 Tables)

### Phase 01: Core Authentication & RBAC
1. **`roles`**: System operational roles (`Administrator`, `Procurement Officer`, `Manager`, `Requester`).
2. **`users`**: System accounts, bcrypt password hashes, profile avatars, and soft deletion (`deleted_at`).
3. **`user_roles`**: Many-to-many user-to-role assignment junction table.
4. **`password_resets`**: Expiring crypto-token storage for account password resets.
5. **`activity_logs`**: System audit trail capturing user ID, action, description, client IP, and user-agent.

### Phase 02: Requisition & Departments
6. **`departments`**: Organizational units (Administration, Finance, HR, IT, Operations, Procurement).
7. **`purchase_requests`**: Purchase requisition headers (`PR-000001`), priorities, status, and approval stamps.
8. **`purchase_request_items`**: Line items per requisition (name, description, unit, quantity, estimated cost).
9. **`purchase_request_history`**: Audit trail of purchase request state transitions.

### Phase 03: Suppliers & Quotations
10. **`suppliers`**: Vendor profiles (`SUP-000001`), tax details, payment terms, and status (`active`, `inactive`, `blacklisted`).
11. **`supplier_contacts`**: Vendor contact persons with primary indicator (`is_primary`).
12. **`quotations`**: Competitive vendor quotes (`QT-000001`) with multi-charge calculations (subtotal, tax, shipping, discounts).
13. **`quotation_items`**: Line item pricing linked to requisition items.
14. **`quotation_history`**: State audit trail of quotation submission, review, and selection.

### Phase 04: Purchase Orders
15. **`purchase_orders`**: Formal contracts (`PO-000001`), delivery dates, terms, and commitment amounts.
16. **`purchase_order_items`**: Line items with itemized tax and line totals.
17. **`purchase_order_history`**: Lifecycle audit trail (`draft` -> `pending_approval` -> `approved` -> `sent` -> `partially_received` -> `fully_received`).

### Phase 05: Goods Receiving (GRN)
18. **`goods_receipts`**: Warehouse intake records (`GRN-000001`), receipt dates, receiver IDs, and locked posted state.
19. **`goods_receipt_items`**: Accepted vs damaged/defective tracking with over-receiving prevention.
20. **`goods_receipt_history`**: Goods receiving lifecycle audit log.

### Phase 06: Reports & Settings Foundation
21. **`settings`**: Dynamic key-value store for company name, email, phone, address, logo, currency symbol, date format, and timezone.

---

## Default Administrator Seed Account
- **Username:** `admin`
- **Email:** `admin@example.com`
- **Password:** `admin123`
