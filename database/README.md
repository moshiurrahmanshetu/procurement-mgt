# Database Documentation - Phase 01

## Database Name
`procurement_mgt`

## Import Instructions

### Option 1: phpMyAdmin
1. Open [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. Click **Import** from the top menu
3. Choose file: `database/01_auth_schema.sql`
4. Click **Import** (or Go)

### Option 2: MySQL CLI (XAMPP)
Run in terminal / PowerShell:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root < c:\xampp\htdocs\procurement-mgt\database\01_auth_schema.sql
```

---

## Schema Overview

### Tables

1. **`roles`**: Defines system roles.
   - `id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`
   - Default Seed Roles:
     - `administrator` (ID: 1)
     - `procurement-officer` (ID: 2)
     - `manager` (ID: 3)
     - `requester` (ID: 4)

2. **`users`**: Stores user credentials and profile information.
   - `id`, `full_name`, `username`, `email`, `password` (bcrypt hash), `avatar`, `status` (`active`/`inactive`), `last_login`, `last_activity`, `created_at`, `updated_at`, `deleted_at` (soft delete).
   - Default Admin Seed:
     - Username: `admin`
     - Email: `admin@example.com`
     - Password: `admin123` (hashed)

3. **`user_roles`**: Many-to-many relationship mapping users to roles.
   - `id`, `user_id`, `role_id`, `created_at`
   - Foreign Keys with `ON DELETE CASCADE` and unique constraint `(user_id, role_id)`.

4. **`password_resets`**: Secure token storage for forgot/reset password flows.
   - `id`, `user_id`, `token` (SHA-256 hash of random token), `expires_at`, `used_at`, `created_at`.
   - Foreign Key to `users(id)` with `ON DELETE CASCADE`.

5. **`activity_logs`**: System audit trail.
   - `id`, `user_id` (nullable), `action`, `description`, `ip_address`, `user_agent`, `created_at`.
   - Foreign Key to `users(id)` with `ON DELETE SET NULL`.
