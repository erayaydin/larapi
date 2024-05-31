<?php

namespace Larapi\Infrastructure\Laravel\Exceptions;

use RuntimeException;
use Throwable;

class LoggerNotFoundException extends RuntimeException
{
    public function __construct($message = "The application does not have any logger instance.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}