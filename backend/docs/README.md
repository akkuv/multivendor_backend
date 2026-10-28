# Multi-Vendor Ecommerce — Backend Documentation

This directory contains the technical documentation for the Laravel 13 backend of the Multi-Vendor Ecommerce platform.

A multi-vendor marketplace with customer storefront, vendor dashboard, super-admin panel, Razorpay payments, wallet & commission management, and Shiprocket shipping.

## Contents

| Document | Purpose |
| --- | --- |
| [API Reference](API.md) | Complete REST API surface (public, customer, vendor, admin) |
| [Database / ERD](DATABASE.md) | Schema overview and entity relationships |
| [Environment Variables](ENVIRONMENT_VARIABLES.md) | Every env var and its meaning |
| [Installation](INSTALLATION.md) | Local setup from a fresh clone |
| [Testing](TESTING.md) | How to run and write tests |
| [Deployment](DEPLOYMENT.md) | Production deployment guide |
| [Project Structure](PROJECT_STRUCTURE.md) | Layout of the codebase |

## Quick Stats

- **Framework:** Laravel 13, PHP ^8.3
- **Auth:** Laravel Sanctum (token), role middleware (CUSTOMER / VENDOR / SUPER_ADMIN)
- **Payments:** Razorpay (cards, UPI, wallets, netbanking) + Cash on Delivery
- **Shipping:** Shiprocket (v2 API) with webhook-based tracking
- **Queues / Cache / Session:** database driver by default (production-safe)
- **Tests:** 277 feature/unit tests, 1225 assertions (all green)

## Roles

| Role | Panel | Base prefix |
| --- | --- | --- |
| CUSTOMER | Customer app | `/api/v1/user/*` |
| VENDOR | Vendor dashboard | `/api/v1/vendor/*` (requires approved vendor) |
| SUPER_ADMIN | Admin panel | `/api/v1/admin/*` |

## Authentication

All requests use a Bearer token:

```http
Authorization: Bearer <token>
```

Obtain a token via `POST /api/v1/auth/login` (or registration). The token lifetime is controlled by `SANCTUM_TOKEN_EXPIRATION` (default 7 days / 10080 minutes).
</content>
