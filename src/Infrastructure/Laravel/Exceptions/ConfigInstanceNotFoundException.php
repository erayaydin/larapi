<?php

namespace Larapi\Infrastructure\Laravel\Exceptions;

use RuntimeException;
use Throwable;

class ConfigInstanceNotFoundException extends RuntimeException
{
    public function __construct($message = "The container does not have an 'config' instance.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}