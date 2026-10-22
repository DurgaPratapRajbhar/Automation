<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Service layer se business rule tootne par throw karo.
 * bootstrap/app.php isse JSON { success: false, message } me badal deta hai.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 422
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
