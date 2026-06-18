---
titulo: Progresso (estado da obra)
tipo: progress
atualizado: 2026-06-17 (sessão 6)
---

# Progresso

> Claude Code: leia no início de cada sessão, atualize no fim. Formato: o que foi feito / o que falta / decisões.

## Feito
- **T0.1** — Scaffold Laravel 13 + Postgres 16 + pgvector. Migration `create_vector_extension` via `pgvector/pgvector` service provider. `SELECT '[1,2,3]'::vector;` OK. Suíte verde (2/2).
- **T0.2** — Migrations + Models: Disciplina, Pagina (SoftDeletes), Tag, pagina_tag (pivot), Chunk (embedding vector(1536) nullable), Geracao (`$table='geracoes'`), GeracaoFonte (id + unique geracao_id+pagina_id). Factories + 6 testes Pest verdes (8/8 total). Decisão: `geracao_fontes` ganhou coluna `id` para compatibilidade com Eloquent; rastreabilidade garantida via unique(geracao_id, pagina_id).
- **T0.3** — `studywiki:sync` + `VaultSyncService`. Varre vault recursivamente, faz upsert por hash SHA-256, soft-delete de removidos, sincroniza disciplina e tags do frontmatter. 20/20 testes verdes. Bugs corrigidos no sync real contra a vault: (1) YAML inválido no frontmatter tratado com try/catch; (2) `firstOrCreate` de Tag/Disciplina busca por `slug` (não `nome`) para evitar UniqueConstraintViolation com variantes acentuadas; (3) páginas "ignoradas" re-sincronizam tags do frontmatter salvo para recuperar syncs interrompidos por crash.
- **T0.4** — `ChunkingService` heading-aware + integração no `VaultSyncService`. Quebra por `#`/`##`/`###`, guarda `heading_path` hierárquico ("A > B > C"), alvo 400 tokens / máx 512, overlap 15%, parágrafos sem `\n\n` são subdivididos por palavras via `splitByWords`. `embedding` nulo. 30/30 testes verdes.
- **T1.1** — `RetrievalService::forScope` estruturado. `Escopo` (value object com disciplina/tags/paginas), query com JOIN em paginas/disciplinas/tags, respeita `deleted_at`, retorna chunk_id + pagina_id + heading_path + score (null na Fase 1). 40/40 testes verdes.
- **T1.2** — `GroundingValidator` determinístico em `app/Services/AI/`. AC-G1: reprova item sem fontes. AC-G2: reprova fonte fantasma (pagina_id/chunk_id ausente no contexto). AC-G3: overlap léxico `\p{L}{3,}` item×chunks citados, limiar 10%; abaixo → reprova com detalhes. `ValidationResult` value object (aprovado/motivo/detalhes). 49/49 testes verdes.
- **T1.3** — `SimuladoGenerator` em `app/Services/AI/`. Pipeline: `RetrievalService::forScope` → Prism structured (Anthropic, `claude-sonnet-4-6`) → `GroundingValidator` por questão → persiste `Geracao`+`GeracaoFonte`. Rejeita+regenera (máx 2 tentativas). Migration `add_regeneracoes_to_geracoes` + coluna `regeneracoes` no model. Só cria `GeracaoFonte` quando `status='ok'` (fontes rejeitadas têm pagina_ids inválidos). 58/58 testes verdes.

- **T1.4** — `studywiki:simulado {disciplina} {--n=5} {--dif=medio} {--gabarito}`. Imprime questões sem gabarito, depois pede confirmação (ou `--gabarito` pula o confirm). Disciplina não encontrada lista as disponíveis. Geração rejeitada retorna FAILURE. Bug: `ã` em "Questão" + "Gabarito:" na mesma linha quebra o buffer de teste do PendingCommand — separado em duas linhas (`<fg=cyan>Questão N</>` + `Gabarito: X`). 65/65 testes verdes.

- **Sessão 4 (bugs de deploy pós-F1)** — Corrigidos 3 problemas que impediam o comando real de rodar: (1) migration `add_regeneracoes_to_geracoes` pendente — aplicada; (2) VaultSyncService não rechunkeava páginas com hash inalterado mas sem chunks (sincronizadas antes do T0.4) — corrigido com `doesntExist()` check; (3) timeout de 30s insuficiente para chamada estruturada ao Sonnet — timeout elevado para 120s em `config/prism.php` e chunks limitados a 30 por chamada (amostragem distribuída por página). Comando `studywiki:simulado` confirmado ponta a ponta com vault real. Fase 6 adicionada ao `tasks.md` com 5 features levantadas, todas marcadas como ⚠ REFINAMENTO PENDENTE.

- **T2.1** — `ResumoGenerator` em `app/Services/AI/`. Pipeline idêntica ao SimuladoGenerator: `RetrievalService::forScope` → Prism structured (Anthropic, `claude-sonnet-4-6`) → `GroundingValidator` por bullet → persiste `Geracao`+`GeracaoFonte`. AC-R1: bullet sem fontes → rejeitado. AC-R2: overlap léxico insuficiente → rejeitado. AC-R3: resumo ≥ soma dos chunks → rejeitado. Rejeita+regenera (máx 2 tentativas). 12 testes novos; 77/77 suite verde.

- **T2.2** — `FlashcardsGenerator` em `app/Services/AI/`. Pipeline idêntica ao ResumoGenerator. AC-F1: verso ancorado via GroundingValidator. AC-F2: frente e verso não-vazios (verificação estrutural). AC-F3: frentes normalizadas únicas (lowercase + strip punctuation). 15 testes novos; 92/92 suite verde.

- **T2.3** — `AbstractGenerator` (template method pattern). Extrai `executarPipeline()`, `persistir()`, `amostrarChunks()`. Subclasses implementam `tipo()`, `chamarLLM()`, `validarConteudo()`, `extrairPaginaIds()`. SimuladoGenerator: args extras como propriedades de instância setadas antes de `executarPipeline`. 3 testes de arquitetura adicionados. 95/95 suite verde.

- **T3.1** — `Biblioteca` (Livewire, search por nome) + `DisciplinaPage` (botões Gerar Resumo/Flashcards/Simulado, exibe resultado com fontes e loading state). Layout `layouts/app.blade.php`. Pacotes instalados: `livewire/livewire ^4`, `filament/filament ^5`. Filament 5 + PHP 8.4: propriedades `$navigationIcon/$navigationGroup` usam union types — substituído por métodos `getNavigationIcon()/getNavigationGroup()`. `$title` e `$navigationLabel` OK como `?string`. `$view` na Page é instância (não static). `getTitle()` é método de instância.
- **T3.2** — `SimuladoPage` (Livewire): questões uma a uma, radio a–e, enviar → gabarito comentado + fonte, salva `RespostaSimulado` (acertos/total). Migration `resposta_simulados` + model.
- **T3.3** — Filament admin: `GeracaoResource` (filtro por tipo/status, custo/regenerações), `ChunkResource` (inspeção), `SyncPage` (executa sync via artisan), `GeracaoStatsWidget` (taxa de rejeição).

- **Sessão 6 — Flux UI 2 + correções visuais** — Instalado `livewire/flux ^2.14`. Criadas: `Setting` (model key-value), migration `settings`, `ThemeSettings` page Filament (swatches de accent/base com preview ao vivo). Reescrito layout `layouts/app.blade.php` (header Flux, dark mode toggle, CSS vars dinâmicos). Reescritas telas `Biblioteca`, `DisciplinaPage` (tabs Alpine: Gerar/Resumo/Flashcards/Simulado) e `SimuladoPage` (sticky header, progress bar). Bugs corrigidos: (1) `.dark :root` → `.dark {}` para dark mode funcionar; (2) CSS inválido `var()20` → `var(--sw-accent-tint)` via `color-mix()`; (3) swatches Filament sem inline style; (4) `@custom-variant dark (&:where(.dark, .dark *))` adicionado ao `app.css` — sem isso, os `dark:` do Tailwind v4 geravam `@media (prefers-color-scheme)` em vez de seletores `.dark`, tornando texto invisível em dark mode com toggle. 115/115 testes verdes. Adicionadas T6.6 (histórico de gerações re-consultável) e T6.7 (configuração de escopo de geração) ao tasks.md, ambas com nota de impacto da Fase 4.

- **T4.1** — `studywiki:embed` + `EmbeddingService`. Provider VoyageAI `voyage-3-lite` (1024d) em vez do OpenAI planejado — decisão do dono (sem chave OpenAI, VoyageAI gratuito até 200M tokens/mês). Migration altera `chunks.embedding` de `vector(1536)` para `vector(1024)` + cria index HNSW cosseno. Idempotência garantida via `embedding_model IS NULL`. `--force` permite re-embedar em troca de modelo. 123/123 testes verdes.

- **T4.2** — `RetrievalService::forQuery(string $query, Escopo $escopo, int $limit): array`. Hybrid retrieval: vector (pgvector cosine via `<=>`) + full-text (`websearch_to_tsquery('portuguese', ...)`) mergeados com RRF (k=60). `applyEscopoFilters` extraído como private helper (usado por `forScope` e `forQuery`). Migration: GIN index em `to_tsvector('portuguese', conteudo)`. `EmbeddingService::embedQuery` adicionado. 129/129 testes verdes.

- **T4.3** — `Escopo` ganha campo `?string $query`. `AbstractGenerator::executarPipeline` despacha para `forQuery($query, $escopo, MAX_CHUNKS)` quando `query !== null`, ou `forScope` caso contrário. `persistir` inclui `query` no JSON salvo. Bug no teste G3: `contexto` da questão também precisa ser sobrescrito (não só `enunciado`), pois o validator concatena todos os campos de texto. 135/135 testes verdes.

## Fazendo agora
- **Fase 4 concluída.** Próxima fase: T5.1 (repetição espaçada) ou T6.x (Fase 6 UX) — aguardando OK do dono.

## Falta
- T4.2, T4.3, Fases 5–6 conforme `tasks.md`.

## Decisões tomadas (resumo; detalhe em docs/adr)
- Retrieval estruturado antes de vetor (ADR-0001).
- pgvector + **VoyageAI voyage-3-lite** (1024d), embedding versionado por `embedding_model` (ADR-0002 — provider mudou de OpenAI para VoyageAI por decisão do dono na sessão 7).
- Vault read-only; geradores sempre ancorados e verificados.
- `OBSIDIAN_VAULT_PATH = /var/www/vault` dentro do container Sail. A vault Windows (`C:\Users\Interfocus\Documents\engenharia-de-software-estudos`) é montada como `/var/www/vault:ro` no serviço `laravel.test` do `compose.yaml` (não no pgsql — bug corrigido).
- Harness reforçado: guard-bash bloqueia leitura de `.env` e chave Anthropic inline; hooks sem Sail bloqueiam em vez de fazer fallback para `php artisan`; commands atualizados para `./vendor/bin/sail artisan`; mem0 integrado com seção no CLAUDE.md e passos no `/proxima-task`.

## Aberto / a confirmar com o dono
- Chave `VOYAGEAI_API_KEY` deve ser adicionada ao `.env` antes de rodar `studywiki:embed` contra a vault real.
