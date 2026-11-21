<?php

namespace App\Enums;

use App\Traits\HasValues;

enum UserRole: string
{
    use HasValues;

    case Admin = 'admin';
    case Penyewa = 'penyewa';
}
