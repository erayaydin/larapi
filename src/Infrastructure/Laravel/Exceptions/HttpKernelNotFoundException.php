<?php

namespace Larapi\Infrastructure\Laravel\Exceptions;

use RuntimeException;
use Throwable;

class HttpKernelNotFoundException extends RuntimeException
{
    public function __construct($message = "The application does not have Http kernel.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}