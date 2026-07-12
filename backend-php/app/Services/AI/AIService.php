<?php
declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIKnowledgeModel;

final class AIService
{
    public function __construct(
        private AIKnowledgeModel $knowledge,
        private AIProviderInterface $provider
    ) {
    }

    public function answer(string $question, array $context, bool $includeDeveloper = false, bool $includeAdmin = false): array
    {
        $started = microtime(true);
        $chunks = $this->knowledge->searchChunks($question, $context, $includeDeveloper, $includeAdmin, 6);

        if ($chunks === []) {
            return [
                'answer' => 'I could not find this information in the approved CBMS knowledge base for the current context.',
                'sources' => [],
                'provider' => $this->provider->code(),
                'model' => $this->provider->model(),
                'usage' => [],
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'provider_error' => null,
            ];
        }

        $sources = $this->sourcesFromChunks($chunks);
        $input = $this->buildInput($question, $context, $chunks);
        $instructions = implode("\n", [
            'You are the CBMS Knowledge Assistant.',
            'Answer only using the supplied CBMS documentation excerpts.',
            'If the excerpts do not contain the answer, state that the information could not be found.',
            'Do not guess and do not make budgeting decisions.',
            'Always cite source labels from the supplied excerpts, for example [S1] or [S2].',
        ]);

        $providerError = null;
        $usage = [];
        try {
            $generated = $this->provider->generate($instructions, $input);
            $answer = trim((string) ($generated['text'] ?? ''));
            $usage = is_array($generated['usage'] ?? null) ? $generated['usage'] : [];
        } catch (\Throwable $e) {
            $providerError = $e->getMessage();
            $answer = $this->fallbackAnswer($chunks);
        }

        if ($answer === '') {
            $answer = $this->fallbackAnswer($chunks);
        }

        return [
            'answer' => $answer,
            'sources' => $sources,
            'provider' => $this->provider->code(),
            'model' => $this->provider->model(),
            'usage' => $usage,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'provider_error' => $providerError,
        ];
    }

    private function buildInput(string $question, array $context, array $chunks): string
    {
        $lines = [
            'Question: ' . $question,
            '',
            'CBMS context:',
            '- FiscalYearID: ' . (string) ($context['FiscalYearID'] ?? ''),
            '- VersionID: ' . (string) ($context['VersionID'] ?? ''),
            '- Module: ' . (string) ($context['Module'] ?? ''),
            '- Screen: ' . (string) ($context['Screen'] ?? ''),
            '',
            'Approved excerpts:',
        ];

        foreach ($chunks as $index => $chunk) {
            $label = 'S' . ($index + 1);
            $lines[] = '[' . $label . '] ' . (string) ($chunk['Title'] ?? 'Untitled') . ', chunk ' . (string) ($chunk['ChunkNumber'] ?? '');
            $lines[] = trim((string) ($chunk['ChunkText'] ?? ''));
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function fallbackAnswer(array $chunks): string
    {
        $lines = ['I found relevant approved CBMS documentation, but the AI provider is not currently available. Key excerpts:'];
        foreach (array_slice($chunks, 0, 3) as $index => $chunk) {
            $text = trim((string) ($chunk['ChunkText'] ?? ''));
            if (strlen($text) > 450) {
                $text = substr($text, 0, 447) . '...';
            }
            $lines[] = '[S' . ($index + 1) . '] ' . $text;
        }
        return implode("\n\n", $lines);
    }

    private function sourcesFromChunks(array $chunks): array
    {
        $sources = [];
        foreach ($chunks as $index => $chunk) {
            $sources[] = [
                'label' => 'S' . ($index + 1),
                'document_id' => (int) ($chunk['DocumentID'] ?? 0),
                'chunk_id' => (int) ($chunk['ChunkID'] ?? 0),
                'title' => (string) ($chunk['Title'] ?? 'Untitled'),
                'category' => (string) ($chunk['Category'] ?? ''),
                'module' => (string) ($chunk['Module'] ?? ''),
                'chunk_number' => (int) ($chunk['ChunkNumber'] ?? 0),
                'source_page' => (string) ($chunk['SourcePage'] ?? ''),
                'url' => 'index.php?route=ai-knowledge/chunks&id=' . (int) ($chunk['DocumentID'] ?? 0) . '#chunk-' . (int) ($chunk['ChunkNumber'] ?? 0),
            ];
        }
        return $sources;
    }
}
