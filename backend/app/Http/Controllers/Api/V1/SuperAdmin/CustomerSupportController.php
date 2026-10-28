<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\SupportRequesterType;
use App\Models\SupportConversation;

/**
 * The customer support inbox at /admin/customer-support.
 */
class CustomerSupportController extends SupportInboxController
{
    protected function requesterType(): SupportRequesterType
    {
        return SupportRequesterType::Customer;
    }

    /**
     * @return list<string>
     */
    protected function relations(): array
    {
        return [
            'customerUser:id,name,email,phone,avatar',
            ...parent::relations(),
        ];
    }

    /**
     * The open thread also carries the customer's recent orders, so the admin
     * sees the same order context next to the first message that the customer
     * sees on their side.
     *
     * @return list<string>
     */
    protected function threadRelations(SupportConversation $conversation): array
    {
        return [
            ...$this->relations(),
            'customerOrders' => fn ($query) => $query->limit(3),
            'customerOrders.items',
        ];
    }
}
