# Environment Variables

All configuration is read from environment variables (`.env`, never committed). A production-safe template lives at `.env.example`.

## Core Application

| Variable | Default | Description |
| --- | --- | --- |
| `APP_NAME` | `Multi Vendor Ecommerce` | Application name |
| `APP_ENV` | `production` | `local`, `production`, `testing` |
| `APP_KEY` | *(empty — required)* | Laravel app key. Run `php artisan key:generate` |
| `APP_DEBUG` | `false` | Must be `false` in production |
| `APP_URL` | `http://localhost:8000` | Public base URL of the API |
| `APP_LOCALE` | `en` | Default locale |
| `APP_FALLBACK_LOCALE` | `en` | Fallback locale |
| `APP_MAINTENANCE_DRIVER` | `file` | Maintenance mode driver |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |

## Logging

| Variable | Default | Description |
| --- | --- | --- |
| `LOG_CHANNEL` | `stack` | Log channel stack |
| `LOG_STACK` | `single` | Stack channels |
| `LOG_LEVEL` | `warning` | Minimum log level |

## Database

| Variable | Default | Description |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Database driver |
| `DB_HOST` | `127.0.0.1` | DB host |
| `DB_PORT` | `3306` | DB port |
| `DB_DATABASE` | `ecommerce` | Database name |
| `DB_USERNAME` | `root` | DB user |
| `DB_PASSWORD` | *(empty)* | DB password |

## Session / Cache / Queue / Broadcast

| Variable | Default | Description |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | Session store (`database` recommended) |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes |
| `SESSION_ENCRYPT` | `true` | Encrypt session data |
| `BROADCAST_CONNECTION` | `log` | Broadcast driver |
| `FILESYSTEM_DISK` | `local` | Default storage disk |
| `QUEUE_CONNECTION` | `database` | Queue driver (`database` recommended; `redis` for scale) |
| `CACHE_STORE` | `database` | Cache store (`database`; `redis`/`file` alternatives) |

## Redis

Used only if `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis`:

| Variable | Default |
| --- | --- |
| `REDIS_CLIENT` | `phpredis` |
| `REDIS_HOST` | `127.0.0.1` |
| `REDIS_PASSWORD` | `null` |
| `REDIS_PORT` | `6379` |

## Mail

| Variable | Default | Description |
| --- | --- | --- |
| `MAIL_MAILER` | `log` | `smtp`, `log`, `mailgun`, etc. |
| `MAIL_HOST` | `127.0.0.1` | SMTP host |
| `MAIL_PORT` | `2525` | SMTP port |
| `MAIL_USERNAME` | *(empty)* | SMTP user |
| `MAIL_PASSWORD` | *(empty)* | SMTP password |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | From address |
| `MAIL_FROM_NAME` | `${APP_NAME}` | From name |
| `FRONTEND_URL` | `http://localhost:3000` | Frontend URL used in email links (reset/verify) |

## AWS / S3

Settings if `FILESYSTEM_DISK=s3` for product image/media storage:

| Variable | Default |
| --- | --- |
| `AWS_ACCESS_KEY_ID` | *(empty)* |
| `AWS_SECRET_ACCESS_KEY` | *(empty)* |
| `AWS_DEFAULT_REGION` | `us-east-1` |
| `AWS_BUCKET` | *(empty)* |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` |

## Payments (Razorpay)

| Variable | Default | Description |
| --- | --- | --- |
| `RAZORPAY_KEY_ID` | *(empty)* | Razorpay key ID |
| `RAZORPAY_KEY_SECRET` | *(empty)* | Razorpay key secret (encrypted at rest) |
| `RAZORPAY_WEBHOOK_SECRET` | *(empty)* | Webhook secret for HMAC-SHA256 verification |
| `RAZORPAY_BASE_URL` | `https://api.razorpay.com/v1` | Razorpay API base |

## Shipping (Shiprocket)

Configured at admin panel → Shipping Settings (`SHIPROCKET_TOKEN` is stored encrypted). These env vars provide initial defaults:

| Variable | Default | Description |
| --- | --- | --- |
| `SHIPROCKET_TOKEN` | *(empty)* | Auth token |
| `SHIPROCKET_EMAIL` | *(empty)* | Login email (token generation) |
| `SHIPROCKET_PASSWORD` | *(empty)* | Login password |
| `SHIPROCKET_BASE_URL` | `https://apiv2.shiprocket.in/v1/external` | API base |

## Security & CORS

| Variable | Default | Description |
| --- | --- | --- |
| `SANCTUM_TOKEN_EXPIRATION` | `10080` | Sanctum token lifetime in **minutes** (default 7 days) |
| `CORS_ALLOWED_ORIGINS` | *(empty)* | Comma-separated frontend origins, e.g. `https://app.example.com,https://admin.example.com` |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost` | Comma-separated SPA domains |

## Admin Bootstrapping

Used by `php artisan db:seed --class=SuperAdminSeeder` to create the first super admin. The seeder does **nothing** when `ADMIN_PASSWORD` is empty.

| Variable | Default | Description |
| --- | --- | --- |
| `ADMIN_NAME` | `Admin` | Super admin name |
| `ADMIN_EMAIL` | `admin@example.com` | Super admin email (unique key) |
| `ADMIN_PASSWORD` | *(empty)* | Super admin password. Empty → seeder skipped. **Set a strong value in production** |

## Other

| Variable | Default | Description |
| --- | --- | --- |
| `COD_CHARGE` | `0` | Flat COD handling charge |
</content>
