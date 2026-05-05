<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Illuminate\Support\Str;

class PayloadRedactor
{
    /**
     * Redact the full payload
     */
    public function redact(array $payload): array
    {
        $fields = collect(config('larawebhook.redaction.fields', []))
            ->map(fn (string $field) => Str::lower($field))
            ->all();

        $replacement = config('larawebhook.redaction.replacement', '[REDACTED]');

        return $this->redactArray($payload, $fields, $replacement);
    }

    private function redactArray(array $payload, array $fields, string $replacement): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(Str::lower($key), $fields, true)) {
                $payload[$key] = $replacement;

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redactArray($value, $fields, $replacement);
            }
        }

        return $payload;
    }
}
