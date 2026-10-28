# Installation

Local setup from a fresh clone. Requires **PHP ^8.3**, **Composer**, and a database (MySQL recommended; tests use SQLite/MySQL in-memory as configured).

## 1. Clone & install dependencies

```bash
cd backend
composer install
```

## 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` to set your local database credentials:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=multi_vendor
DB_USERNAME=root
DB_PASSWORD=
```

For a quick local experience you may also set:

```dotenv
APP_ENV=local
APP_DEBUG=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
MAIL_MAILER=log
```

(Production notes: keep the production-safe defaults from `.env.example`.)

## 3. Migrate & seed

```bash
php artisan migrate
```

Create a super admin by setting the admin env values, then run:

```bash
ADMIN_NAME="Admin" \
ADMIN_EMAIL="admin@example.com" \
ADMIN_PASSWORD="ChangeMe123!" \
php artisan db:seed --class=SuperAdminSeeder
```

Registered vendors are approved from the admin panel (`admin/vendors/{vendor}/approve`).

## 4. Storage link

```bash
php artisan storage:link
```

## 5. Run the server

```bash
php artisan serve
```

The API is available at `http://localhost:8000/api/v1`.

## 6. Queue worker (required for mail/notifications in non-log environments)

```bash
php artisan queue:work
```

## Payment & Shipping

- **Razorpay:** set `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`, or configure at admin → Payment Settings. Point the Razorpay webhook at `POST /api/v1/payments/webhook`.
- **Shiprocket:** configure at admin → Shipping Settings; the Shiprocket webhook targets `POST /api/v1/shipments/webhook`.
- **COD:** enabled by default with a `COD_CHARGE` of `0`.
</content>
