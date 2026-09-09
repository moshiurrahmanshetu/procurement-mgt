# Marketplace Web Installer — Complete Technical Reference

The **Procurement Management CMS Web Installer** provides a modern, secure, web-based installation wizard for deploying the application on shared hosting (cPanel, DirectAdmin), VPS, and local environments (XAMPP, WAMP, Docker).

---

## 1. Full Installation Flow

```
Step 1: System Requirements Check (installer/index.php)
  ├── Evaluates PHP 8.0+, required extensions, ini directives, and writable directories
  └── Enables progression only when all critical checks pass
         │
         ▼
Step 2: Database Setup & Connection Testing (installer/database.php)
  ├── Captures Host, Port, Database Name, Username, and Password
  ├── Live AJAX / POST Connection Test via PDO (safe error categorization)
  └── Stores validated credentials in isolated installer session
         │
         ▼
Step 3: Database Schema Import (installer/import.php)
  ├── Bundled Master Schema: database/install.sql (21 tables + static lookups)
  ├── Optional: Upload custom .sql file (max 5MB, security validated)
  ├── Detects existing application tables to prevent accidental data loss
  └── Executes schema with temporary foreign key suppression and safe quote-aware parser
         │
         ▼
Step 4: Primary Administrator Account Setup (installer/admin.php)
  ├── Captures Full Name, Username, Email, Password, and Confirmation
  ├── Strict password policy (minimum 8 characters, common password protection)
  ├── Uses password_hash(..., PASSWORD_BCRYPT)
  └── Assigns Administrator role and initializes system activity audit log
         │
         ▼
Step 5: System Finalization & Security Lock (installer/finalize.php)
  ├── Generates and saves config/config.php with rigorous PHP escaping (var_export)
  ├── Performs live PDO connection verification with newly saved configuration
  ├── Creates permanent installation lock file: config/installed.lock
  └── Cleans up sensitive credentials from installer session
         │
         ▼
Step 6: Completion & Login (installer/complete.php)
  ├── Displays installation summary and credentials confirmation
  └── Direct link to application login (auth/login.php)
```

---

## 2. Directory Structure

```
installer/
├── assets/
│   ├── css/
│   │   └── installer.css            # Restrained, modern marketplace styling
│   └── js/
│       └── installer.js             # Vanilla JS for AJAX connection test & tooltips
├── includes/
│   ├── installer-footer.php         # Reusable footer layout & scripts
│   ├── installer-functions.php      # Core helpers (PDO tester, SQL engine, config writer, lock)
│   ├── installer-header.php         # Reusable header with 5-step progress bar
│   └── requirements.php             # System environment & permissions engine
├── index.php                        # Step 1: System Requirements Check
├── database.php                     # Step 2: Database Setup & Live Tester
├── import.php                       # Step 3: Database Schema Import
├── admin.php                        # Step 4: Administrator Account Setup
├── finalize.php                     # Step 5: Finalization & Security Lock
├── complete.php                     # Step 6: Installation Complete & Login
└── README.md                        # Installer documentation & technical reference
```

---

## 3. Security Architecture & Lock Protection

1. **Every Installer Entry Point Protected:**
   Every single file in `installer/` checks `installer_is_locked()` before processing. Once `config/installed.lock` exists, the installer wizard is completely inaccessible and immediately displays a locked advisory screen.
2. **First-Run Redirection:**
   When an uninstalled system is accessed via `/` or `/auth/login.php`, the system detects the absence of `config/installed.lock` and redirects to `/installer/` without triggering database errors.
3. **Strict CSRF Tokens:**
   All POST submissions (database parameters, schema import, administrator creation) require a cryptographic CSRF token generated and verified independently of the CMS database.
4. **No Plaintext Passwords / Credential Leakage:**
   Passwords are never logged, never stored in plaintext, never displayed in output HTML, and never written into lock files or client JavaScript.
5. **Safe Configuration Generation:**
   `config/config.php` is generated using safe token escaping (`var_export()`) to prevent PHP code injection.

---

## 4. Manual Reinstallation Procedure (Admins & Developers)

For security reasons, there is **no public web-accessible reinstall button**. If a server administrator or developer needs to reinstall the CMS from scratch:

1. **Backup Existing Data:**
   Take a complete backup of the database and `config/config.php` if required.
2. **Remove the Installation Lock:**
   Delete the lock file located on the server:
   ```bash
   rm config/installed.lock
   ```
3. **Prepare an Empty Database:**
   Create a fresh empty MySQL database or drop the old tables in phpMyAdmin / MySQL CLI.
4. **Run the Installer:**
   Navigate to `http://your-domain.com/installer/` and complete the setup wizard.
