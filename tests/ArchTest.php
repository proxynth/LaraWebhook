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

arch('domain does not call config helper')
    ->expect('config')
    ->not->toBeUsedIn($domains);

arch('application does not depend on http controllers')
    ->expect($applications)
    ->not->toUse([
        'Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Controllers',
        'Proxynth\Larawebhook\Ingestion\Infrastructure\Laravel\Http\Controllers',
        'Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Http\Controllers',
        'Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Http\Controllers',
    ]);
