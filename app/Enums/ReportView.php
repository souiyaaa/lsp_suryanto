<?php

namespace App\Enums;

use App\Traits\HasValues;

/** The four "hasil persewaan" reports (requirements §8), one per ReportService method. */
enum ReportView: string
{
    use HasValues;

    case Monthly = 'per_month'; // a selected year, by month
    case Daily = 'per_day'; // a selected month, by day
    case GarageType = 'per_garage_type'; // all-time
    case Penyewa = 'per_penyewa'; // all-time
}
