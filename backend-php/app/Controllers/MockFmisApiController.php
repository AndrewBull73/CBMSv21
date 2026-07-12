<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Integrations\MockFmisApiService;

final class MockFmisApiController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => false],
    ];

    public function budgetExport(): void
    {
        $this->handleJsonPost('budget_export');
    }

    public function actualsImport(): void
    {
        $this->handleJsonPost('actuals_import');
    }

    private function handleJsonPost(string $operation): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        require_once __DIR__ . '/../Services/Integrations/MockFmisApiService.php';

        $raw = file_get_contents('php://input');
        $payload = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $service = new MockFmisApiService();
        $response = $operation === 'actuals_import'
            ? $service->acceptActualsImport($payload)
            : $service->acceptBudgetExport($payload);

        http_response_code(($response['ok'] ?? false) ? 200 : (($response['partial'] ?? false) ? 207 : 422));
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
