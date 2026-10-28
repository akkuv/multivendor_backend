# API Reference

Base URL: `/api/v1`

All responses use a consistent envelope:

- Success: `{ "success": true, "message": "...", "data": ... }`
- Error: `{ "success": false, "message": "...", "errors": {...} }`

Invalid/malformed requests receive JSON (a global `ForceJsonResponse` middleware), and rate-limited requests receive 429 responses.

## Authentication

| Method | Route | Auth | Description |
| --- | --- | --- | --- |
| POST | `/auth/register` | public | Register a customer |
| POST | `/auth/vendor/register` | public | Register as a vendor (upload PDF/PNG/JPG/JPG, max 5 MB) |
| POST | `/auth/login` | public, `throttle:login` (5/min) | Login; returns Bearer token |
| POST | `/auth/forgot-password` | public, `throttle:forgot-password` (3/min) | Request password reset |
| POST | `/auth/reset-password` | public | Reset password with token |
| POST | `/auth/logout` | auth | Revoke current token |
| GET | `/auth/me` | auth | Current user |
| POST | `/auth/send-email-verification` | auth, `throttle:verification` (3/min) | Send verification email |
| POST | `/auth/verify-email` | auth | Verify email |
| POST | `/auth/send-otp` | auth, `throttle:verification` (3/min) | Send OTP |
| POST | `/auth/verify-otp` | auth | Verify OTP |
| POST | `/auth/change-password` | auth | Change password |
| GET | `/auth/sessions` | auth | List active sessions |
| DELETE | `/auth/sessions/{token}` | auth | Revoke a session |

## Public / Storefront (unauthenticated)

| Method | Route | Description |
| --- | --- | --- |
| GET | `/categories` | Category tree |
| GET | `/categories/{category}` | Category detail |
| GET | `/brands` | Brands |
| GET | `/vendors` | Approved vendors |
| GET | `/vendors/{vendor}` | Vendor detail |
| GET | `/vendors/{vendor}/reviews` | Vendor reviews |
| GET | `/vendors/{vendorId}/products` | Products of a vendor |
| GET | `/search` | Search products/vendors |
| GET | `/products` | Paginated product listing |
| GET | `/products/featured` | Featured products |
| GET | `/products/deals` | Deals |
| GET | `/products/best-sellers` | Best sellers |
| GET | `/products/trending` | Trending |
| GET | `/products/compare` | Compare products |
| GET | `/products/{product}` | Product detail |
| GET | `/products/{product}/reviews` | Product reviews |
| GET | `/products/{product}/related` | Related products |
| GET | `/products/{product}/frequently-bought` | Frequently bought together |
| GET | `/products/{product}/questions` | Product questions |
| GET | `/payment-options` | Available payment methods (Razorpay, COD) |

## Customer Panel (auth + role CUSTOMER)

### Cart
| Method | Route |
| --- | --- |
| GET | `/user/cart` |
| POST | `/user/cart/items` |
| PUT | `/user/cart/items/{cartItem}` |
| DELETE | `/user/cart/items/{cartItem}` |
| DELETE | `/user/cart` |
| GET | `/user/cart/totals` |

### Addresses
| Method | Route |
| --- | --- |
| GET/POST | `/user/addresses` (apiResource) |
| GET/PUT/DELETE | `/user/addresses/{address}` |

### Orders
| Method | Route | Description |
| --- | --- | --- |
| POST | `/user/orders` | Place order. Accepts optional `idempotency_key` (≤100 chars) for checkout dedup |
| GET | `/user/orders` | My orders |
| GET | `/user/orders/{order}` | Order detail |
| POST | `/user/orders/{order}/cancel` | Cancel order |

### Payments
| Method | Route |
| --- | --- |
| POST | `/user/payments/{order}/checkout` | Initiate Razorpay/COD checkout |
| POST | `/user/payments/verify` | Verify Razorpay signature + capture |
| GET | `/user/payments/{order}/status` | Payment status |
| POST | `/payments/webhook` | Razorpay webhook (signature verified) |

### Coupons
| Method | Route |
| --- | --- |
| POST | `/user/coupons/validate` | Validate/apply a coupon |

### Wallet
| Method | Route |
| --- | --- |
| GET | `/user/wallet` | Wallet dashboard |
| GET | `/user/wallet/transactions` | Transaction list |
| GET | `/user/wallet/transactions/{transaction}` | Transaction detail (ownership-scoped) |
| POST | `/user/wallet/recharge` | Create recharge payment |
| POST | `/user/wallet/recharge/verify` | Verify + credit recharge |

### Returns & Tracking
| Method | Route |
| --- | --- |
| POST | `/user/returns` |
| GET | `/user/returns` |
| GET | `/user/returns/{productReturn}` |
| POST | `/user/returns/{productReturn}/withdraw` |
| GET | `/user/track/{order}` |

### Wishlist
| Method | Route |
| --- | --- |
| GET | `/user/wishlist` |
| POST | `/user/wishlist` |
| DELETE | `/user/wishlist` |
| DELETE | `/user/wishlist/{product}` |
| POST | `/user/wishlist/{product}/move-to-cart` |
| PUT | `/user/wishlist/{product}/notifications` |
| GET | `/user/wishlist-notifications` |
| POST | `/user/wishlist-notifications/read` |

### Notifications
| Method | Route |
| --- | --- |
| GET | `/user/notifications` |
| GET | `/user/notifications/unread-count` |
| POST | `/user/notifications/read-all` |
| PUT | `/user/notifications/{notification}/read` |
| DELETE | `/user/notifications/{notification}` |

### Reviews & Questions
| Method | Route |
| --- | --- |
| GET | `/user/reviews` (mine) |
| POST | `/user/reviews` |
| PUT | `/user/reviews/{review}` |
| DELETE | `/user/reviews/{review}` |
| GET | `/user/vendor-reviews` (mine) |
| POST | `/user/vendor-reviews` |
| DELETE | `/user/vendor-reviews/{vendorReview}` |
| POST | `/user/products/{product}/questions` |
| GET | `/user/questions` (mine) |
| DELETE | `/user/questions/{productQuestion}` |

### Personalization
| Method | Route |
| --- | --- |
| GET | `/user/recently-viewed` |
| POST | `/user/products/{product}/view` |
| GET | `/user/buy-again` |
| GET | `/user/recommendations` |

## Vendor Panel (auth + role VENDOR + approved vendor)

| Method | Route | Description |
| --- | --- | --- |
| GET | `/vendor/dashboard` | Stats |
| GET/POST | `/vendor/products` | List / create |
| GET/PUT/DELETE | `/vendor/products/{product}` | Manage **own** product (others → 404) |
| PUT | `/vendor/products/{product}/toggle` | Active/inactive |
| GET | `/vendor/products/{product}/inventory/transactions` | Inventory ledger |
| POST | `/vendor/products/{product}/inventory/adjust` | Adjust stock |
| GET | `/vendor/orders` | Orders for my products |
| GET | `/vendor/orders/{vendorOrder}` | Order detail |
| PUT | `/vendor/orders/{vendorOrder}/status` | Update order status |
| PUT | `/vendor/order-items/{orderItem}/status` | Update item status |
| POST | `/vendor/orders/{vendorOrder}/ship` | Create shipment |
| GET | `/vendor/returns` | Returns |
| GET | `/vendor/returns/{productReturn}` | Return detail |
| GET | `/vendor/questions` | Questions on my products |
| POST | `/vendor/questions/{productQuestion}/answer` | Answer a question |
| GET | `/vendor/shipments` | Shipments |
| GET | `/vendor/shipments/{shipment}` | Shipment detail |
| GET | `/vendor/shipments/{shipment}/events` | Tracking events |
| POST | `/vendor/shipments/{shipment}/pickup` | Schedule pickup |
| POST | `/vendor/shipments/{shipment}/retry` | Retry (webhook consumed) |
| GET | `/vendor/wallet` | Wallet dashboard |
| GET | `/vendor/wallet/balance` | Balance |
| GET | `/vendor/wallet/transactions` | Transaction list |
| GET | `/vendor/payouts` | Payout list |
| POST | `/vendor/payouts` | Request payout (balance-checked in tx) |
| GET | `/vendor/payouts/{payout}` | Payout detail |
| GET | `/vendor/reports/{type}` | Report |
| GET | `/vendor/reports/{type}/export` | Export CSV |
| GET/PUT | `/vendor/profile` | Vendor profile |

## Super Admin Panel (auth + role SUPER_ADMIN)

All under `/admin`. Every route is admin-only; any non-admin is rejected with 403 before the handler runs.

### Core
| Method | Route |
| --- | --- |
| GET | `/admin/dashboard` |
| GET | `/admin/me` |
| GET | `/admin/reports/{type}` |
| GET | `/admin/reports/{type}/export` |
| GET | `/admin/audit-logs` |

### Vendors
| Method | Route |
| --- | --- |
| GET/POST | `/admin/vendors` |
| GET/PUT/DELETE | `/admin/vendors/{vendor}` |
| PUT | `/admin/vendors/{vendor}/approve` / `activate` / `suspend` / `reject` |

### Products
| Method | Route |
| --- | --- |
| GET | `/admin/products` |
| GET | `/admin/products/{product}` |
| PUT | `/admin/products/{product}/approve` / `reject` / `publish` / `unpublish` / `feature` / `best-seller` |
| DELETE | `/admin/products/{product}` |

### Reviews / Questions (moderation)
| Method | Route |
| --- | --- |
| GET | `/admin/reviews`, `/admin/vendor-reviews`, `/admin/questions` |
| PUT | approve/hide/unhide, moderate images, report/approve |
| GET/POST | `/admin/questions/{question}/report` |

### Catalog
| Method | Resource |
| --- | --- |
| CRUD + toggle | `/admin/categories`, `/admin/brands`, `/admin/attributes`, `/admin/attribute-values`, `/admin/colors`, `/admin/size-groups`, `/admin/sizes` |

### Marketing / Content
| Method | Route |
| --- | --- |
| CRUD + toggle | `/admin/coupons` (+usage, history), `/admin/email-templates`, `/admin/cms-pages`, `/admin/banners`, `/admin/testimonials`, `/admin/homepage-sections` |

### Orders & Customers
| Method | Route |
| --- | --- |
| GET | `/admin/orders`, `/admin/orders/{order}` |
| PUT | `/admin/orders/{order}/status` |
| GET | `/admin/vendor-orders`, `/admin/vendor-orders/{vendorOrder}` |
| PUT | `/admin/vendor-orders/{vendorOrder}/status` |
| GET | `/admin/users` |
| GET | `/admin/users/{user}` |
| PUT | `/admin/users/{user}/toggle-active` / `block` / `unblock` |

### Refunds, Returns, Financials
| Method | Route |
| --- | --- |
| GET | `/admin/refunds` |
| POST | `/admin/refunds/{refund}/process` | Process refund (idempotent, Completed-guarded) |
| GET | `/admin/returns` / `/admin/returns/{productReturn}` |
| PUT | `/admin/returns/{productReturn}/status` |
| GET | `/admin/ledger`, `/admin/ledger/{entry}` |
| GET | `/admin/commissions` |
| PUT | `/admin/commissions/{commissionSetting}` |
| CRUD | `/admin/commission-rules` |
| GET | `/admin/settlements` (commissions) |
| POST | `/admin/settlements/run` | Run settlement for eligible delivered orders |
| POST | `/admin/settlements/{commission}/reverse` | Reverse a settlement |
| GET | `/admin/payouts` |
| PUT | `/admin/payouts/{payout}/approve` / `mark-paid` / `reject` |

### Settings
| Method | Route |
| --- | --- |
| GET/PUT | `/admin/payment-settings` (secrets returned masked) |
| GET/PUT | `/admin/shiprokets/settings` |
| POST | `/admin/shiprokets/test` |
| GET/PUT | `/admin/general-settings` |
| GET/PUT | `/admin/branding-settings` |
| GET/PUT | `/admin/security-settings` |

### Shipping (admin)
| Method | Route |
| --- | --- |
| GET | `/admin/shipments`, `/admin/shipments/{shipment}` |
| GET | `/admin/shipments/{shipment}/events` |
| POST | `/admin/shipments/{shipment}/pickup` / `retry` |

## Webhooks (signature-verified)
| Method | Route |
| --- | --- |
| POST | `/payments/webhook` | Razorpay (HMAC-SHA256) |
| POST | `/shipments/webhook` | Shiprocket (fail-closed without secret) |

## Checkout Idempotency

`POST /user/orders` accepts an optional `idempotency_key`. When a second request is made with the **same key for the same user** before a first order completed, the existing order is returned instead of creating a duplicate. A unique index on `(user_id, idempotency_key)` guarantees this behavior.
</content>
