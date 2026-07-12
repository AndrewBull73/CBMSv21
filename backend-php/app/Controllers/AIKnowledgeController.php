<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Rbac;
use App\Models\AIKnowledgeModel;
use App\Services\AI\AIService;
use App\Services\AI\OpenAIProvider;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/env.php';

final class AIKnowledgeController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['AI_HELP_USE', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'admin' => ['auth' => true, 'permsAny' => ['AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'documents' => ['auth' => true, 'permsAny' => ['AI_HELP_ADMIN', 'AI_HELP_UPLOAD', 'ADMIN_ALL', 'SYSADMIN']],
        'upload' => ['auth' => true, 'permsAny' => ['AI_HELP_UPLOAD', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'uploadDocument' => ['auth' => true, 'permsAny' => ['AI_HELP_UPLOAD', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'upload-document' => ['auth' => true, 'permsAny' => ['AI_HELP_UPLOAD', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'toggle-document' => ['auth' => true, 'permsAny' => ['AI_HELP_UPLOAD', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'bulk-index-help' => ['auth' => true, 'permsAny' => ['AI_HELP_UPLOAD', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'chunks' => ['auth' => true, 'permsAny' => ['AI_HELP_ADMIN', 'AI_HELP_UPLOAD', 'ADMIN_ALL', 'SYSADMIN']],
        'logs' => ['auth' => true, 'permsAny' => ['AI_HELP_VIEW_LOGS', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'usage' => ['auth' => true, 'permsAny' => ['AI_HELP_VIEW_LOGS', 'AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'provider-health' => ['auth' => true, 'permsAny' => ['AI_HELP_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    private AIKnowledgeModel $model;

    public function __construct()
    {
        parent::__construct();

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/AIKnowledgeModel.php';
        require_once __DIR__ . '/../Services/AI/AIProviderInterface.php';
        require_once __DIR__ . '/../Services/AI/OpenAIProvider.php';
        require_once __DIR__ . '/../Services/AI/AIService.php';

        $this->model = new AIKnowledgeModel($conn);
    }

    public function ask(): void
    {
        $this->render('ai/Ask', [
            'title' => 'Ask CBMS Assistant',
            'foundationInstalled' => $this->model->supportsAIKnowledge(),
            'installScriptPath' => $this->installScriptPath(),
            'context' => $this->assistantContext(),
            'csrf' => csrf_token(),
        ]);
    }

    public function answer(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Security check failed.']);
            return;
        }
        if (!$this->model->supportsAIKnowledge()) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'AI knowledge assistant schema is not installed.']);
            return;
        }

        $question = trim((string) ($_POST['question'] ?? ''));
        if ($question === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Please enter a question.']);
            return;
        }

        $context = $this->assistantContext([
            'Module' => trim((string) ($_POST['module'] ?? '')),
            'Screen' => trim((string) ($_POST['screen'] ?? '')),
        ]);
        $service = new AIService($this->model, $this->buildProvider());
        try {
            $result = $service->answer($question, $context, Rbac::can('AI_HELP_DEVELOPER'), Rbac::can('AI_HELP_ADMIN'));
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => 'Assistant search failed: ' . $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
        $documentsUsedJson = json_encode($result['sources'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $questionId = $this->model->logQuestion([
            'UserID' => (int) SessionHelper::get('auth.user_id', 0),
            'Question' => $question,
            'Response' => (string) ($result['answer'] ?? ''),
            'DocumentsUsedJson' => $documentsUsedJson,
            'ResponseTimeMs' => (int) ($result['duration_ms'] ?? 0),
            'ProviderCode' => (string) ($result['provider'] ?? ''),
            'ModelCode' => (string) ($result['model'] ?? ''),
            'PromptTokens' => (int) ($usage['input_tokens'] ?? 0),
            'CompletionTokens' => (int) ($usage['output_tokens'] ?? 0),
            'TotalTokens' => (int) ($usage['total_tokens'] ?? 0),
            'ContextJson' => $contextJson,
        ]);

        echo json_encode([
            'ok' => true,
            'question_id' => $questionId,
            'answer' => (string) ($result['answer'] ?? ''),
            'sources' => $result['sources'] ?? [],
            'duration_ms' => (int) ($result['duration_ms'] ?? 0),
            'provider_error' => $result['provider_error'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function feedback(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false]);
            return;
        }
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $helpfulRaw = trim((string) ($_POST['helpful'] ?? ''));
        $helpful = $helpfulRaw === '' ? null : $helpfulRaw === '1';
        $this->model->updateFeedback($questionId, $helpful, trim((string) ($_POST['feedback'] ?? '')));
        echo json_encode(['ok' => true]);
    }

    public function admin(): void
    {
        $this->render('ai/Admin', [
            'title' => 'AI Knowledge Base',
            'foundationInstalled' => $this->model->supportsAIKnowledge(),
            'installScriptPath' => $this->installScriptPath(),
            'summary' => $this->model->summary(),
            'csrf' => csrf_token(),
        ]);
    }

    public function documents(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $this->render('ai/Documents', [
            'title' => 'AI Knowledge Documents',
            'foundationInstalled' => $this->model->supportsAIKnowledge(),
            'installScriptPath' => $this->installScriptPath(),
            'filters' => $filters,
            'rows' => $this->model->supportsAIKnowledge() ? $this->model->listDocuments($filters) : [],
        ]);
    }

    public function upload(): void
    {
        $replaceId = (int) ($_GET['replace_id'] ?? 0);
        $replaceDocument = $replaceId > 0 ? $this->model->getDocument($replaceId) : null;

        $this->render('ai/Upload', [
            'title' => $replaceDocument !== null ? 'Replace AI Knowledge Document' : 'Upload AI Knowledge Document',
            'foundationInstalled' => $this->model->supportsAIKnowledge(),
            'installScriptPath' => $this->installScriptPath(),
            'context' => $this->assistantContext(),
            'replaceDocument' => $replaceDocument,
            'csrf' => csrf_token(),
        ]);
    }

    public function uploadDocument(): void
    {
        $this->assertPostWithCsrf('index.php?route=ai-knowledge/upload');
        if (!$this->model->supportsAIKnowledge()) {
            $this->flashError('Install the AI knowledge assistant schema before uploading documents.');
            header('Location: index.php?route=ai-knowledge/upload');
            return;
        }

        $fileName = '';
        $fileType = '';
        $text = trim((string) ($_POST['ExtractedText'] ?? ''));

        if (isset($_FILES['KnowledgeFile']) && is_array($_FILES['KnowledgeFile']) && (int) ($_FILES['KnowledgeFile']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $fileName = basename((string) ($_FILES['KnowledgeFile']['name'] ?? ''));
            $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $tmp = (string) ($_FILES['KnowledgeFile']['tmp_name'] ?? '');
            $fileText = $this->extractUploadedText($tmp, $fileType);
            if ($fileText !== '') {
                $text = $fileText . ($text !== '' ? "\n\n" . $text : '');
            }
        }

        $title = trim((string) ($_POST['Title'] ?? ''));
        if ($title === '') {
            $title = $fileName !== '' ? $fileName : 'Untitled Knowledge Document';
        }
        if ($text === '') {
            $this->flashError('No indexable text was found. For PDF files, paste the extracted text into the text area before uploading.');
            header('Location: index.php?route=ai-knowledge/upload');
            return;
        }

        $replaceId = (int) ($_POST['ReplaceDocumentID'] ?? 0);
        $payload = [
                'Title' => $title,
                'Category' => trim((string) ($_POST['Category'] ?? '')),
                'Module' => trim((string) ($_POST['Module'] ?? '')),
                'AudienceCode' => trim((string) ($_POST['AudienceCode'] ?? 'USER')),
                'FiscalYearID' => trim((string) ($_POST['FiscalYearID'] ?? '')),
                'VersionID' => trim((string) ($_POST['VersionID'] ?? '')),
                'CountryID' => trim((string) ($_POST['CountryID'] ?? '')),
                'MinistryCode' => trim((string) ($_POST['MinistryCode'] ?? '')),
                'FileName' => $fileName,
                'FileType' => $fileType,
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'IsActive' => isset($_POST['IsActive']) ? 1 : 0,
        ];

        try {
            if ($replaceId > 0) {
                $documentId = $this->model->replaceDocumentWithChunks($replaceId, $payload, $text, (int) SessionHelper::get('auth.user_id', 0));
                $this->flashSuccess('Knowledge document replaced and re-indexed.');
            } else {
                $documentId = $this->model->saveDocumentWithChunks($payload, $text, (int) SessionHelper::get('auth.user_id', 0));
                $this->flashSuccess('Knowledge document indexed.');
            }
            header('Location: index.php?route=ai-knowledge/chunks&id=' . $documentId);
        } catch (\Throwable $e) {
            $this->flashError('Knowledge document upload failed: ' . $e->getMessage());
            header('Location: index.php?route=ai-knowledge/upload');
        }
    }

    public function toggleDocument(): void
    {
        $this->assertPostWithCsrf('index.php?route=ai-knowledge/documents');
        $id = (int) ($_POST['DocumentID'] ?? 0);
        $active = (int) ($_POST['IsActive'] ?? 0) === 1;
        $this->model->setDocumentActive($id, $active);
        $this->flashSuccess($active ? 'Knowledge document activated.' : 'Knowledge document deactivated.');
        header('Location: index.php?route=ai-knowledge/documents');
    }

    public function bulkIndexHelp(): void
    {
        $this->assertPostWithCsrf('index.php?route=ai-knowledge/admin');
        if (!$this->model->supportsAIKnowledge()) {
            $this->flashError('Install the AI knowledge assistant schema before indexing help files.');
            header('Location: index.php?route=ai-knowledge/admin');
            return;
        }

        $helpDir = realpath(__DIR__ . '/../Views/help');
        if ($helpDir === false || !is_dir($helpDir)) {
            $this->flashError('Help file directory was not found.');
            header('Location: index.php?route=ai-knowledge/admin');
            return;
        }

        $indexed = 0;
        $skipped = 0;
        foreach (glob($helpDir . DIRECTORY_SEPARATOR . '*.en.php') ?: [] as $path) {
            $fileName = basename($path);
            $text = $this->extractHelpFileText($path);
            if ($text === '') {
                $skipped++;
                continue;
            }

            $title = $this->titleFromHelpFileName($fileName);
            $sourceFileName = 'help/' . $fileName;
            $payload = [
                'Title' => $title,
                'Category' => 'Online Help',
                'Module' => $this->moduleFromHelpFileName($fileName),
                'AudienceCode' => 'USER',
                'FiscalYearID' => '',
                'VersionID' => '',
                'CountryID' => '',
                'MinistryCode' => '',
                'FileName' => $sourceFileName,
                'FileType' => 'php-help',
                'Notes' => 'Indexed from CBMS help view ' . $fileName,
                'IsActive' => 1,
            ];

            $existing = $this->model->getActiveDocumentByFileName($sourceFileName);
            if ($existing !== null) {
                $this->model->replaceDocumentWithChunks((int) $existing['DocumentID'], $payload, $text, (int) SessionHelper::get('auth.user_id', 0));
            } else {
                $this->model->saveDocumentWithChunks($payload, $text, (int) SessionHelper::get('auth.user_id', 0));
            }
            $indexed++;
        }

        $this->flashSuccess('Help indexing complete. Indexed ' . $indexed . ' file(s), skipped ' . $skipped . '.');
        header('Location: index.php?route=ai-knowledge/documents&q=help%2F');
    }

    public function chunks(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->render('ai/Chunks', [
            'title' => 'AI Knowledge Chunks',
            'foundationInstalled' => $this->model->supportsAIKnowledge(),
            'installScriptPath' => $this->installScriptPath(),
            'document' => $this->model->getDocument($id),
            'chunks' => $this->model->listChunks($id),
        ]);
    }

    public function logs(): void
    {
        $this->render('ai/Logs', [
            'title' => 'AI Question Logs',
            'foundationInstalled' => $this->model->supportsAIKnowledge(),
            'installScriptPath' => $this->installScriptPath(),
            'rows' => $this->model->recentQuestions(50),
        ]);
    }

    public function usage(): void
    {
        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
        $this->render('ai/Usage', [
            'title' => 'AI Usage Dashboard',
            'foundationInstalled' => $this->model->supportsAIKnowledge(),
            'installScriptPath' => $this->installScriptPath(),
            'days' => $days,
            'usage' => $this->model->usageSummary($days),
        ]);
    }

    public function providerHealth(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
            return;
        }
        if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Security check failed.']);
            return;
        }

        echo json_encode($this->buildProvider()->healthCheck(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function buildProvider(): OpenAIProvider
    {
        return new OpenAIProvider(
            envStr('OPENAI_API_KEY', ''),
            envStr('OPENAI_MODEL', 'gpt-4.1') ?? 'gpt-4.1',
            envStr('OPENAI_BASE_URL', 'https://api.openai.com/v1') ?? 'https://api.openai.com/v1',
            (int) (envStr('OPENAI_TIMEOUT', '45') ?? '45'),
            (int) (envStr('OPENAI_CONNECT_TIMEOUT', '15') ?? '15')
        );
    }

    private function assistantContext(array $overrides = []): array
    {
        $route = trim((string) ($_GET['screen'] ?? $_GET['source_route'] ?? $_GET['route'] ?? ''));
        $module = trim((string) ($overrides['Module'] ?? $this->moduleFromRoute($route)));
        return [
            'FiscalYearID' => (int) SessionHelper::get('FiscalYearID', 0),
            'VersionID' => (int) SessionHelper::get('VersionID', 0),
            'Module' => $module,
            'Screen' => trim((string) ($overrides['Screen'] ?? $route)),
            'DataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
        ];
    }

    private function moduleFromRoute(string $route): string
    {
        return match (true) {
            str_starts_with($route, 'strategy') => 'Budget Strategy',
            str_starts_with($route, 'execution') => 'Budget Execution',
            str_starts_with($route, 'workflow') => 'Workflow',
            str_starts_with($route, 'training') => 'Training',
            str_starts_with($route, 'integration') => 'Integrations',
            str_starts_with($route, 'reports') => 'Reports',
            default => '',
        };
    }

    private function extractUploadedText(string $path, string $extension): string
    {
        if ($path === '' || !is_file($path)) {
            return '';
        }
        if (in_array($extension, ['txt', 'md', 'markdown', 'html', 'htm'], true)) {
            $content = file_get_contents($path);
            return is_string($content) ? $content : '';
        }
        if ($extension === 'docx' && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                return is_string($xml) ? trim(html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8')) : '';
            }
        }
        return '';
    }

    private function extractHelpFileText(string $path): string
    {
        $content = file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            return '';
        }

        $content = preg_replace('/<\?(?:php)?[\s\S]*?\?>/i', ' ', $content) ?? $content;
        $content = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', ' ', $content) ?? $content;
        $content = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', ' ', $content) ?? $content;
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        return trim($content);
    }

    private function titleFromHelpFileName(string $fileName): string
    {
        $base = preg_replace('/\.en\.php$/i', '', $fileName) ?? $fileName;
        $title = preg_replace('/(?<!^)([A-Z])/', ' $1', $base) ?? $base;
        $title = trim(str_replace(['  ', '_', '-'], ' ', $title));
        return $title !== '' ? $title . ' Help' : 'CBMS Help';
    }

    private function moduleFromHelpFileName(string $fileName): string
    {
        $base = strtolower($fileName);
        return match (true) {
            str_contains($base, 'strategy') => 'Budget Strategy',
            str_contains($base, 'workflow') => 'Workflow',
            str_contains($base, 'training') => 'Training',
            str_contains($base, 'screentest') || str_contains($base, 'screen') => 'Testing',
            str_contains($base, 'integration') => 'Integrations',
            str_contains($base, 'dataobject') => 'Organisation',
            str_contains($base, 'fiscal') || str_contains($base, 'version') || str_contains($base, 'currency') => 'System Configuration',
            default => 'General',
        };
    }

    private function installScriptPath(): string
    {
        return 'backend-php/config/sql/create_ai_knowledge_assistant_v1.sql';
    }
}
