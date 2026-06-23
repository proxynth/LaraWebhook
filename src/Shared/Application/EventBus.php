<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Application;

use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

interface EventBus
{
    public function dispatch(DomainEvent $event): void;

    /**
     * @param  array<int, DomainEvent>  $events
     */
    public function dispatchMany(array $events): void;
}
