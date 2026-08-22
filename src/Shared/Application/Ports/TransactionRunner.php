<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Application\Ports;

/** @template T */
interface TransactionRunner
{
    /** @template T */
    /** @param callable(): T $operation */
    /** @return T */
    public function run(callable $operation): mixed;
}
