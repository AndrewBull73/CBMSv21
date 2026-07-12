<?php
declare(strict_types=1);

namespace App\Services\AI;

interface AIProviderInterface
{
    public function code(): string;

    public function model(): ?string;

    public function generate(string $instructions, string $input): array;
}
