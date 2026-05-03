# 🔐 PasswordVault

> A secure, elegant local password & notes manager — zero cloud dependency.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![SQLite](https://img.shields.io/badge/SQLite-3-green.svg)](https://sqlite.org)

---

## ✨ Features

- **Password Management** — Store, categorize, search, and retrieve passwords with AES-256-CBC encryption
- **Notes/Notepad** — Rich-text notes with mood tracking, pinning, and view counts
- **Local-Only Storage** — All data stays on your machine, zero cloud dependency
- **6 Themes** — Deep Space Purple · Ocean Blue · Emerald Green · Sunset Orange · Rose Pink · Light Mode
- **Category System** — Customizable categories for passwords and notes
- **Responsive Design** — Works across desktop and mobile devices

---

## 🛡️ Security

| Feature | Implementation |
|---------|---------------|
| Encryption | AES-256-CBC with HMAC-SHA256 authentication |
| Master Password | `password_hash()` / `password_verify()` |
| Session Timeout | Auto logout after 30 min inactivity |
| CSRF Protection | Per-session tokens with `hash_equals()` |
| Brute Force Shield | 5 failed attempts → 60-second lockout |
| Route Guard | `router.php` blocks direct access to sensitive files |

---

## 📂 Project Structure

```
PasswordVault/
├── index.php              # Main entry: routing, login, main UI, AJAX
├── config.php             # Core config: encryption, DB, security
├── router.php             # PHP built-in server route guard
├── upload.php             # Image upload handler
├── assets/
│   ├── app.js             # Frontend: rich text editor, UI interactions
│   └── style.css          # Styling: theme system, responsive layout
├── data/
│   ├── passwords.db       # SQLite database
│   ├── .master_key         # AES-256 key (hidden)
│   └── index.php           # Directory protection
├── uploads/               # User uploaded images
│   └── index.php           # Directory protection
└── docs/
    └── API.md             # API documentation
```

---

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+
- PHP extensions: `pdo_sqlite`, `openssl`

### Run

```bash
# Clone or download the project
cd PasswordVault

# Start PHP built-in server
php -S localhost:8080 router.php

# Open in browser
open http://localhost:8080
```

> **First run:** Set your master password (minimum 4 characters).

### Production Deployment (Recommended)

```nginx
# Nginx config example
server {
    listen 443 ssl;
    server_name your-domain.com;
    root /var/www/PasswordVault;
    index index.php;

    # Deny access to sensitive files
    location ~ /\.(db|master_key|migrated) { deny all; }
    location = /config.php { deny all; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 🗄️ Database Schema

### `passwords` — Password Records

| Field | Type | Description |
|-------|------|-------------|
| `id` | INTEGER | Primary key |
| `title` | TEXT | Name (required) |
| `category` | TEXT | Category, default "Default" |
| `username` | TEXT | Account / username |
| `password_enc` | TEXT | AES-256 encrypted password |
| `url` | TEXT | Associated URL |
| `notes` | TEXT | Remarks |
| `icon` | TEXT | Emoji icon, default 🔑 |
| `color` | TEXT | Card color, default #8b5cf6 |
| `created_at` | DATETIME | Creation time |
| `updated_at` | DATETIME | Update time |

### `posts` — Notes

| Field | Type | Description |
|-------|------|-------------|
| `id` | INTEGER | Primary key |
| `title` | TEXT | Title (required) |
| `content` | TEXT | Rich text content (HTML) |
| `category` | TEXT | Category, default "General" |
| `mood` | TEXT | Mood emoji, default 📝 |
| `is_pinned` | INTEGER | Pinned flag (0/1) |
| `views` | INTEGER | View count |
| `created_at` | DATETIME | Creation time |
| `updated_at` | DATETIME | Update time |

### `categories` — Category Management

| Field | Type | Description |
|-------|------|-------------|
| `id` | INTEGER | Primary key |
| `name` | TEXT | Category name (unique) |
| `icon` | TEXT | Emoji icon |
| `color` | TEXT | Color |
| `type` | TEXT | `pwd` or `note` |
| `sort_order` | INTEGER | Sort order |

### `settings` — System Settings

| Field | Type | Description |
|-------|------|-------------|
| `key` | TEXT | Setting key (primary) |
| `value` | TEXT | Setting value |

---

## 📡 API Endpoints

| Endpoint | Method | Params | Description |
|----------|--------|--------|-------------|
| `?reveal=1&id=X` | GET | Auth required | Decrypt & return password plaintext |
| `?ajax=view_note&id=X` | GET | Auth required | Increment note view count |
| `?ajax=categories&type=pwd\|note` | GET | Auth required | Get category list |

---

## 🗂️ Default Categories

**Password Categories:**
Default · Social💬 · Work💼 · Finance💰 · Shopping🛒 · Email📧 · Gaming🎮 · Other📦

**Note Categories:**
General📝 · Mood💭 · Reading📚 · Travel✈️ · Food🍜 · Study📖 · Other📦

---

## 📝 Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0 | 2026 | Unified password vault + notepad, theme system, AES-256 migration |
| 1.x | Early | Initial release with old key encryption |

---

## ⚠️ Security Notes

1. **Do NOT** directly access `config.php` or files under `data/`
2. On first deployment, consider removing `.migrated_v2` in `data/` to trigger full re-migration
3. For production use, enable **HTTPS** and use Nginx/Apache instead of PHP built-in server
4. **Backup** `data/passwords.db` and `data/.master_key` regularly

---

## 📄 License

MIT License — Free for personal and commercial use.

---

## 👤 Author

**六斤Libra**
- Email: 528.Libra@gmail.com

---

*Your data, your control. No cloud, no tracking, just security.*