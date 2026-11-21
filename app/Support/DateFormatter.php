<?php
namespace App\Support;

use Carbon\CarbonInterface;

final class DateFormatter
{
    private const DISPLAY_FORMAT = 'd M Y';

    /** "21 Nov 2026", with Indonesian month names because config/app.php has locale id. */
    public static function format(CarbonInterface $date): string
    {
        return $date->translatedFormat(self::DISPLAY_FORMAT);
    }
}