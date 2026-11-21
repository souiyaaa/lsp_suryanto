<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PaymentProofUploadException extends RuntimeException
{
    public const USER_MESSAGE = 'Gagal mengunggah bukti pembayaran, coba lagi';

    public function __construct(string $message = self::USER_MESSAGE, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
