<?php
namespace App\Traits;

/**
 * For backed enums only: `cases()` is provided by the enum that uses this trait.
 *
 * @method static static[] cases()
 */
trait HasValues
{
    /** @return string[] the database values, e.g. ['harian', 'bulanan'] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}