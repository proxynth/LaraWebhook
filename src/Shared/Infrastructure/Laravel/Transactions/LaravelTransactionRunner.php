<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Transactions;

use Illuminate\Support\Facades\DB;
use Proxynth\Larawebhook\Shared\Application\Ports\TransactionRunner;

final class LaravelTransactionRunner implements TransactionRunner
{
    public function run(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}
