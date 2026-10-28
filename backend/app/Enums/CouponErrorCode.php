<?php

namespace App\Enums;

enum CouponErrorCode: string
{
    case CouponNotFound = 'COUPON_NOT_FOUND';
    case CouponInactive = 'COUPON_INACTIVE';
    case CouponNotStarted = 'COUPON_NOT_STARTED';
    case CouponExpired = 'COUPON_EXPIRED';
    case MinimumOrderNotMet = 'MINIMUM_ORDER_NOT_MET';
    case UsageLimitReached = 'USAGE_LIMIT_REACHED';
    case UserLimitReached = 'USER_LIMIT_REACHED';
    case FirstOrderOnly = 'FIRST_ORDER_ONLY';
    case ProductNotApplicable = 'PRODUCT_NOT_APPLICABLE';
    case VendorNotApplicable = 'VENDOR_NOT_APPLICABLE';
    case CategoryNotApplicable = 'CATEGORY_NOT_APPLICABLE';
    case BrandNotApplicable = 'BRAND_NOT_APPLICABLE';
}
