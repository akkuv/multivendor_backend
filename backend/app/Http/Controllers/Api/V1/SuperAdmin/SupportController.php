<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\SupportRequesterType;

/**
 * The vendor support inbox at /admin/support.
 */
class SupportController extends SupportInboxController
{
    protected function requesterType(): SupportRequesterType
    {
        return SupportRequesterType::Vendor;
    }

    /**
     * @return list<string>
     */
    protected function relations(): array
    {
        return [
            'vendor:id,store_name,slug,logo,status',
            'vendorUser:id,name,email,phone',
            ...parent::relations(),
        ];
    }
}
