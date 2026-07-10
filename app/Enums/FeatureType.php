<?php

namespace App\Enums;

enum FeatureType: string
{
    case ROOT = 'root';
    case SUB_FEATURE = 'sub_feature';
    case MINI_FEATURE = 'mini_feature';
}
