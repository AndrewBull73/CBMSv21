<?php
declare(strict_types=1);

namespace App\Services\AI;

final class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        private ?string $apiKey,
        private string $model = 'gpt-4.1',
        private string $baseUrl = 'https://api.openai.com/v1',
        private int $timeoutSeconds = 45,
        private int $connectTimeoutSeconds = 15
    ) {
    }

    public function code(): string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function generate(string $instructions, string $input): array
    {
        if (trim((string) $this->apiKey) === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL is not available.');
        }

        $payload = [
            'model' => $this->model,
            'instructions' => $instructions,
            'input' => $input,
            'temperature' => 0.2,
            'max_output_tokens' => 900,
            'store' => false,
        ];

        $ch = curl_init(rtrim($this->baseUrl, '/') . '/responses');
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialise cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(1, min(60, $this->connectTimeoutSeconds)),
            CURLOPT_TIMEOUT => max(5, min(120, $this->timeoutSeconds)),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('AI provider returned no response. ' . $error);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('AI provider returned invalid JSON.');
        }
        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['error']['message'] ?? ('HTTP ' . $status));
            throw new \RuntimeException('AI provider request failed: ' . $message);
        }

        return [
            'text' => $this->extractText($decoded),
            'raw' => $decoded,
            'usage' => is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [],
        ];
    }

    private function extractText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return trim($response['output_text']);
        }

        $parts = [];
        foreach ((array) ($response['output'] ?? []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    $parts[] = (string) $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    public function healthCheck(): array
    {
        $started = microtime(true);
        $checks = [
            'api_key_configured' => trim((string) $this->apiKey) !== '',
            'curl_available' => function_exists('curl_init'),
            'model' => $this->model,
            'base_url' => $this->baseUrl,
        ];

        if (!$checks['api_key_configured']) {
            return [
                'ok' => false,
                'checks' => $checks,
                'message' => 'OpenAI API key is not configured.',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
        if (!$checks['curl_available']) {
            return [
                'ok' => false,
                'checks' => $checks,
                'message' => 'PHP cURL is not available.',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        try {
            $result = $this->generate(
                'Reply with exactly: CBMS_AI_HEALTH_OK',
                'Health check'
            );
            $text = trim((string) ($result['text'] ?? ''));
            return [
                'ok' => $text !== '',
                'checks' => $checks,
                'message' => $text !== '' ? 'Provider returned a response.' : 'Provider returned an empty response.',
                'response' => $text,
                'usage' => is_array($result['usage'] ?? null) ? $result['usage'] : [],
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'checks' => $checks,
                'message' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }
}
