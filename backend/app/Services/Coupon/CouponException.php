<?php

namespace App\Services\Coupon;

use App\Enums\CouponErrorCode;
use Exception;

class CouponException extends Exception
{
    public function __construct(
        public readonly CouponErrorCode $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function code(): string
    {
        return $this->errorCode->value;
    }
}
