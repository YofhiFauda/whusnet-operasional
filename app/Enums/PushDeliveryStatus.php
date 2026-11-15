<?php

namespace App\Enums;

enum PushDeliveryStatus: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Escalated = 'escalated';
}
