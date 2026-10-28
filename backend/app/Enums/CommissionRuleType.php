<?php

namespace App\Enums;

enum CommissionRuleType: string
{
    case Percentage = 'PERCENTAGE';
    case Fixed = 'FIXED';
}
