<?php

namespace Larapi\Infrastructure\Laravel\Contracts;

use Illuminate\Http\Request;

interface HandlesHttp
{
    public function handleRequest(Request $request): void;
}