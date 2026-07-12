<?php
declare(strict_types=1);

namespace App\Services\Intelligence;

final class IntelligenceEngineClient
{
    public function __construct(
        private string $baseUrl,
        private ?string $apiKey = null,
        private int $timeoutSeconds = 20
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function health(): array
    {
        return $this->request('GET', '/health');
    }

    public function forecast(array $payload): array
    {
        return $this->request('POST', '/forecast/basic', $payload);
    }

    public function scenario(array $payload): array
    {
        return $this->request('POST', '/scenario/evaluate', $payload);
    }

    public function trainModel(array $payload): array
    {
        return $this->request('POST', '/ml/train', $payload);
    }

    public function predictModel(array $payload): array
    {
        return $this->request('POST', '/ml/predict', $payload);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        if ($this->baseUrl === '') {
            throw new \RuntimeException('Intelligence Engine URL is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL is not available.');
        }

        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialise cURL.');
        }

        $headers = ['Accept: application/json'];
        if ($this->apiKey !== null && trim($this->apiKey) !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(8, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (strtoupper($method) === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        $errorNo = curl_errno($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            $suffix = trim($error) !== '' ? ' ' . trim($error) : '';
            if ($errorNo === CURLE_OPERATION_TIMEDOUT) {
                $suffix .= ' Increase INTELLIGENCE_ENGINE_TIMEOUT if this operation is expected to take longer.';
            }
            throw new \RuntimeException('Intelligence Engine returned no response after ' . $this->timeoutSeconds . ' seconds.' . $suffix);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Intelligence Engine returned invalid JSON.');
        }
        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['detail'] ?? $decoded['error'] ?? ('HTTP ' . $status));
            throw new \RuntimeException('Intelligence Engine request failed: ' . $message);
        }

        return $decoded;
    }
}
