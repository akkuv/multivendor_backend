<?php

namespace App\Enums;

enum NotificationType: string
{
    // Customer
    case CustomerRegistered = 'customer_registered';
    case CustomerVerified = 'customer_verified';
    case OrderCreated = 'order_created';
    case OrderStatus = 'order_status';
    case PaymentSuccess = 'payment_success';
    case PaymentFailure = 'payment_failure';
    case OrderConfirmed = 'order_confirmed';
    case OrderPacked = 'order_packed';
    case OrderShipped = 'order_shipped';
    case OutForDelivery = 'out_for_delivery';
    case OrderDelivered = 'order_delivered';
    case OrderCancelled = 'order_cancelled';
    case ReturnRequested = 'return_requested';
    case RefundCompleted = 'refund_completed';
    case WalletCredited = 'wallet_credited';
    case WalletDebited = 'wallet_debited';
    case SupportReplyForCustomer = 'support_reply_for_customer';
    case ProductQueryReplyForCustomer = 'product_query_reply_for_customer';

    // Vendor
    case VendorApproved = 'vendor_approved';
    case ProductApproved = 'product_approved';
    case ProductRejected = 'product_rejected';
    case NewOrderForVendor = 'new_order_for_vendor';
    case ReturnRequestForVendor = 'return_request_for_vendor';
    case PayoutApproved = 'payout_approved';
    case VendorWalletCredited = 'vendor_wallet_credited';
    case OrderQueryForVendor = 'order_query_for_vendor';
    case ProductQueryForVendor = 'product_query_for_vendor';
    case SupportReplyForVendor = 'support_reply_for_vendor';

    // Admin
    case NewVendorRegistered = 'new_vendor_registered';
    case NewProductSubmitted = 'new_product_submitted';
    case AdminNewOrder = 'admin_new_order';
    case AdminReturnRequest = 'admin_return_request';
    case AdminPayoutRequest = 'admin_payout_request';
    case AdminPaymentFailure = 'admin_payment_failure';
    case AdminShippingFailure = 'admin_shipping_failure';
    case ProductQueryForAdmin = 'product_query_for_admin';
    case NewSupportQueryForAdmin = 'new_support_query_for_admin';
    case OrderQueryReplyForAdmin = 'order_query_reply_for_admin';
}
