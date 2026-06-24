<?php

namespace App\Services\Retrieval;

class Escopo
{
    /**
     * @param  int[]  $paginas
     * @param  string[]  $tags
     * @param  string[]  $disciplinas  slugs de múltiplas disciplinas (tem precedência sobre $disciplina)
     */
    public function __construct(
        public readonly ?string $disciplina = null,
        public readonly array $tags = [],
        public readonly array $paginas = [],
        public readonly ?string $query = null,
        public readonly ?int $temaId = null,
        public readonly array $disciplinas = [],
    ) {}

    public function vazio(): bool
    {
        return $this->disciplina === null
            && $this->disciplinas === []
            && $this->tags === []
            && $this->paginas === []
            && $this->temaId === null;
    }
}
