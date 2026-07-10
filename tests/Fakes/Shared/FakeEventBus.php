<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Tests\Fakes\Shared;

use Proxynth\Larawebhook\Shared\Application\Ports\EventBus;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final class FakeEventBus implements EventBus
{
    /**
     * @var list<DomainEvent>
     */
    public array $events = [];

    /**
     * @var list<array<int, DomainEvent>>
     */
    public array $batches = [];

    public function dispatch(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    public function dispatchMany(array $events): void
    {
        $this->batches[] = $events;

        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
