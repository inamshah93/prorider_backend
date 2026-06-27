<?php

namespace App\Enums;

enum MerchantPrepStatus: string
{
    case Created = 'created';
    case LabelGenerated = 'label_generated';
    case Packed = 'packed';
}
