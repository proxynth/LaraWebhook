<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;

final readonly class GetWebhookFailureDetails
{
    public function __construct(
        private WebhookLogReadRepository $readRepository,
    ) {}

    public function handle(GetWebhookFailureDetailsQuery $query): WebhookFailureDetails
    {
        return $this->readRepository->findFailureDetails($query->webhookLogId);
    }
}
