<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Proxynth\Larawebhook\Larawebhook
 */
class Larawebhook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Proxynth\Larawebhook\Larawebhook::class;
    }
}
