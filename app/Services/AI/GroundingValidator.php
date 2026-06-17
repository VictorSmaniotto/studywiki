<?php

namespace App\Services\AI;

class GroundingValidator
{
    // Fração mínima das palavras do item que deve aparecer nos chunks citados.
    private const OVERLAP_LIMIAR = 0.10;

    /**
     * Valida ancoragem de um item gerado contra o contexto recuperado.
     *
     * @param  array{texto: string, fontes: list<array{pagina_id: int, chunk_id?: int}>}  $item
     * @param  list<array{chunk_id: int, pagina_id: int, conteudo: string}>  $contexto
     */
    public function validate(array $item, array $contexto): ValidationResult
    {
        // AC-G1: o item deve referenciar ao menos uma fonte.
        if (empty($item['fontes'])) {
            return ValidationResult::reprovado('sem_fontes');
        }

        // AC-G1 + AC-G2: toda fonte referenciada deve existir no contexto.
        $chunksCitados = [];
        foreach ($item['fontes'] as $fonte) {
            $encontrado = $this->encontrarNoContexto($fonte, $contexto);
            if ($encontrado === null) {
                return ValidationResult::reprovado('fonte_fantasma', ['fonte' => $fonte]);
            }
            $chunksCitados[] = $encontrado;
        }

        // AC-G3: overlap léxico entre o texto do item e os chunks citados.
        $palavrasItem = $this->tokenizar($item['texto']);
        if ($palavrasItem === []) {
            return ValidationResult::reprovado('texto_vazio');
        }

        $palavrasChunks = [];
        foreach ($chunksCitados as $chunk) {
            foreach ($this->tokenizar($chunk['conteudo']) as $p) {
                $palavrasChunks[$p] = true;
            }
        }

        $intersecao = count(array_filter($palavrasItem, fn ($p) => isset($palavrasChunks[$p])));
        $overlap = $intersecao / count($palavrasItem);

        if ($overlap < self::OVERLAP_LIMIAR) {
            return ValidationResult::reprovado('overlap_insuficiente', [
                'overlap' => round($overlap, 4),
                'limiar' => self::OVERLAP_LIMIAR,
            ]);
        }

        return ValidationResult::aprovado();
    }

    /**
     * @param  array{pagina_id: int, chunk_id?: int}  $fonte
     * @param  list<array{chunk_id: int, pagina_id: int, conteudo: string}>  $contexto
     * @return array{chunk_id: int, pagina_id: int, conteudo: string}|null
     */
    private function encontrarNoContexto(array $fonte, array $contexto): ?array
    {
        foreach ($contexto as $chunk) {
            if ($chunk['pagina_id'] !== $fonte['pagina_id']) {
                continue;
            }
            if (isset($fonte['chunk_id']) && $chunk['chunk_id'] !== $fonte['chunk_id']) {
                continue;
            }

            return $chunk;
        }

        return null;
    }

    /** @return string[] */
    private function tokenizar(string $texto): array
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        preg_match_all('/\p{L}{3,}/u', $texto, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
