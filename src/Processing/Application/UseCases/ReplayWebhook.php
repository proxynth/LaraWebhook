<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\UseCases;

use Proxynth\Larawebhook\Audit\Domain\Exceptions\PayloadNotAvailable;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\WebhookValidatorFactory;
use Proxynth\Larawebhook\Processing\Application\Commands\ReplayWebhookCommand;

final readonly class ReplayWebhook
{
    public function __construct(
        private WebhookValidatorFactory $validatorFactory,
    ) {}

    /**
     * @throws WebhookException
     * @throws \JsonException
     * @throws \Exception
     */
    public function handle(ReplayWebhookCommand $command): WebhookLog
    {
        $log = $command->log;

        if ($log->payload === null | $log->payload === []) {
            throw PayloadNotAvailable::forWebhookLog($log->id);
        }

        $validator = $this->validatorFactory->forService($log->service);

        return $validator->validateAndLog(
            payload: json_encode($log->payload, JSON_THROW_ON_ERROR),
            signature: $command->signature,
            service: $log->service,
            event: $log->event,
            attempt: $log->attempt + 1
        );
    }
}
