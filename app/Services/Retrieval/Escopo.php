<?php

namespace App\Services\Retrieval;

class Escopo
{
    /**
     * @param  int[]  $paginas
     * @param  string[]  $tags
     */
    public function __construct(
        public readonly ?string $disciplina = null,
        public readonly array $tags = [],
        public readonly array $paginas = [],
        public readonly ?string $query = null,
        public readonly ?int $temaId = null,
    ) {}

    public function vazio(): bool
    {
        return $this->disciplina === null && $this->tags === [] && $this->paginas === [] && $this->temaId === null;
    }
}
