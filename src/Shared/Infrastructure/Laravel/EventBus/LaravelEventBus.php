<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\EventBus;

use Illuminate\Contracts\Events\Dispatcher;
use Proxynth\Larawebhook\Shared\Application\Ports\EventBus;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class LaravelEventBus implements EventBus
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function dispatch(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    public function dispatchMany(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
