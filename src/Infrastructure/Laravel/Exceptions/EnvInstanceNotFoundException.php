<?php

namespace Larapi\Infrastructure\Laravel\Exceptions;

use RuntimeException;
use Throwable;

class EnvInstanceNotFoundException extends RuntimeException
{
    public function __construct($message = "The container does not have an 'env' instance.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}