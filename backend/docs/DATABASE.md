# Database / ERD

The schema is defined entirely by anonymous migrations in `database/migrations/`. This document summarises the core entities and their relationships.

## Entity Relationship Overview

```
users ─ has one ─ vendor_profiles
  │                 (user_id unique)
  ├─ has many ─ addresses
  ├─ has many ─ carts (one active) ─ has many ─ cart_items
  ├─ has many ─ wishlist_items
  ├─ has one ─ wallets ─ has many ─ wallet_transactions
  │                        └─ has many ─ wallet_holds (from COD)
  ├─ has many ─ orders ─ has many ─ order_items
  │                        └─ has many ─ vendor_orders
  │                                        └─ has many ─ vendor_order_items
  ├─ has many ─ payments ─ has many ─ payment_transactions / payment_webhooks
  ├─ has many ─ reviews, vendor_reviews, product_questions/product_answers
  └─ has many ─ verification_codes, personal_access_tokens (Sanctum)

categories ─ has many ─ products ─ has many ─ product_images
brands ─ has many ─ products            ├─ product_videos, product_variants
attributes ─ has many ─ attribute_values (product attributes)
size_groups ─ has many ─ sizes
colors

products ─ has many ─ inventory_transactions
vendors (vendor_profiles) ─ has many ─ products, vendor_wallets
vendor_wallets ─ has many ─ vendor_wallet_transactions
vendor_orders ─ settled into ─ vendor_commissions ─ has many ─ commission_transactions
vendor_payouts ─ from vendor wallet
orders ─ has many ─ coupons (coupon_usages), coupons
wallets ─ credit/debit ─ admin_financial_ledgers
shipments ─ has many ─ shipment_events / shipment_webhooks
product_returns ─ has many ─ refunds
```

## Core Tables

| Table | Purpose |
| --- | --- |
| `users` | Customers, vendors, super admins (`role`, `is_active`, email/phone verification) |
| `vendor_profiles` | Vendor KYC, approval state, shipping details |
| `categories` / `brands` | Catalog taxonomy |
| `attributes` / `attribute_values` | Product attributes |
| `size_groups` / `sizes` / `colors` | Variant dimensions |
| `products` | Products (vendor owner, price, stock, status, featured/best-seller flags) |
| `product_variants` / `product_images` / `product_videos` | Variants and media |
| `inventory_transactions` | Stock ledger (indexed on `reference_type, reference_id`) |
| `carts` / `cart_items` | Shopping cart |
| `wishlist_items` | Wishlist (+ back-in-stock notification flag) |
| `addresses` | Customer addresses |
| `coupons` / `coupon_usages` | Discounts and usage (customer/vendor/item/category/min/category conditions) |
| `orders` / `order_items` | Orders (idempotency_key, pricing snapshot, status) |
| `order_addresses` / `order_status_histories` | Order shipping addrs + status timeline |
| `vendor_orders` / `vendor_order_items` | Per-vendor fulfillment split |
| `payments` / `payment_transactions` / `payment_webhooks` | Razorpay + COD payment records |
| `wallets` / `wallet_transactions` / `wallet_holds` | Customer wallet + ledger + holds |
| `vendor_wallets` / `vendor_wallet_transactions` | Vendor wallet ledger |
| `vendor_commissions` / `commission_transactions` | Frozen commission + ledger (unique per vendor_order) |
| `commission_settings` / `commission_rules` | Commission configuration |
| `vendor_payouts` | Vendor payout requests |
| `refunds` | Refund records (gateway + source type) |
| `product_returns` | Return requests |
| `shipments` / `shipment_events` / `shipment_webhooks` | Shiprocket shipments + tracking |
| `reviews` / `vendor_reviews` | Product and vendor reviews (moderation state) |
| `product_questions` / `product_answers` | Q&A |
| `admin_financial_ledgers` | Full audit ledger for wallet/settlement movements |
| `audit_logs` | Immutable audit trail (actor, action, before/after) |
| `notifications` | Per-user notifications |
| `email_templates` | Configurable transactional email templates |
| `payment_settings` / `shiprocket_settings` | Credentials (encrypted at rest) |
| `verification_codes` | Email/OTP verification |
| `recently_viewed` | Personalization |
| `general_settings` / `branding_settings` / `security_settings` / `cms_pages` / `banners` / `testimonials` / `homepage_sections` | Storefront/admin configuration & content |

## Key Financial Integrity Constraints

- **Checkout idempotency:** unique index on `orders(user_id, idempotency_key)`.
- **Settlement uniqueness:** `vendor_commissions.vendor_order_id` is unique — an order can be settled at most once.
- **Wallet dedup:** wallet credit/debit checks a per-`reference_type/reference_id` flag before posting, preventing double crediting from webhook/verify retries.
- **Refunds:** refund processing is guarded so a refund can be processed only once.
- **Payouts:** payout balance is verified inside the transaction (via a wallet lock) to prevent overspending.

## Indexing

- `inventory_transactions(reference_type, reference_id)` — fast inventory ledger lookups.
- Storefront indexes on `products` (status/featured/best-seller) for fast listing.
- All foreign keys are indexed by Laravel's default `foreignId` conventions.
</content>
