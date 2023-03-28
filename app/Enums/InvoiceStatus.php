<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Initial = 'initial';
}
