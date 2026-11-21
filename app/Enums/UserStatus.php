<?php

namespace App\Enums;

use App\Traits\HasValues;

enum UserStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
