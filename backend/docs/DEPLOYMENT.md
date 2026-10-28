# Deployment

Production-hardening is baked into the config defaults. This guide covers a container/Fortify-CLI or traditional server deployment of the Laravel backend.

## Hardening already applied

- `APP_DEBUG=false` by default in `.env.example`.
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` (no Redis required to be safe).
- Sanctum tokens expire (`SANCTUM_TOKEN_EXPIRATION`, default 7 days).
- CORS restricted to `CORS_ALLOWED_ORIGINS`.
- Role middleware enforces CUSTOMER / VENDOR / SUPER_ADMIN separation; all admin financial routes return 403 to non-admins.
- Secrets encrypted at rest (`PaymentSetting`, `ShiprocketSetting` use `encrypted` casts).
- Payments & shipping webhooks fail closed on invalid/missing signatures.
- Rate limiting on login (5/min), password reset (3/min), and verification/OTP (3/min).
- Shared financial operations use database transactions, row/wallet locks, unique constraints, and idempotency keys to prevent double-charging/double-crediting.

## Pre-deployment checklist

```bash
# On your deployment machine / CI
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
php artisan migrate --force
php artisan storage:link
```

Set in `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
CORS_ALLOWED_ORIGINS=https://yourstore.com,https://admin.yourstore.com
FRONTEND_URL=https://yourstore.com
SANCTUM_STATEFUL_DOMAINS=yourstore.com
SANCTUM_TOKEN_EXPIRATION=10080
RAZORPAY_KEY_ID=...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
SHIPROCKET_TOKEN=...
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

Create the initial super admin (only once):

```bash
ADMIN_EMAIL=admin@yourdomain.com ADMIN_PASSWORD='a-strong-secret' \
php artisan db:seed --class=SuperAdminSeeder --force
```

Then remove/blank `ADMIN_PASSWORD` from `.env` so the seeder is inert going forward.

## Queue worker

The app uses the database queue for mail and notifications. Run workers with:

```bash
php artisan queue:work --tries=3
```

Use a process supervisor (systemd/supervisord) or a managed process (Laravel Cloud / Forge) to keep it running.

## Scheduled tasks

Set up the scheduler (add `* * * * * php /path/artisan schedule:run` to cron):

```bash
php artisan schedule:work   # foreground; or cron for background
```

## After deploy — cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Webhooks (must be public)

Register these public, signature-verified endpoints with your providers:

```
POST https://api.yourdomain.com/api/v1/payments/webhook    # Razorpay (HMAC-SHA256)
POST https://api.yourdomain.com/api/v1/shipments/webhook   # Shiprocket (fail-closed)
```

## Scaling & performance notes

- Default stores (`database`) are safe but database-backed; for higher throughput switch `CACHE_STORE`/`QUEUE_CONNECTION` to Redis and point `REDIS_*` at a managed instance.
- Media: set `FILESYSTEM_DISK=s3` and configure `AWS_*` to offload image storage from the web server.
- Response caching on the public catalog (categories/products) is a recommended next step; add cache invalidation on product/vendor/stock changes.

## Security reminders

- Keep `APP_KEY`, Razorpay secrets, Shiprocket token, and DB credentials in a secret manager, never in the committed `.env`.
- Rotate `RAZORPAY_WEBHOOK_SECRET` and `SHIPROCKET_TOKEN` periodically.
- Run on HTTPS only; enforce HSTS at the proxy.
</content>
