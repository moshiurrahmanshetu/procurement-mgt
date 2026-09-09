# Marketplace Web Installer — Phase 01: Foundation & Requirements Check

Welcome to the **Procurement Management CMS Web Installer**. This installer provides a web-based, zero-command-line installation workflow for shared hosting, cPanel, and VPS buyers.

---

## 1. Phase 01 Scope & Status

### What Phase 01 Does:
- **Isolated Installer Bootstrap:** Runs independently of the CMS database and application session (`installer/includes/installer-functions.php`).
- **Comprehensive Requirements Checker:** Evaluates PHP version, extensions, ini directives, and writable storage paths (`installer/includes/requirements.php`).
- **Modern Marketplace UI:** Professional Bootstrap 5 + Bootstrap Icons multi-step wizard interface with solid business styling (`installer/assets/css/installer.css`).
- **Installation Lock Detection:** Safely determines whether the system has already been installed via `config/installed.lock` (`isInstallationLocked()`).
- **Dynamic Path & URL Resolution:** Fully portable across domain roots and subdirectories with zero hardcoded filesystem paths or URLs.

### What Phase 01 Does NOT Do:
- Does NOT connect to MySQL or write database credentials (reserved for **Phase 02**).
- Does NOT import database schemas or tables (reserved for **Phase 03**).
- Does NOT create administrator accounts (reserved for **Phase 04**).
- Does NOT overwrite `config/config.php` (reserved for **Phase 05**).
- Does NOT create an active lock file blocking fresh installs (reserved for **Phase 06**).

---

## 2. Directory Structure

```
installer/
├── assets/
│   ├── css/
│   │   └── installer.css            # Clean, responsive installer styling
│   └── js/
│       └── installer.js             # Vanilla JS for interactive elements & tooltips
├── includes/
│   ├── installer-footer.php         # Reusable HTML footer & script imports
│   ├── installer-functions.php      # Isolated helper functions (URL, path, lock checks)
│   ├── installer-header.php         # Reusable HTML header & 5-step progress bar
│   └── requirements.php             # Core environment & permission verification engine
├── database.php                     # Step 2 informational placeholder for Phase 02
├── index.php                        # Step 1: System requirements entry point
└── README.md                        # Installer documentation & technical reference
```

---

## 3. System Requirements Checked

| Component | Required Value | Critical / Warning | Purpose |
|---|---|---|---|
| **PHP Version** | `8.0.0+` | **Critical** | Pure native PHP 8 syntax & performance |
| **file_uploads** | `On / Enabled` | **Critical** | Document, logo, and avatar file uploads |
| **upload_max_filesize** | `2M+` | Info / Recommendation | File upload size tolerance |
| **memory_limit** | `128M+` | Info / Recommendation | Report generation memory allocation |
| **PDO Extension** | `Enabled` | **Critical** | Secure prepared statements |
| **PDO MySQL Driver** | `Enabled` | **Critical** | MySQL / MariaDB database connectivity |
| **Session Support** | `Enabled` | **Critical** | User authentication & CSRF validation |
| **JSON Extension** | `Enabled` | **Critical** | Data interchange & structured fields |
| **mbstring Extension** | `Enabled` | **Critical** | UTF-8 multibyte character processing |
| **fileinfo Extension** | `Enabled` | **Critical** | Server-side MIME verification |
| **OpenSSL Support** | `Enabled` | Recommended | Cryptographic token generation |
| **GD Library** | `Enabled` | Recommended | Image rendering & manipulation |
| **ctype Extension** | `Enabled` | Recommended | String type validation |
| **config/ Directory** | `Writable` | **Critical** | Future generation of configuration file |
| **assets/uploads/ Directory** | `Writable` | **Critical** | Attachment & asset storage |
| **assets/uploads/avatars/** | `Writable` | **Critical** | User avatar image storage |
| **assets/uploads/logos/** | `Writable` | **Critical** | Company branding logo storage |

---

## 4. Local Access & Verification

To access the installation wizard on a local development server:
```
http://localhost/procurement-mgt/installer/
```

- If all mandatory requirements pass: The **"Continue to Database Setup"** button is enabled.
- If any mandatory requirement fails: The button is disabled, and clear resolution instructions are displayed in red.
- If the application is locked via `config/installed.lock`: The wizard displays a locked notification screen and redirects to login.
