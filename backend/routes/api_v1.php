<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\VendorAuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ShipmentWebhookController;
use App\Http\Controllers\Api\V1\SuperAdmin\AdminCommissionRuleController;
use App\Http\Controllers\Api\V1\SuperAdmin\AdminProfileController;
use App\Http\Controllers\Api\V1\SuperAdmin\AdminWalletController;
use App\Http\Controllers\Api\V1\SuperAdmin\AttributeController as AdminAttributeController;
use App\Http\Controllers\Api\V1\SuperAdmin\AttributeValueController as AdminAttributeValueController;
use App\Http\Controllers\Api\V1\SuperAdmin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\V1\SuperAdmin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\V1\SuperAdmin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\V1\SuperAdmin\BrandingSettingController as AdminBrandingSettingController;
use App\Http\Controllers\Api\V1\SuperAdmin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\SuperAdmin\CategoryPageController as AdminCategoryPageController;
use App\Http\Controllers\Api\V1\SuperAdmin\CmsPageController as AdminCmsPageController;
use App\Http\Controllers\Api\V1\SuperAdmin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Api\V1\SuperAdmin\ColorController as AdminColorController;
use App\Http\Controllers\Api\V1\SuperAdmin\CommissionSettingController;
use App\Http\Controllers\Api\V1\SuperAdmin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\V1\SuperAdmin\CustomerSupportController as AdminCustomerSupportController;
use App\Http\Controllers\Api\V1\SuperAdmin\CustomPageController as AdminCustomPageController;
use App\Http\Controllers\Api\V1\SuperAdmin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\SuperAdmin\EmailTemplateController as AdminEmailTemplateController;
use App\Http\Controllers\Api\V1\SuperAdmin\GeneralSettingController as AdminGeneralSettingController;
use App\Http\Controllers\Api\V1\SuperAdmin\HomepageSectionController as AdminHomepageSectionController;
use App\Http\Controllers\Api\V1\SuperAdmin\LedgerController as AdminLedgerController;
use App\Http\Controllers\Api\V1\SuperAdmin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\SuperAdmin\OrderQueryInboxController as AdminOrderQueryInboxController;
use App\Http\Controllers\Api\V1\SuperAdmin\PaymentSettingController as AdminPaymentSettingController;
use App\Http\Controllers\Api\V1\SuperAdmin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\SuperAdmin\ProductQueryInboxController as AdminProductQueryInboxController;
use App\Http\Controllers\Api\V1\SuperAdmin\ProductQuestionController as AdminProductQuestionController;
use App\Http\Controllers\Api\V1\SuperAdmin\RefundController as AdminRefundController;
use App\Http\Controllers\Api\V1\SuperAdmin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\SuperAdmin\ReturnController as AdminReturnController;
use App\Http\Controllers\Api\V1\SuperAdmin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\SuperAdmin\WishlistInsightController as AdminWishlistInsightController;
use App\Http\Controllers\Api\V1\SuperAdmin\SecuritySettingController as AdminSecuritySettingController;
use App\Http\Controllers\Api\V1\SuperAdmin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Api\V1\SuperAdmin\ShipmentController as AdminShipmentController;
use App\Http\Controllers\Api\V1\SuperAdmin\ShiprocketSettingController as AdminShiprocketSettingController;
use App\Http\Controllers\Api\V1\SuperAdmin\SizeController as AdminSizeController;
use App\Http\Controllers\Api\V1\SuperAdmin\SizeGroupController as AdminSizeGroupController;
use App\Http\Controllers\Api\V1\SuperAdmin\SupportController as AdminSupportController;
use App\Http\Controllers\Api\V1\SuperAdmin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Api\V1\SuperAdmin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\SuperAdmin\VendorController as AdminVendorController;
use App\Http\Controllers\Api\V1\SuperAdmin\VendorReviewController as AdminVendorReviewController;
use App\Http\Controllers\Api\V1\SuperAdmin\WebsiteSettingController as AdminWebsiteSettingController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\User\AddressController;
use App\Http\Controllers\Api\V1\User\BrandController;
use App\Http\Controllers\Api\V1\User\CartController;
use App\Http\Controllers\Api\V1\User\CategoryController;
use App\Http\Controllers\Api\V1\User\CategoryPageController;
use App\Http\Controllers\Api\V1\User\CollectionController;
use App\Http\Controllers\Api\V1\User\ColorController;
use App\Http\Controllers\Api\V1\User\CouponController;
use App\Http\Controllers\Api\V1\User\CustomerExperienceController;
use App\Http\Controllers\Api\V1\User\CustomPageController;
use App\Http\Controllers\Api\V1\User\HomeController;
use App\Http\Controllers\Api\V1\User\OrderController;
use App\Http\Controllers\Api\V1\User\PaymentController;
use App\Http\Controllers\Api\V1\User\ProductController;
use App\Http\Controllers\Api\V1\User\ProductQueryController as CustomerProductQueryController;
use App\Http\Controllers\Api\V1\User\ProductQuestionController;
use App\Http\Controllers\Api\V1\User\ReturnController;
use App\Http\Controllers\Api\V1\User\ReviewController;
use App\Http\Controllers\Api\V1\User\SearchController;
use App\Http\Controllers\Api\V1\User\SupportController as CustomerSupportController;
use App\Http\Controllers\Api\V1\User\TrackController;
use App\Http\Controllers\Api\V1\User\VendorController;
use App\Http\Controllers\Api\V1\User\VendorReviewController;
use App\Http\Controllers\Api\V1\User\WalletController;
use App\Http\Controllers\Api\V1\User\WebsiteContentController;
use App\Http\Controllers\Api\V1\User\WishlistController;
use App\Http\Controllers\Api\V1\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Api\V1\Vendor\InventoryController;
use App\Http\Controllers\Api\V1\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Api\V1\Vendor\OrderQueryController as VendorOrderQueryController;
use App\Http\Controllers\Api\V1\Vendor\PayoutController as VendorPayoutController;
use App\Http\Controllers\Api\V1\Vendor\ProductAnswerController as VendorProductAnswerController;
use App\Http\Controllers\Api\V1\Vendor\ProductController as VendorProductController;
use App\Http\Controllers\Api\V1\Vendor\ProductQueryController as VendorProductQueryController;
use App\Http\Controllers\Api\V1\Vendor\ProfileController as VendorProfileController;
use App\Http\Controllers\Api\V1\Vendor\ReportController as VendorReportController;
use App\Http\Controllers\Api\V1\Vendor\ReturnController as VendorReturnController;
use App\Http\Controllers\Api\V1\Vendor\ShipmentController as VendorShipmentController;
use App\Http\Controllers\Api\V1\Vendor\SupportController as VendorSupportController;
use App\Http\Controllers\Api\V1\Vendor\WalletController as VendorWalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('vendor/register', [VendorAuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('send-email-verification', [AuthController::class, 'sendEmailVerification'])->middleware('throttle:verification');
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:verification');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::get('sessions', [AuthController::class, 'sessions']);
        Route::delete('sessions/{token}', [AuthController::class, 'revokeSession']);
    });
});

Route::middleware('auth:sanctum')->prefix('uploads')->group(function (): void {
    Route::post('images', [UploadController::class, 'storeImage']);
    Route::post('videos', [UploadController::class, 'storeVideo']);
});

Route::get('home', [HomeController::class, 'index']);
Route::get('pages/{slug}', [CustomPageController::class, 'show'])->where('slug', '.*');
Route::get('category-pages/{slug}', [CategoryPageController::class, 'show']);
Route::get('website-content', [WebsiteContentController::class, 'show']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);
Route::get('collections', [CollectionController::class, 'index']);
Route::get('collections/{collection:slug}', [CollectionController::class, 'show']);
Route::get('brands', [BrandController::class, 'index']);
Route::get('colors', [ColorController::class, 'index']);
Route::get('vendors', [VendorController::class, 'index']);
Route::get('vendors/{vendor}', [VendorController::class, 'show']);
Route::get('vendors/{vendor}/reviews', [VendorController::class, 'reviews']);
Route::get('search', SearchController::class);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/featured', [ProductController::class, 'featured']);
Route::get('products/deals', [ProductController::class, 'deals']);
Route::get('products/best-sellers', [ProductController::class, 'bestSellers']);
Route::get('products/trending', [ProductController::class, 'trending']);
Route::get('products/compare', [CustomerExperienceController::class, 'compare']);
Route::get('vendors/{vendorId}/products', [ProductController::class, 'vendors']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('products/{product}/reviews', [ProductController::class, 'reviews']);
Route::get('products/{product}/related', [CustomerExperienceController::class, 'related']);
Route::get('products/{product}/frequently-bought', [CustomerExperienceController::class, 'frequentlyBoughtTogether']);
Route::get('products/{product}/questions', [ProductQuestionController::class, 'index']);
Route::get('products/{product}/offers', [ProductController::class, 'offers']);

Route::get('payment-options', [PaymentController::class, 'options']);

/*
|--------------------------------------------------------------------------
| Customer Panel
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:CUSTOMER', 'active'])->prefix('user')->group(function (): void {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/items', [CartController::class, 'add']);
    Route::put('cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('cart/items/{cartItem}', [CartController::class, 'remove']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::get('cart/totals', [CartController::class, 'totals']);

    Route::apiResource('addresses', AddressController::class);

    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::post('payments/{order}/checkout', [PaymentController::class, 'checkout']);
    Route::post('payments/verify', [PaymentController::class, 'verify']);
    Route::get('payments/{order}/status', [PaymentController::class, 'status']);

    Route::post('coupons/validate', [CouponController::class, 'validate']);

    Route::get('wallet', [WalletController::class, 'dashboard']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::get('wallet/transactions/{transaction}', [WalletController::class, 'show']);
    Route::post('wallet/recharge', [WalletController::class, 'recharge']);
    Route::post('wallet/recharge/verify', [WalletController::class, 'confirmRecharge']);

    Route::post('returns', [ReturnController::class, 'store']);
    Route::get('returns', [ReturnController::class, 'index']);
    Route::get('returns/{productReturn}', [ReturnController::class, 'show']);
    Route::post('returns/{productReturn}/withdraw', [ReturnController::class, 'withdraw']);

    Route::get('track/{order}', [TrackController::class, 'track']);

    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist', [WishlistController::class, 'clear']);
    Route::delete('wishlist/{product}', [WishlistController::class, 'destroy']);
    Route::post('wishlist/{product}/move-to-cart', [WishlistController::class, 'moveToCart']);
    Route::put('wishlist/{product}/notifications', [WishlistController::class, 'updateNotifications']);
    Route::get('wishlist-notifications', [WishlistController::class, 'notifications']);
    Route::post('wishlist-notifications/read', [WishlistController::class, 'markNotificationsRead']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('reviews', [ReviewController::class, 'my']);
    Route::post('reviews', [ReviewController::class, 'store']);
    Route::put('reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);

    Route::get('vendor-reviews', [VendorReviewController::class, 'my']);
    Route::post('vendor-reviews', [VendorReviewController::class, 'store']);
    Route::delete('vendor-reviews/{vendorReview}', [VendorReviewController::class, 'destroy']);

    // Customer support chat with the marketplace team.
    Route::get('support', [CustomerSupportController::class, 'show']);
    Route::post('support/messages', [CustomerSupportController::class, 'store']);
    Route::post('support/read', [CustomerSupportController::class, 'markRead']);
    Route::get('support/unread-count', [CustomerSupportController::class, 'unreadCount']);

    // Product queries: private customer-initiated questions about a product,
    // answered by the product's vendor and the admin team together.
    // `/unread-count` is registered before `{supportConversation}` so the
    // literal wins the match.
    Route::get('product-queries', [CustomerProductQueryController::class, 'index']);
    Route::post('product-queries', [CustomerProductQueryController::class, 'store']);
    Route::get('product-queries/unread-count', [CustomerProductQueryController::class, 'unreadCount']);
    Route::get('product-queries/{supportConversation}', [CustomerProductQueryController::class, 'show']);
    Route::post('product-queries/{supportConversation}/messages', [CustomerProductQueryController::class, 'reply']);
    Route::post('product-queries/{supportConversation}/read', [CustomerProductQueryController::class, 'markRead']);

    Route::get('recently-viewed', [CustomerExperienceController::class, 'recentlyViewed']);
    Route::post('products/{product}/view', [CustomerExperienceController::class, 'recordView']);
    Route::post('products/{product}/questions', [ProductQuestionController::class, 'store']);
    Route::get('buy-again', [CustomerExperienceController::class, 'buyAgain']);
    Route::get('recommendations', [CustomerExperienceController::class, 'recommendations']);
    Route::get('questions', [ProductQuestionController::class, 'my']);
    Route::delete('questions/{productQuestion}', [ProductQuestionController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Vendor Panel
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:VENDOR', 'approved.vendor', 'active'])->prefix('vendor')->group(function (): void {
    Route::get('dashboard', [VendorDashboardController::class, 'index']);

    Route::get('products', [VendorProductController::class, 'index']);
    Route::post('products', [VendorProductController::class, 'store']);
    Route::get('products/{product}', [VendorProductController::class, 'show']);
    Route::put('products/{product}', [VendorProductController::class, 'update']);
    Route::delete('products/{product}', [VendorProductController::class, 'destroy']);
    Route::put('products/{product}/toggle', [VendorProductController::class, 'toggleActive']);
    Route::get('products/{product}/inventory/transactions', [InventoryController::class, 'index']);
    Route::post('products/{product}/inventory/adjust', [InventoryController::class, 'adjust']);

    Route::get('orders', [VendorOrderController::class, 'index']);
    Route::get('orders/{vendorOrder}', [VendorOrderController::class, 'show']);
    Route::put('orders/{vendorOrder}/status', [VendorOrderController::class, 'updateStatus']);
    Route::put('order-items/{orderItem}/status', [VendorOrderController::class, 'updateItemStatus']);
    Route::post('orders/{vendorOrder}/ship', [VendorOrderController::class, 'ship']);
    Route::get('orders/{vendorOrder}/couriers', [VendorOrderController::class, 'couriers']);

    Route::get('returns', [VendorReturnController::class, 'index']);
    Route::get('returns/{productReturn}', [VendorReturnController::class, 'show']);
    Route::put('returns/{productReturn}/status', [VendorReturnController::class, 'updateStatus']);

    Route::get('questions', [VendorProductAnswerController::class, 'index']);
    Route::post('questions/{productQuestion}/answer', [VendorProductAnswerController::class, 'store']);

    Route::get('shipments', [VendorShipmentController::class, 'index']);
    Route::post('shipments/manifest', [VendorShipmentController::class, 'manifest']);
    Route::get('shipments/{shipment}', [VendorShipmentController::class, 'show']);
    Route::get('shipments/{shipment}/events', [VendorShipmentController::class, 'events']);
    Route::get('shipments/{shipment}/label', [VendorShipmentController::class, 'label']);
    Route::post('shipments/{shipment}/pickup', [VendorShipmentController::class, 'pickup']);
    Route::post('shipments/{shipment}/retry', [VendorShipmentController::class, 'retry']);
    Route::post('shipments/{shipment}/cancel', [VendorShipmentController::class, 'cancel']);

    // Live support chat with the marketplace team (one thread per vendor).
    Route::get('support', [VendorSupportController::class, 'show']);
    Route::post('support/messages', [VendorSupportController::class, 'store']);
    Route::post('support/read', [VendorSupportController::class, 'markRead']);
    Route::get('support/unread-count', [VendorSupportController::class, 'unreadCount']);

    // Order queries: admin-initiated questions about a specific order.
    // `/unread-count` is registered before `{supportConversation}` so the
    // literal wins the match.
    Route::get('order-queries', [VendorOrderQueryController::class, 'index']);
    Route::get('order-queries/unread-count', [VendorOrderQueryController::class, 'unreadCount']);
    Route::get('order-queries/{supportConversation}', [VendorOrderQueryController::class, 'show']);
    Route::post('order-queries/{supportConversation}/messages', [VendorOrderQueryController::class, 'store']);
    Route::post('order-queries/{supportConversation}/read', [VendorOrderQueryController::class, 'markRead']);

    // Product queries: customer-initiated questions about this vendor's products.
    // `/unread-count` is registered before `{supportConversation}` so the
    // literal wins the match.
    Route::get('product-queries', [VendorProductQueryController::class, 'index']);
    Route::post('product-queries', [VendorProductQueryController::class, 'create']);
    Route::get('product-queries/unread-count', [VendorProductQueryController::class, 'unreadCount']);
    Route::get('product-queries/{supportConversation}', [VendorProductQueryController::class, 'show']);
    Route::post('product-queries/{supportConversation}/messages', [VendorProductQueryController::class, 'store']);
    Route::post('product-queries/{supportConversation}/read', [VendorProductQueryController::class, 'markRead']);

    Route::get('wallet', [VendorWalletController::class, 'dashboard']);
    Route::get('wallet/balance', [VendorWalletController::class, 'balance']);
    Route::get('wallet/transactions', [VendorWalletController::class, 'transactions']);

    Route::get('payouts', [VendorPayoutController::class, 'index']);
    Route::post('payouts', [VendorPayoutController::class, 'request']);
    Route::get('payouts/{payout}', [VendorPayoutController::class, 'show']);

    Route::get('reports/{type}', [VendorReportController::class, 'show']);
    Route::get('reports/{type}/export', [VendorReportController::class, 'export']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('profile', [VendorProfileController::class, 'show']);
    Route::put('profile', [VendorProfileController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Super Admin Panel
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:SUPER_ADMIN', 'active'])->prefix('admin')->group(function (): void {
    Route::get('dashboard', [AdminDashboardController::class, 'index']);
    Route::get('me', [AdminProfileController::class, 'me']);

    Route::get('wallet', [AdminWalletController::class, 'dashboard']);
    Route::get('wallet/transactions', [AdminWalletController::class, 'transactions']);
    Route::get('wallet/transactions/{transaction}', [AdminWalletController::class, 'show']);

    Route::get('reports/{type}', [AdminReportController::class, 'show']);
    Route::get('reports/{type}/export', [AdminReportController::class, 'export']);

    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);

    Route::get('email-templates', [AdminEmailTemplateController::class, 'index']);
    Route::post('email-templates', [AdminEmailTemplateController::class, 'store']);
    Route::get('email-templates/{emailTemplate}', [AdminEmailTemplateController::class, 'show']);
    Route::put('email-templates/{emailTemplate}', [AdminEmailTemplateController::class, 'update']);
    Route::put('email-templates/{emailTemplate}/toggle', [AdminEmailTemplateController::class, 'toggle']);
    Route::delete('email-templates/{emailTemplate}', [AdminEmailTemplateController::class, 'destroy']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('vendors', [AdminVendorController::class, 'index']);
    Route::post('vendors', [AdminVendorController::class, 'store']);
    Route::get('vendors/{vendor}', [AdminVendorController::class, 'show']);
    Route::put('vendors/{vendor}', [AdminVendorController::class, 'update']);
    Route::put('vendors/{vendor}/approve', [AdminVendorController::class, 'approve']);
    Route::put('vendors/{vendor}/activate', [AdminVendorController::class, 'activate']);
    Route::put('vendors/{vendor}/suspend', [AdminVendorController::class, 'suspend']);
    Route::put('vendors/{vendor}/reject', [AdminVendorController::class, 'reject']);
    Route::delete('vendors/{vendor}', [AdminVendorController::class, 'destroy']);

    Route::get('products', [AdminProductController::class, 'index']);
    Route::get('products/{product}', [AdminProductController::class, 'show']);
    Route::put('products/{product}/approve', [AdminProductController::class, 'approve']);
    Route::put('products/{product}/reject', [AdminProductController::class, 'reject']);
    Route::put('products/{product}/publish', [AdminProductController::class, 'publish']);
    Route::put('products/{product}/unpublish', [AdminProductController::class, 'unpublish']);
    Route::put('products/{product}/feature', [AdminProductController::class, 'feature']);
    Route::put('products/{product}/best-seller', [AdminProductController::class, 'bestSeller']);
    Route::put('products/{product}/return-policy', [AdminProductController::class, 'updateReturnPolicy']);
    Route::delete('products/{product}', [AdminProductController::class, 'destroy']);

    Route::get('reviews', [AdminReviewController::class, 'index']);
    Route::get('reviews/stats', [AdminReviewController::class, 'stats']);
    Route::get('reviews/{review}', [AdminReviewController::class, 'show']);
    Route::put('reviews/{review}/approve', [AdminReviewController::class, 'approve']);
    Route::put('reviews/{review}/hide', [AdminReviewController::class, 'hide']);
    Route::put('reviews/{review}/unhide', [AdminReviewController::class, 'unhide']);
    Route::post('reviews/{review}/report', [AdminReviewController::class, 'report']);
    Route::delete('reviews/{review}/report', [AdminReviewController::class, 'unreport']);
    Route::put('reviews/{review}/images', [AdminReviewController::class, 'moderateImages']);
    Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy']);

    // Wishlist insights
    Route::get('wishlist/insights/stats', [AdminWishlistInsightController::class, 'stats']);
    Route::get('wishlist/insights', [AdminWishlistInsightController::class, 'index']);
    Route::get('wishlist/insights/customers/{user}', [AdminWishlistInsightController::class, 'customerDetail']);
    Route::get('wishlist/insights/recommendations/{product}', [AdminWishlistInsightController::class, 'recommendations']);
    Route::post('wishlist/insights/suggest', [AdminWishlistInsightController::class, 'sendSuggestion']);
    Route::get('wishlist/insights/suggestions', [AdminWishlistInsightController::class, 'suggestionHistory']);

    Route::get('vendor-reviews', [AdminVendorReviewController::class, 'index']);
    Route::get('vendor-reviews/{vendorReview}', [AdminVendorReviewController::class, 'show']);
    Route::put('vendor-reviews/{vendorReview}/approve', [AdminVendorReviewController::class, 'approve']);
    Route::put('vendor-reviews/{vendorReview}/hide', [AdminVendorReviewController::class, 'hide']);
    Route::put('vendor-reviews/{vendorReview}/unhide', [AdminVendorReviewController::class, 'unhide']);
    Route::post('vendor-reviews/{vendorReview}/report', [AdminVendorReviewController::class, 'report']);
    Route::delete('vendor-reviews/{vendorReview}/report', [AdminVendorReviewController::class, 'unreport']);
    Route::delete('vendor-reviews/{vendorReview}', [AdminVendorReviewController::class, 'destroy']);

    Route::get('questions', [AdminProductQuestionController::class, 'index']);
    Route::get('questions/{productQuestion}', [AdminProductQuestionController::class, 'show']);
    Route::put('questions/{productQuestion}/hide', [AdminProductQuestionController::class, 'hide']);
    Route::put('questions/{productQuestion}/unhide', [AdminProductQuestionController::class, 'unhide']);
    Route::post('questions/{productQuestion}/report', [AdminProductQuestionController::class, 'report']);
    Route::delete('questions/{productQuestion}/report', [AdminProductQuestionController::class, 'unreport']);
    Route::delete('questions/{productQuestion}', [AdminProductQuestionController::class, 'destroy']);

    Route::get('categories/tree', [AdminCategoryController::class, 'tree']);
    Route::get('categories/all', [AdminCategoryController::class, 'all']);
    Route::get('categories', [AdminCategoryController::class, 'index']);
    Route::post('categories', [AdminCategoryController::class, 'store']);
    Route::get('categories/{category}', [AdminCategoryController::class, 'show']);
    Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::put('categories/{category}/toggle', [AdminCategoryController::class, 'toggleActive']);

    Route::get('brands', [AdminBrandController::class, 'index']);
    Route::post('brands', [AdminBrandController::class, 'store']);
    Route::get('brands/{brand}', [AdminBrandController::class, 'show']);
    Route::put('brands/{brand}', [AdminBrandController::class, 'update']);
    Route::delete('brands/{brand}', [AdminBrandController::class, 'destroy']);
    Route::put('brands/{brand}/toggle', [AdminBrandController::class, 'toggleActive']);

    Route::get('collections', [AdminCollectionController::class, 'index']);
    Route::post('collections', [AdminCollectionController::class, 'store']);
    Route::get('collections/{collection}', [AdminCollectionController::class, 'show']);
    Route::put('collections/{collection}', [AdminCollectionController::class, 'update']);
    Route::delete('collections/{collection}', [AdminCollectionController::class, 'destroy']);
    Route::put('collections/{collection}/toggle', [AdminCollectionController::class, 'toggleActive']);

    Route::get('attributes', [AdminAttributeController::class, 'index']);
    Route::post('attributes', [AdminAttributeController::class, 'store']);
    Route::get('attributes/{attribute}', [AdminAttributeController::class, 'show']);
    Route::put('attributes/{attribute}', [AdminAttributeController::class, 'update']);
    Route::delete('attributes/{attribute}', [AdminAttributeController::class, 'destroy']);
    Route::put('attributes/{attribute}/toggle', [AdminAttributeController::class, 'toggleActive']);

    Route::get('attribute-values', [AdminAttributeValueController::class, 'index']);
    Route::post('attribute-values', [AdminAttributeValueController::class, 'store']);
    Route::get('attribute-values/{attributeValue}', [AdminAttributeValueController::class, 'show']);
    Route::put('attribute-values/{attributeValue}', [AdminAttributeValueController::class, 'update']);
    Route::delete('attribute-values/{attributeValue}', [AdminAttributeValueController::class, 'destroy']);
    Route::put('attribute-values/{attributeValue}/toggle', [AdminAttributeValueController::class, 'toggleActive']);

    Route::get('colors', [AdminColorController::class, 'index']);
    Route::post('colors', [AdminColorController::class, 'store']);
    Route::get('colors/{color}', [AdminColorController::class, 'show']);
    Route::put('colors/{color}', [AdminColorController::class, 'update']);
    Route::delete('colors/{color}', [AdminColorController::class, 'destroy']);
    Route::put('colors/{color}/toggle', [AdminColorController::class, 'toggleActive']);

    Route::get('size-groups', [AdminSizeGroupController::class, 'index']);
    Route::post('size-groups', [AdminSizeGroupController::class, 'store']);
    Route::get('size-groups/{sizeGroup}', [AdminSizeGroupController::class, 'show']);
    Route::put('size-groups/{sizeGroup}', [AdminSizeGroupController::class, 'update']);
    Route::delete('size-groups/{sizeGroup}', [AdminSizeGroupController::class, 'destroy']);
    Route::put('size-groups/{sizeGroup}/toggle', [AdminSizeGroupController::class, 'toggleActive']);

    Route::get('sizes', [AdminSizeController::class, 'index']);
    Route::post('sizes', [AdminSizeController::class, 'store']);
    Route::get('sizes/{size}', [AdminSizeController::class, 'show']);
    Route::put('sizes/{size}', [AdminSizeController::class, 'update']);
    Route::delete('sizes/{size}', [AdminSizeController::class, 'destroy']);
    Route::put('sizes/{size}/toggle', [AdminSizeController::class, 'toggleActive']);

    Route::get('coupons', [AdminCouponController::class, 'index']);
    Route::post('coupons', [AdminCouponController::class, 'store']);
    Route::get('coupons/{coupon}', [AdminCouponController::class, 'show']);
    Route::put('coupons/{coupon}', [AdminCouponController::class, 'update']);
    Route::put('coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle']);
    Route::get('coupons/{coupon}/usage', [AdminCouponController::class, 'usage']);
    Route::get('coupons/{coupon}/history', [AdminCouponController::class, 'history']);
    Route::delete('coupons/{coupon}', [AdminCouponController::class, 'destroy']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show']);
    Route::put('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('vendor-orders', [AdminOrderController::class, 'vendorOrders']);
    Route::get('vendor-orders/{vendorOrder}', [AdminOrderController::class, 'showVendorOrder']);
    Route::put('vendor-orders/{vendorOrder}/status', [AdminOrderController::class, 'updateVendorOrderStatus']);

    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::put('users/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);
    Route::put('users/{user}/block', [AdminUserController::class, 'block']);
    Route::put('users/{user}/unblock', [AdminUserController::class, 'unblock']);

    Route::get('refunds', [AdminRefundController::class, 'index']);
    Route::post('refunds/{refund}/process', [AdminRefundController::class, 'process']);

    Route::get('returns', [AdminReturnController::class, 'index']);
    Route::get('returns/{productReturn}', [AdminReturnController::class, 'show']);
    Route::put('returns/{productReturn}/status', [AdminReturnController::class, 'updateStatus']);

    Route::get('ledger', [AdminLedgerController::class, 'index']);
    Route::get('ledger/{entry}', [AdminLedgerController::class, 'show']);

    Route::get('commissions', [CommissionSettingController::class, 'index']);
    Route::put('commissions/{commissionSetting}', [CommissionSettingController::class, 'update']);

    Route::get('settlements', [AdminSettlementController::class, 'commissions']);
    Route::post('settlements/run', [AdminSettlementController::class, 'run']);
    Route::post('settlements/{commission}/reverse', [AdminSettlementController::class, 'reverse']);

    Route::get('payouts', [AdminSettlementController::class, 'payouts']);
    Route::put('payouts/{payout}/approve', [AdminSettlementController::class, 'approve']);
    Route::put('payouts/{payout}/mark-paid', [AdminSettlementController::class, 'markPaid']);
    Route::put('payouts/{payout}/reject', [AdminSettlementController::class, 'reject']);

    Route::get('payment-settings', [AdminPaymentSettingController::class, 'index']);
    Route::put('payment-settings', [AdminPaymentSettingController::class, 'update']);
    Route::post('payment-settings/test-razorpay', [AdminPaymentSettingController::class, 'testRazorpay']);

    Route::get('shiprockets/settings', [AdminShiprocketSettingController::class, 'index']);
    Route::put('shiprockets/settings', [AdminShiprocketSettingController::class, 'update']);
    Route::post('shiprockets/test', [AdminShiprocketSettingController::class, 'test']);
    Route::get('shiprockets/pickup-locations', [AdminShiprocketSettingController::class, 'pickupLocations']);

    Route::get('general-settings', [AdminGeneralSettingController::class, 'index']);
    Route::put('general-settings', [AdminGeneralSettingController::class, 'update']);

    Route::get('website-settings', [AdminWebsiteSettingController::class, 'index']);
    Route::put('website-settings', [AdminWebsiteSettingController::class, 'update']);

    Route::get('branding-settings', [AdminBrandingSettingController::class, 'index']);
    Route::put('branding-settings', [AdminBrandingSettingController::class, 'update']);

    Route::get('security-settings', [AdminSecuritySettingController::class, 'index']);
    Route::put('security-settings', [AdminSecuritySettingController::class, 'update']);

    Route::get('cms-pages', [AdminCmsPageController::class, 'index']);
    Route::post('cms-pages', [AdminCmsPageController::class, 'store']);
    Route::put('cms-pages/reorder', [AdminCmsPageController::class, 'reorder']);
    Route::get('cms-pages/{cmsPage}', [AdminCmsPageController::class, 'show']);
    Route::put('cms-pages/{cmsPage}', [AdminCmsPageController::class, 'update']);
    Route::delete('cms-pages/{cmsPage}', [AdminCmsPageController::class, 'destroy']);
    Route::put('cms-pages/{cmsPage}/toggle', [AdminCmsPageController::class, 'toggle']);

    Route::get('banners', [AdminBannerController::class, 'index']);
    Route::post('banners', [AdminBannerController::class, 'store']);
    Route::put('banners/reorder', [AdminBannerController::class, 'reorder']);
    Route::get('banners/{banner}', [AdminBannerController::class, 'show']);
    Route::put('banners/{banner}', [AdminBannerController::class, 'update']);
    Route::delete('banners/{banner}', [AdminBannerController::class, 'destroy']);
    Route::put('banners/{banner}/toggle', [AdminBannerController::class, 'toggle']);

    Route::get('testimonials', [AdminTestimonialController::class, 'index']);
    Route::post('testimonials', [AdminTestimonialController::class, 'store']);
    Route::get('testimonials/{testimonial}', [AdminTestimonialController::class, 'show']);
    Route::put('testimonials/{testimonial}', [AdminTestimonialController::class, 'update']);
    Route::delete('testimonials/{testimonial}', [AdminTestimonialController::class, 'destroy']);
    Route::put('testimonials/{testimonial}/toggle', [AdminTestimonialController::class, 'toggle']);

    Route::get('homepage-sections', [AdminHomepageSectionController::class, 'index']);
    Route::post('homepage-sections', [AdminHomepageSectionController::class, 'store']);
    Route::put('homepage-sections/reorder', [AdminHomepageSectionController::class, 'reorder']);
    Route::get('homepage-sections/{homepageSection}', [AdminHomepageSectionController::class, 'show']);
    Route::put('homepage-sections/{homepageSection}', [AdminHomepageSectionController::class, 'update']);
    Route::delete('homepage-sections/{homepageSection}', [AdminHomepageSectionController::class, 'destroy']);
    Route::put('homepage-sections/{homepageSection}/toggle', [AdminHomepageSectionController::class, 'toggle']);

    Route::get('category-pages', [AdminCategoryPageController::class, 'index']);
    Route::post('category-pages', [AdminCategoryPageController::class, 'store']);
    Route::get('category-pages/{categoryPage}', [AdminCategoryPageController::class, 'show']);
    Route::put('category-pages/{categoryPage}', [AdminCategoryPageController::class, 'update']);
    Route::delete('category-pages/{categoryPage}', [AdminCategoryPageController::class, 'destroy']);
    Route::put('category-pages/{categoryPage}/toggle', [AdminCategoryPageController::class, 'toggle']);
    Route::post('category-pages/{categoryPage}/sections', [AdminCategoryPageController::class, 'storeSection']);
    Route::put('category-pages/{categoryPage}/sections/reorder', [AdminCategoryPageController::class, 'reorderSections']);

    Route::get('pages', [AdminCustomPageController::class, 'index']);
    Route::post('pages', [AdminCustomPageController::class, 'store']);
    Route::get('pages/{page}', [AdminCustomPageController::class, 'show']);
    Route::put('pages/{page}', [AdminCustomPageController::class, 'update']);
    Route::delete('pages/{page}', [AdminCustomPageController::class, 'destroy']);
    Route::put('pages/{page}/toggle', [AdminCustomPageController::class, 'toggle']);
    Route::put('category-page-sections/{section}', [AdminCategoryPageController::class, 'updateSection']);
    Route::delete('category-page-sections/{section}', [AdminCategoryPageController::class, 'destroySection']);

    Route::get('commission-rules', [AdminCommissionRuleController::class, 'index']);
    Route::post('commission-rules', [AdminCommissionRuleController::class, 'store']);
    Route::get('commission-rules/{commissionRule}', [AdminCommissionRuleController::class, 'show']);
    Route::put('commission-rules/{commissionRule}', [AdminCommissionRuleController::class, 'update']);
    Route::delete('commission-rules/{commissionRule}', [AdminCommissionRuleController::class, 'destroy']);

    Route::get('shipments', [AdminShipmentController::class, 'index']);
    Route::post('shipments/manifest', [AdminShipmentController::class, 'manifest']);
    Route::get('shipments/{shipment}', [AdminShipmentController::class, 'show']);
    Route::get('shipments/{shipment}/events', [AdminShipmentController::class, 'events']);
    Route::get('shipments/{shipment}/label', [AdminShipmentController::class, 'label']);
    Route::post('shipments/{shipment}/pickup', [AdminShipmentController::class, 'pickup']);
    Route::post('shipments/{shipment}/retry', [AdminShipmentController::class, 'retry']);
    Route::post('shipments/{shipment}/cancel', [AdminShipmentController::class, 'cancel']);

    // Vendor support inbox: list every vendor thread, reply, close or reopen.
    Route::get('support', [AdminSupportController::class, 'index']);
    // Registered before the {supportConversation} route so the literal wins.
    Route::get('support/counts', [AdminSupportController::class, 'counts']);
    Route::get('support/{supportConversation}', [AdminSupportController::class, 'show']);
    Route::post('support/{supportConversation}/messages', [AdminSupportController::class, 'store']);
    Route::post('support/{supportConversation}/read', [AdminSupportController::class, 'markRead']);
    Route::put('support/{supportConversation}/close', [AdminSupportController::class, 'close']);
    Route::put('support/{supportConversation}/reopen', [AdminSupportController::class, 'reopen']);

    // Customer support inbox: the same shape, scoped to customer threads.
    Route::get('customer-support', [AdminCustomerSupportController::class, 'index']);
    Route::get('customer-support/counts', [AdminCustomerSupportController::class, 'counts']);
    Route::get('customer-support/{supportConversation}', [AdminCustomerSupportController::class, 'show']);
    Route::post('customer-support/{supportConversation}/messages', [AdminCustomerSupportController::class, 'store']);
    Route::post('customer-support/{supportConversation}/read', [AdminCustomerSupportController::class, 'markRead']);
    Route::put('customer-support/{supportConversation}/close', [AdminCustomerSupportController::class, 'close']);
    Route::put('customer-support/{supportConversation}/reopen', [AdminCustomerSupportController::class, 'reopen']);

    // Order queries: admin-initiated questions to a vendor about an order.
    // `/counts` is registered before `{supportConversation}` so the literal wins.
    Route::get('order-queries', [AdminOrderQueryInboxController::class, 'index']);
    Route::get('order-queries/counts', [AdminOrderQueryInboxController::class, 'counts']);
    Route::post('order-queries', [AdminOrderQueryInboxController::class, 'create']);
    Route::get('order-queries/{supportConversation}', [AdminOrderQueryInboxController::class, 'show']);
    Route::post('order-queries/{supportConversation}/messages', [AdminOrderQueryInboxController::class, 'store']);
    Route::post('order-queries/{supportConversation}/read', [AdminOrderQueryInboxController::class, 'markRead']);
    Route::put('order-queries/{supportConversation}/close', [AdminOrderQueryInboxController::class, 'close']);
    Route::put('order-queries/{supportConversation}/reopen', [AdminOrderQueryInboxController::class, 'reopen']);

    // Product queries: customer-initiated threads about a product, answered by
    // the vendor and the marketplace team. Admins read and reply only.
    // `/counts` is registered before `{supportConversation}` so the literal wins.
    Route::get('product-queries', [AdminProductQueryInboxController::class, 'index']);
    Route::get('product-queries/counts', [AdminProductQueryInboxController::class, 'counts']);
    Route::post('product-queries', [AdminProductQueryInboxController::class, 'create']);
    Route::get('product-queries/{supportConversation}', [AdminProductQueryInboxController::class, 'show']);
    Route::post('product-queries/{supportConversation}/messages', [AdminProductQueryInboxController::class, 'store']);
    Route::post('product-queries/{supportConversation}/read', [AdminProductQueryInboxController::class, 'markRead']);
    Route::put('product-queries/{supportConversation}/close', [AdminProductQueryInboxController::class, 'close']);
    Route::put('product-queries/{supportConversation}/reopen', [AdminProductQueryInboxController::class, 'reopen']);
});

/*
|--------------------------------------------------------------------------
| Payments Webhook (unauthenticated, verified via signature)
|--------------------------------------------------------------------------
*/
Route::post('payments/webhook', [PaymentController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| Shipping Webhook (unauthenticated, verified via signature)
|--------------------------------------------------------------------------
*/
Route::post('shipments/webhook', [ShipmentWebhookController::class, 'handle'])->name('shipments.webhook');
