<?php

namespace Larapi\Infrastructure\Laravel\Exceptions;

use RuntimeException;
use Throwable;

class ExceptionHandlerNotFoundException extends RuntimeException
{
    public function __construct($message = "The container does not have an exception handler.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}