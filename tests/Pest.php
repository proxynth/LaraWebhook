<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Proxynth\Larawebhook\Tests\Testcase;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in('Unit/WebhookLoggerTest.php', 'Unit/WebhookValidatorWithLoggingTest.php', 'Unit/WebhookValidatorRetryTest.php', 'Feature');
