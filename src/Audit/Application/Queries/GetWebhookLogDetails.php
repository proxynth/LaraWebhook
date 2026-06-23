<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;

final readonly class GetWebhookLogDetails
{
    public function __construct(
        private WebhookLogReadRepository $readRepository,
    ) {}

    public function handle(GetWebhookLogDetailsQuery $query): WebhookLogDetails
    {
        return $this->readRepository->findDetails($query->webhookLogId);
    }
}
