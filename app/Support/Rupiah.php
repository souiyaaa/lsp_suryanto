<?php
namespace App\Support;

final class Rupiah
{
    private const PREFIX = 'Rp ';

    public static function format(int $amount): string
    {
        return self::PREFIX . number_format($amount, 0, ',', '.');
    }
}