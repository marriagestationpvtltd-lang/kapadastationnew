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

### Step 1 — Upload Files
Upload the entire project to your hosting's `public_html` (or the desired subdirectory).

### Step 2 — Create the Database
1. Open **phpMyAdmin** (or any MySQL client)
2. Create a new database named `kapada_station` (or any name)
3. Import `backend/database.sql`

### Step 3 — Configure the Backend
Edit `backend/config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'kapada_station');
define('JWT_SECRET', 'change-this-to-a-long-random-string');
define('BASE_URL', 'https://yourdomain.com');
```

> **Tip (Production):** Set secrets via environment variables instead of hardcoding — the config already checks `getenv()` first.

### Step 4 — Set Upload Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/products/ uploads/id_documents/ uploads/photos/ uploads/tryon/
```

### Step 5 — Configure Frontend Base URL
Edit `frontend/js/config.js`:
```javascript
const API_BASE = 'https://yourdomain.com/backend/api';
const UPLOAD_BASE = 'https://yourdomain.com/uploads';
```

### Step 6 — Access the Site
| URL | Description |
|---|---|
| `https://yourdomain.com/frontend/` | Customer-facing site |
| `https://yourdomain.com/frontend/pages/admin/dashboard.html` | Admin dashboard |

### Default Admin Login
| Field | Value |
|---|---|
| Email | `admin@kapadadstation.com` |
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