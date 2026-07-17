<?php

namespace App\Enums;

enum NotificationType: string
{
    case INFO = 'info';
    case ERROR = 'error';
    case WARNING = 'warning';
    case SUCCESS = 'success';
}
