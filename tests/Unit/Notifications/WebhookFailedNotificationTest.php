<?php

declare(strict_types=1);

use Illuminate\Notifications\Messages\MailMessage;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Notifications\WebhookFailedNotification;

beforeEach(function () {
    config([
        'larawebhook.notifications.channels' => ['mail', 'slack'],
        'larawebhook.dashboard.path' => '/larawebhook/dashboard',
    ]);

    $this->log = WebhookLog::create([
        'service' => 'stripe',
        'event' => 'payment.failed',
        'status' => 'failed',
        'error_message' => 'Connection timeout',
        'payload' => ['id' => 'pi_123'],
    ]);

    $this->notification = new WebhookFailedNotification($this->log, 5);
});

describe('WebhookFailedNotification channels', function () {
    it('returns configured channels', function () {
        $channels = $this->notification->via(null);

        expect($channels)->toBe(['mail', 'slack']);
    });

    it('uses default channels when not configured', function () {
        config(['larawebhook.notifications.channels' => null]);

        $notification = new WebhookFailedNotification($this->log, 3);
        $channels = $notification->via(null);

        expect($channels)->toBe(['mail']);
    });

    it('uses default channels when empty array', function () {
        config(['larawebhook.notifications.channels' => []]);

        $notification = new WebhookFailedNotification($this->log, 3);
        $channels = $notification->via(null);

        expect($channels)->toBe(['mail']);
    });
});

describe('WebhookFailedNotification mail', function () {
    it('creates mail message with correct subject', function () {
        $mail = $this->notification->toMail(null);

        expect($mail)->toBeInstanceOf(MailMessage::class)
            ->and($mail->subject)->toBe('Webhook Failure Alert: stripe');
    });

    it('includes service and event information', function () {
        $mail = $this->notification->toMail(null);
        $lines = implode(' ', $mail->introLines);

        expect($lines)->toContain('payment.failed')
            ->and($lines)->toContain('stripe')
            ->and($lines)->toContain('5 times');
    });

    it('includes error message when present', function () {
        $mail = $this->notification->toMail(null);
        $lines = implode(' ', $mail->introLines);

        expect($lines)->toContain('Connection timeout');
    });

    it('includes dashboard link', function () {
        $mail = $this->notification->toMail(null);

        expect($mail->actionUrl)->toContain('/larawebhook/dashboard')
            ->and($mail->actionUrl)->toContain('log='.$this->log->id);
    });
});

describe('WebhookFailedNotification slack', function () {
    it('returns array payload for slack webhook', function () {
        $slack = $this->notification->toSlack(null);

        expect($slack)->toBeArray()
            ->and($slack)->toHaveKey('text')
            ->and($slack)->toHaveKey('attachments');
    });

    it('includes alert content', function () {
        $slack = $this->notification->toSlack(null);

        expect($slack['text'])->toContain('Webhook Failure Alert');
    });

    it('has attachment with service title', function () {
        $slack = $this->notification->toSlack(null);

        expect($slack['attachments'])->toHaveCount(1);

        $attachment = $slack['attachments'][0];
        expect($attachment['title'])->toBe('Service: stripe');
    });

    it('has attachment with danger color', function () {
        $slack = $this->notification->toSlack(null);
        $attachment = $slack['attachments'][0];

        expect($attachment['color'])->toBe('danger');
    });

    it('includes event and failure count in fields', function () {
        $slack = $this->notification->toSlack(null);
        $fields = $slack['attachments'][0]['fields'];

        $fieldValues = collect($fields)->pluck('value', 'title')->toArray();

        expect($fieldValues['Event'])->toBe('payment.failed')
            ->and($fieldValues['Failure Count'])->toBe('5');
    });
});

describe('WebhookFailedNotification array', function () {
    it('returns array with all data', function () {
        $array = $this->notification->toArray(null);

        expect($array)->toBeArray()
            ->and($array['service'])->toBe('stripe')
            ->and($array['event'])->toBe('payment.failed')
            ->and($array['failure_count'])->toBe(5)
            ->and($array['error_message'])->toBe('Connection timeout')
            ->and($array['log_id'])->toBe($this->log->id);
    });
});

describe('WebhookFailedNotification accessors', function () {
    it('returns the log', function () {
        expect($this->notification->getLog()->id)->toBe($this->log->id);
    });

    it('returns the failure count', function () {
        expect($this->notification->getFailureCount())->toBe(5);
    });
});

describe('WebhookFailedNotification without error message', function () {
    it('handles missing error message gracefully', function () {
        $log = WebhookLog::create([
            'service' => 'github',
            'event' => 'push',
            'status' => 'failed',
            'error_message' => null,
            'payload' => ['test' => 'data'],
        ]);

        $notification = new WebhookFailedNotification($log, 3);

        $mail = $notification->toMail(null);
        $array = $notification->toArray(null);

        expect($array['error_message'])->toBeNull();
    });
});
