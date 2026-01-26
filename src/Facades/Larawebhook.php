<?php

namespace Proxynth\Larawebhook\Larawebhook;

use Illuminate\Support\Facades\Facade;

class Larawebhook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Proxynth\Larawebhook\Larawebhook::class;
    }
}
