<?php

namespace App\Enums;

enum FopTaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'Medium';
    case HIGH = 'High';
    case URGENT = 'Urgent';
}
