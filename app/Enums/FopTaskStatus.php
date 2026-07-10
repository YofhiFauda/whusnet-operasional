<?php

namespace App\Enums;

enum FopTaskStatus: string
{
    case PROSES = 'Proses';
    case PENDING = 'Pending';
    case SELESAI = 'Selesai';
    case CANCEL = 'Cancel';
}
