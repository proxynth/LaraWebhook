<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['var_dump', 'dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

$domains = [
    'Proxynth\Larawebhook\Ingestion\Domain',
    'Proxynth\Larawebhook\Processing\Domain',
    'Proxynth\Larawebhook\Audit\Domain',
    'Proxynth\Larawebhook\Shared\Domain',
];

$applications = [
    'Proxynth\Larawebhook\Ingestion\Application',
    'Proxynth\Larawebhook\Processing\Application',
    'Proxynth\Larawebhook\Audit\Application',
    'Proxynth\Larawebhook\Shared\Application',
];

$controllers = [
    'Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Controllers',
    'Proxynth\Larawebhook\Ingestion\Infrastructure\Laravel\Http\Controllers',
    'Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Http\Controllers',
    'Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Http\Controllers',
];

$applicationInfrastructureExceptions = [
    // Temporary migration exceptions while application read/write models still return Eloquent-backed logs.
    'Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter',
    'Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails',
    'Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails',
    'Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary',
    'Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog',
    'Proxynth\Larawebhook\Ingestion\Application\Results\ReceiveWebhookResult',
    'Proxynth\Larawebhook\Processing\Application\Results\ReplayWebhookResult',
    'Proxynth\Larawebhook\Processing\Application\Results\RetryWebhookResult',
    'Proxynth\Larawebhook\Shared\Application\Larawebhook',
];

arch('domain does not depend on illuminate')
    ->expect($domains)
    ->not->toUse('Illuminate');

arch('domain does not depend on eloquent')
    ->expect($domains)
    ->not->toUse('Illuminate\Database\Eloquent');

arch('domain classes do not extend eloquent model')
    ->expect($domains)
    ->classes()
    ->not->toExtend('Illuminate\Database\Eloquent\Model');

arch('domain does not depend on application')
    ->expect($domains)
    ->not->toUse($applications);

arch('domain does not depend on infrastructure')
    ->expect($domains)
    ->not->toUse([
        'Proxynth\Larawebhook\Ingestion\Infrastructure',
        'Proxynth\Larawebhook\Processing\Infrastructure',
        'Proxynth\Larawebhook\Audit\Infrastructure',
        'Proxynth\Larawebhook\Shared\Infrastructure',
    ]);

arch('processing domain does not depend on audit')
    ->expect('Proxynth\Larawebhook\Processing\Domain')
    ->not->toUse('Proxynth\Larawebhook\Audit');

arch('audit domain does not depend on processing')
    ->expect('Proxynth\Larawebhook\Audit\Domain')
    ->not->toUse('Proxynth\Larawebhook\Processing');

arch('shared domain does not depend on bounded contexts')
    ->expect('Proxynth\Larawebhook\Shared\Domain')
    ->not->toUse([
        'Proxynth\Larawebhook\Ingestion',
        'Proxynth\Larawebhook\Processing',
        'Proxynth\Larawebhook\Audit',
    ]);

arch('domain does not call config helper')
    ->expect('config')
    ->not->toBeUsedIn($domains);

arch('controllers are only used in infrastructure laravel http')
    ->expect(array_merge($domains, $applications))
    ->not->toUse($controllers);

arch('application does not depend on infrastructure except documented migration gaps')
    ->expect($applications)
    ->not->toUse([
        'Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\FailureDetector',
        'Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\NotificationSender',
        'Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog',
        'Proxynth\Larawebhook\Audit\Infrastructure\Logging\WebhookLogger',
        'Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\WebhookValidatorFactory',
        'Proxynth\Larawebhook\Processing\Infrastructure\Idempotency\DefaultIdempotencyResolver',
        'Proxynth\Larawebhook\Processing\Infrastructure\Persistence\EloquentReplayableWebhookRepository',
    ])
    ->ignoring($applicationInfrastructureExceptions)
    ->ignoring([
        'Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Controllers',
        'Proxynth\Larawebhook\Ingestion\Infrastructure\Laravel\Http\Controllers',
        'Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Http\Controllers',
        'Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Http\Controllers',
    ]);

arch('application does not call config helper')
    ->expect([
        'Proxynth\Larawebhook\Ingestion\Application',
        'Proxynth\Larawebhook\Processing\Application',
        'Proxynth\Larawebhook\Audit\Application',
    ])
    ->not->toUse('config');
