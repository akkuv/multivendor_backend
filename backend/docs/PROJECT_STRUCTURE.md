# Project Structure

Layout of the Laravel backend.

```
backend/
├── app/
│   ├── Console/              # Artisan commands, scheduled tasks
│   ├── Enums/                # UPPER_SNAKE status/role enums
│   ├── Exceptions/           # Exception → HTTP response mapping
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Auth/         # Customer + vendor auth (register/login/verify)
│   │   │   ├── User/         # Customer panel + public storefront controllers
│   │   │   ├── Vendor/       # Vendor dashboard controllers
│   │   │   ├── SuperAdmin/   # Admin panel controllers
│   │   │   ├── NotificationController.php
│   │   │   ├── PaymentController.php
│   │   │   └── ShipmentWebhookController.php
│   │   ├── Middleware/       # ForceJsonResponse, RoleMiddleware, ThrottleRequests, approved.vendor
│   │   └── Requests/         # FormRequest validation (PlaceOrderRequest, VendorRegisterRequest, ...)
│   ├── Models/               # Eloquent models ($fillable, casts, relationships)
│   ├── Providers/
│   │   ├── AppServiceProvider.php   # rate-limiters, policy binding
│   │   └── ...
│   ├── Services/             # Domain logic (see below)
│   └── Mail/Exceptions/Notifications/  # transactional email templates
├── config/                   # cors.php, sanctum.php, payment.php, shiprocket.php, ...
├── database/
│   ├── factories/            # model factories for tests
│   ├── migrations/           # anonymous migrations (full schema)
│   └── seeders/              # SuperAdminSeeder (env-driven, inert when no password)
├── docs/                     # this documentation set
├── routes/
│   └── api_v1.php            # all API v1 routes (public/customer/vendor/admin/webhooks)
├── tests/
│   ├── Feature/              # HTTP feature tests (incl. SecurityAuthorizationTest)
│   └── Unit/                 # service unit tests
└── .env.example              # production-safe environment template
```

## Service layer (key domains)

| Service | Responsibility |
| --- | --- |
| `Services/Order/` | Order creation (idempotency), lifecycle, status transitions, `VendorSettlementService` (commission freezing/settlement/reversal) |
| `Services/Payment/` | Razorpay orders, signature verification, capture, recharge verification, webhook handling; `finalizePayment`/verify idempotency |
| `Services/Cart/` | Cart build, totals, pricing |
| `Services/Coupon/` | Coupon validation/application |
| `Services/Refund/` | Refund processing (Completed-guarded idempotency) |
| `Services/Wallet/` | Customer wallet credit/debit/holds (per-reference dedup), `VendorPayoutService` + `VendorWalletService` (balance lock) |
| `Services/Product/` | Product CRUD, ownership checks (`assertOwnsProduct` → 404) |
| `Services/Shipping/` | Shiprocket integration, shipments, webhooks (fail-closed) |
| `Services/Return/` | Return request lifecycle |
| Notification/report/audit services | In-app notifications, reports + CSV export, immutable audit logs |

## Conventions

- **Enums:** `UPPER_SNAKE` values, `->value` for storage; TitleCase keys.
- **Models:** `$fillable` + `casts` declared explicitly; relationships on the model.
- **Controllers:** thin; delegate to services; respond via `paginated()` / `success()` / `error()` helpers.
- **Auth/roles:** routes use `auth:sanctum` + `role:<ROLE>` middleware; vendor panel additionally requires `approved.vendor`.
- **Migrations:** anonymous; grouped by date + numeric sequence.
</content>
