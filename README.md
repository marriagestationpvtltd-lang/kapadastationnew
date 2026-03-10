# Kapada Station — Clothes Rental E-Commerce

A full-featured clothes rental platform with browsing, guest & user bookings, virtual try-on, admin dashboard, and payment tracking. Built with **PHP + MySQL** (backend) and **HTML/Bootstrap 5 + Vanilla JS** (frontend) — runs on any shared hosting.

---

## Features

| Feature | Description |
|---|---|
| **Browse & Filter** | Ladies wear, gents wear, kids wear, footwear, jewelry & accessories |
| **Guest Booking** | Book without an account — just provide contact info, photo & ID |
| **User Accounts** | Register, login (JWT), manage profile & body measurements |
| **ID Verification** | Upload Aadhaar / Passport for identity verification |
| **Virtual Try-On** | Upload your photo to preview how a dress looks (authenticated users) |
| **Admin Dashboard** | Manage products, categories, rentals, guest bookings, payments & users |
| **Payment Tracking** | Record cash / UPI / bank-transfer payments; deposit & rental fee management |
| **Booking Tracker** | Track booking status with just a tracking code — no login required |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.4+ (REST API, no framework) |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Auth | JWT (HMAC-SHA256, pure PHP) |
| Frontend | HTML5, Bootstrap 5.3, Vanilla JS, Font Awesome 6 |
| Hosting | Any shared hosting with PHP & MySQL |

---

## Project Structure

```
kapada-station/
├── backend/
│   ├── config/
│   │   ├── database.php      # DB credentials & JWT secret (use env vars in prod)
│   │   └── cors.php          # CORS headers + OPTIONS handler
│   ├── helpers/
│   │   ├── jwt.php           # Pure-PHP HS256 JWT
│   │   ├── response.php      # JSON response helpers
│   │   └── upload.php        # MIME-validated file upload helper
│   ├── middleware/
│   │   └── auth.php          # requireAuth() / requireAdmin()
│   ├── api/
│   │   ├── auth/             # register, login, profile
│   │   ├── products/         # list (filtered/paginated), detail
│   │   ├── categories/       # list (grouped by type)
│   │   ├── bookings/         # create (guest+user), track (public), my
│   │   ├── payments/         # create payment record
│   │   ├── users/            # body measurements
│   │   ├── tryon/            # virtual try-on photo upload
│   │   └── admin/            # dashboard, products CRUD, bookings, payments, users
│   └── database.sql          # Full schema + sample data + admin user
├── frontend/
│   ├── index.html            # Homepage
│   ├── css/style.css         # Custom styles
│   ├── js/
│   │   ├── config.js         # API base URL
│   │   ├── auth.js           # JWT auth utilities + page protection
│   │   └── app.js            # Global helpers, navbar init
│   └── pages/
│       ├── products.html     # Product listing with filters
│       ├── product-detail.html
│       ├── booking.html      # Booking form (guest & user)
│       ├── track-booking.html # Public booking tracker
│       ├── login.html
│       ├── register.html
│       ├── profile.html      # Profile, measurements, bookings, ID docs
│       ├── tryon.html        # Virtual try-on (canvas compositing)
│       └── admin/
│           ├── dashboard.html
│           ├── products.html
│           ├── bookings.html
│           ├── payments.html
│           └── users.html
└── uploads/                  # User-uploaded files (gitignored)
    ├── products/
    ├── id_documents/
    ├── photos/
    └── tryon/
```

---

## Installation

### Requirements
- PHP 7.4 or higher (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` enabled (or any web server)

---

## Database File — Download & Upload

The complete database schema and seed data is in `backend/database.sql`.  
**Admins can also download and upload live database backups** from the admin panel at:

```
/frontend/pages/admin/database.html
```

| Action | How |
|---|---|
| **Download** | Click **Download Database Backup (.sql)** — exports all tables and data as a timestamped `.sql` file |
| **Upload / Import** | Choose a `.sql` file → click **Import SQL File** — executes all statements and restores the database |

> ⚠️ Always download a backup before importing — importing will overwrite existing data.

---

## Live Server Setup

### Option A — Shared Hosting (cPanel / Hostinger / Namecheap)

#### Step 1 — Upload Files
Upload the entire project to your hosting's `public_html/` (or a sub-directory) using
cPanel File Manager, FTP, or Git.

#### Step 2 — Create the Database
1. In cPanel open **MySQL Databases**
2. Create a new database (e.g. `kapada_station`)
3. Create a database user, note the username/password
4. Add the user to the database with **All Privileges**
5. Open **phpMyAdmin**, select the database, click **Import**, and upload `backend/database.sql`  
   *(or use the backup `.sql` file you downloaded from the admin panel)*

#### Step 3 — Configure the Backend
Edit `backend/config/database.php`:

```php
define('DB_HOST',    'localhost');
define('DB_USER',    'cpanel_user_dbuser');   // cPanel prefixes usernames
define('DB_PASS',    'your_strong_password');
define('DB_NAME',    'cpanel_user_kapada_station');
define('JWT_SECRET', 'replace-with-a-64-char-random-string');
define('BASE_URL',   'https://yourdomain.com');
```

> **Tip:** Set these as environment variables via `backend/.htaccess` instead of hardcoding
> (see [Environment Variables](#environment-variables) below).

#### Step 4 — Set Upload Permissions
In cPanel File Manager, set these folders to **permission 755** (or via SSH):

```bash
chmod 755 uploads/
chmod 755 uploads/products/ uploads/id_documents/ uploads/photos/ uploads/tryon/
```

#### Step 5 — Access the Site
| URL | Description |
|---|---|
| `https://yourdomain.com/frontend/` | Customer-facing site |
| `https://yourdomain.com/frontend/pages/admin/dashboard.html` | Admin dashboard |
| `https://yourdomain.com/frontend/pages/admin/database.html` | Database backup / restore |

---

### Option B — VPS / Dedicated Server (Ubuntu + Apache)

```bash
# 1. Install LAMP stack
sudo apt update && sudo apt upgrade -y
sudo apt install apache2 php php-mysqli php-json php-fileinfo \
     mysql-server unzip -y
sudo a2enmod rewrite
sudo systemctl restart apache2

# 2. Deploy project
sudo cp -r kapadastationnew/ /var/www/html/kapada/
sudo chown -R www-data:www-data /var/www/html/kapada/
sudo chmod -R 755 /var/www/html/kapada/uploads/

# 3. Create database
sudo mysql -u root -p <<'SQL'
CREATE DATABASE kapada_station CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kapada_user'@'localhost' IDENTIFIED BY 'StrongPass!123';
GRANT ALL PRIVILEGES ON kapada_station.* TO 'kapada_user'@'localhost';
FLUSH PRIVILEGES;
SQL

# 4. Import schema
mysql -u kapada_user -p kapada_station < /var/www/html/kapada/backend/database.sql

# 5. Virtual host (save to /etc/apache2/sites-available/kapada.conf)
# <VirtualHost *:80>
#     ServerName yourdomain.com
#     DocumentRoot /var/www/html/kapada
#     <Directory /var/www/html/kapada>
#         AllowOverride All
#         Require all granted
#     </Directory>
# </VirtualHost>
sudo a2ensite kapada.conf && sudo systemctl reload apache2
```

---

### Environment Variables

Rather than editing `database.php` directly, set these on the server.
The config file reads them automatically via `getenv()`.

| Variable | Example | Description |
|---|---|---|
| `DB_HOST` | `localhost` | MySQL host |
| `DB_USER` | `kapada_user` | MySQL username |
| `DB_PASS` | `StrongPass!123` | MySQL password |
| `DB_NAME` | `kapada_station` | Database name |
| `JWT_SECRET` | *(64-char random string)* | JWT signing secret — never share this |
| `BASE_URL` | `https://yourdomain.com` | Public URL of the site |

Generate a secure `JWT_SECRET`:
```bash
openssl rand -hex 32
```

**cPanel / shared hosting** — add to `backend/.htaccess`:
```apache
SetEnv DB_HOST     localhost
SetEnv DB_USER     cpanel_user_dbuser
SetEnv DB_PASS     your_strong_password
SetEnv DB_NAME     cpanel_user_kapada
SetEnv JWT_SECRET  your-64-char-random-secret
SetEnv BASE_URL    https://yourdomain.com
```

---

### Default Admin Login
| Field | Value |
|---|---|
| Email | `admin@kapadastationnew.com` |
| Password | `Admin@123` |

> ⚠️ **Change the admin password immediately after first login.**

---

## API Reference

All endpoints return JSON. Authenticated endpoints require `Authorization: Bearer <token>` header.

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/auth/register.php` | — | Register new user |
| POST | `/api/auth/login.php` | — | Login, returns JWT |
| GET/PUT | `/api/auth/profile.php` | User | Get/update profile |
| GET | `/api/products/list.php` | — | List products (filter, paginate) |
| GET | `/api/products/detail.php?id=X` | — | Product detail |
| GET | `/api/categories/list.php` | — | All categories grouped by type |
| POST | `/api/bookings/create.php` | Optional | Create booking (guest or user) |
| GET | `/api/bookings/track.php?tracking_code=X` | — | Public booking tracker |
| GET | `/api/bookings/my.php` | User | User's own bookings |
| POST | `/api/payments/create.php` | Admin | Record payment |
| GET/PUT | `/api/users/measurements.php` | User | Body measurements |
| POST | `/api/tryon/upload.php` | User | Virtual try-on photo upload |
| GET | `/api/admin/dashboard.php` | Admin | Stats & recent activity |
| GET/POST/PUT/DELETE | `/api/admin/products.php` | Admin | Product CRUD |
| GET/PUT | `/api/admin/bookings.php` | Admin | Booking management |
| GET | `/api/admin/payments.php` | Admin | All payments |
| GET/PUT | `/api/admin/users.php` | Admin | User management |
| GET | `/api/admin/database.php` | Admin | Download full database backup (.sql) |
| POST | `/api/admin/database.php` | Admin | Import / restore database from .sql file |

---

## Security Notes

- All SQL uses **prepared statements** — no string interpolation
- Passwords hashed with `password_hash()` / verified with `password_verify()`  
- JWT signed with **HMAC-SHA256** and compared with `hash_equals()`
- File uploads: MIME type checked via `finfo`, 5 MB limit, random filenames
- Path traversal guard in file operations using `realpath()`
- DB errors are logged server-side; only generic messages returned to client
- **In production**: set `JWT_SECRET`, `DB_PASS` as environment variables

---

## License

MIT