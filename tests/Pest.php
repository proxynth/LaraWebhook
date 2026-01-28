<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Proxynth\Larawebhook\Tests\Testcase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| RefreshDatabase
|--------------------------------------------------------------------------
|
| Tests that require database migrations to be refreshed before each test.
|
*/

uses(RefreshDatabase::class)->in(
    // Unit tests that need database
    'Unit/Services/WebhookLoggerTest.php',
    'Unit/Services/WebhookValidatorWithLoggingTest.php',
    'Unit/Services/WebhookValidatorRetryTest.php',
    'Unit/Services/FailureDetectorTest.php',
    'Unit/Services/NotificationSenderTest.php',
    'Unit/Notifications/WebhookFailedNotificationTest.php',
    'Unit/Jobs/RetryWebhookJobTest.php',
    'Unit/LarawebhookTest.php',
    // All Feature tests
    'Feature'
);
