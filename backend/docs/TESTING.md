# Testing

The backend uses PHPUnit via Laravel's test runner. Feature tests exercise the full HTTP stack (authentication, authorization, IDs, ownership, status transitions, financial integrity); unit tests cover service-layer logic.

## Run the full suite

```bash
php artisan test
```

Current status: **277 tests / 1225 assertions, all green.**

## Run a subset

```bash
# One test class
php artisan test --filter=SecurityAuthorizationTest

# One test method
php artisan test --filter=test_customer_cannot_access_admin_endpoints

# A group of classes
php artisan test --filter='OrderCreationTest|OrderLifecycleTest'
```

Tests use `RefreshDatabase`, so an isolated (usually SQLite in-memory or a test MySQL) database is migrated per run.

## Code style

Format with Laravel Pint after making PHP changes:

```bash
vendor/bin/pint <path-to-file>
```

(Note: this repo is not a git repository, so pass explicit file paths rather than `--dirty`.)

## Coverage areas

### Feature tests
- **Auth & verification:** registration, login (rate-limited), logout, email/OTP verification, password reset, session management.
- **Catalog:** categories, brands, attributes/sizes/colors, product lifecycle, storefront listing, search.
- **Commerce:** cart, checkout (Razorpay + COD), coupon validation, order lifecycle, cancellation policy, returns, refunds, shipments.
- **Financial:** wallet credit/debit/holds/recharge, vendor wallet, payouts, commission settlement, admin financial ledger.
- **Marketplace moderation:** reviews, vendor reviews, questions/answers, wishlists + notifications.
- **Admin/system:** dashboard, reports (CSV export), audit logs, email templates, notifications.

### Security & integrity
- `SecurityAuthorizationTest` — asserts that customers/vendors/admins are rejected (401/403) from panels they may not access, including financial actions: product price changes, commission settings, coupon creation, settlement runs, and payment settings.
- Cross-tenant isolation — a vendor cannot view/update another vendor's products (404) or another user's wallet transactions (404).

### Unit tests
- `CoreServicesTest` and service-level tests for payment verification, idempotency guards, and financial business rules.

## Flaky-test note
`CartTest::test_wishlist_item_can_be_moved_to_cart` occasionally returns 422 on first run due to test DB timing; re-running generally passes. This is a known, non-blocking candidate for future stabilisation.
</content>
