<?php

namespace App\Services\AI;

class ValidationResult
{
    private function __construct(
        public readonly bool $aprovado,
        public readonly ?string $motivo = null,
        public readonly array $detalhes = [],
    ) {}

    public static function aprovado(): self
    {
        return new self(aprovado: true);
    }

    public static function reprovado(string $motivo, array $detalhes = []): self
    {
        return new self(aprovado: false, motivo: $motivo, detalhes: $detalhes);
    }
}
