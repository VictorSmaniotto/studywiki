<?php

namespace App\Services\AI;

use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Services\Retrieval\Escopo;
use App\Services\Retrieval\RetrievalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Prism\Prism\Structured\Response as StructuredResponse;

abstract class AbstractGenerator
{
    protected const MODELO = 'claude-sonnet-4-6';

    protected const MAX_TENTATIVAS = 2;

    protected const MAX_CHUNKS_POR_CHAMADA = 30;

    public function __construct(
        protected readonly RetrievalService $retrieval,
        protected readonly GroundingValidator $validator,
    ) {}

    abstract protected function tipo(): string;

    /** Chama o LLM com os chunks fornecidos e retorna a resposta estruturada. */
    abstract protected function chamarLLM(array $chunks): StructuredResponse;

    /**
     * Valida ancoragem do payload gerado contra os chunks recuperados.
     *
     * @param  array<string, mixed>  $payload  Resposta estruturada completa do LLM
     * @param  list<array<string, mixed>>  $chunks
     */
    abstract protected function validarConteudo(array $payload, array $chunks): bool;

    /**
     * Extrai os pagina_ids únicos do payload para registrar em GeracaoFonte.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function extrairPaginaIds(array $payload): Collection;

    protected function executarPipeline(Escopo $escopo): Geracao
    {
        $chunks = $escopo->query !== null
            ? $this->retrieval->forQuery($escopo->query, $escopo, static::MAX_CHUNKS_POR_CHAMADA)
            : $this->retrieval->forScope($escopo);

        if (empty($chunks)) {
            return $this->persistir([], $escopo, 'rejeitado', 0, 0);
        }

        $totalTokens = 0;
        $ultimoPayload = [];
        $regeneracoes = 0;

        for ($tentativa = 1; $tentativa <= static::MAX_TENTATIVAS; $tentativa++) {
            $response = $this->chamarLLM($chunks);
            $totalTokens += $response->usage->promptTokens + $response->usage->completionTokens;
            $payload = $response->structured;
            $ultimoPayload = $payload;

            if ($tentativa > 1) {
                $regeneracoes++;
            }

            if ($this->validarConteudo($payload, $chunks)) {
                return $this->persistir($payload, $escopo, 'ok', $totalTokens, $regeneracoes);
            }
        }

        return $this->persistir($ultimoPayload, $escopo, 'rejeitado', $totalTokens, $regeneracoes);
    }

    protected function persistir(
        array $payload,
        Escopo $escopo,
        string $status,
        int $tokens,
        int $regeneracoes,
    ): Geracao {
        return DB::transaction(function () use ($payload, $escopo, $status, $tokens, $regeneracoes) {
            $geracao = Geracao::create([
                'tipo' => $this->tipo(),
                'escopo' => [
                    'disciplina' => $escopo->disciplina,
                    'tags' => $escopo->tags,
                    'paginas' => $escopo->paginas,
                    'query' => $escopo->query,
                ],
                'status' => $status,
                'payload' => $payload,
                'custo_tokens' => $tokens,
                'modelo' => static::MODELO,
                'regeneracoes' => $regeneracoes,
            ]);

            if ($status === 'ok') {
                foreach ($this->extrairPaginaIds($payload)->unique() as $paginaId) {
                    GeracaoFonte::firstOrCreate([
                        'geracao_id' => $geracao->id,
                        'pagina_id' => $paginaId,
                    ]);
                }
            }

            return $geracao;
        });
    }

    /** @param array<int, array<string, mixed>> $chunks */
    protected function amostrarChunks(array $chunks): array
    {
        if (count($chunks) <= static::MAX_CHUNKS_POR_CHAMADA) {
            return $chunks;
        }

        $porPagina = [];
        foreach ($chunks as $chunk) {
            $porPagina[$chunk['pagina_id']][] = $chunk;
        }

        $paginas = array_keys($porPagina);
        $resultado = [];
        $i = 0;

        while (count($resultado) < static::MAX_CHUNKS_POR_CHAMADA) {
            $pagina = $paginas[$i % count($paginas)];
            if (! empty($porPagina[$pagina])) {
                $resultado[] = array_shift($porPagina[$pagina]);
            }
            $i++;

            if (array_sum(array_map('count', $porPagina)) === 0) {
                break;
            }
        }

        return $resultado;
    }
}
