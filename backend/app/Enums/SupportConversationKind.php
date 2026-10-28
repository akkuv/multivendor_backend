<?php

namespace App\Enums;

/**
 * What a support conversation is about.
 *
 * `General` is the ordinary chat a vendor or customer has with the admin team,
 * one per person. `OrderQuery` is an admin-initiated thread about a specific
 * order, addressed to the vendor who fulfilled it. `ProductQuery` is a
 * customer-initiated thread about a product, addressed to the vendor who sells
 * it and the admin team together. Keeping them all in the same table lets each
 * kind reuse the messaging, read tracking and broadcast machinery while staying
 * out of the everyday inboxes.
 */
enum SupportConversationKind: string
{
    case General = 'GENERAL';
    case OrderQuery = 'ORDER_QUERY';
    case ProductQuery = 'PRODUCT_QUERY';

    public function label(): string
    {
        return match ($this) {
            self::General => __('General'),
            self::OrderQuery => __('Order Query'),
            self::ProductQuery => __('Product Query'),
        };
    }
}
