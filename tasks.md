---
titulo: Backlog executável
tipo: tasks
criado: 2026-06-16
---

# Tasks

> Execute em ordem, uma por vez. Cada feature segue **MALT** (skill `malt-laravel`): Modelagem → Ação → Lógica → Teste.
> Após cada task: `php artisan test` verde + atualizar `progress.md`. AC = critério de aceite.

## Fase 0 — Scaffold
- [ ] T0.1 Criar projeto Laravel 12, configurar Postgres, habilitar extensão `pgvector`. AC: `php artisan migrate` roda; `SELECT '[1,2,3]'::vector;` funciona.
- [ ] T0.2 (M) Migrations + Models do `specs/01`: Disciplina, Pagina, Tag, pivot, Chunk (coluna `embedding vector(1536) null`), Geracao, GeracaoFonte, com `$fillable`/`$casts`/relacionamentos. AC: factories criam o grafo; teste com `Pagina::with('chunks','tags')` passa.
- [x] T0.3 Command `studywiki:sync`: varre `OBSIDIAN_VAULT_PATH`, lê frontmatter + corpo, upsert por hash, marca removidos. AC: rodar 2x não duplica; alterar 1 arquivo re-processa só ele; **nunca escreve na vault** (teste assertando que a vault não muda).
- [x] T0.4 (L) Chunking heading-aware no sync: quebra por seção, ~400–512 tokens, overlap, guarda `heading_path`. AC: página com 3 headings vira ≥3 chunks com `heading_path` correto. (embedding fica nulo até F4.)

## Fase 1 — Simulado ancorado (núcleo)
- [x] T1.1 `RetrievalService::forScope` estruturado (disciplina/tags/páginas → chunks com fonte). AC: retorna chunks com `pagina_id`+`heading_path`; respeita filtro de disciplina.
- [ ] T1.2 `GroundingValidator` determinístico (AC-G1..G3 do `specs/02`). AC (Pest, sem chamar LLM): item ancorado passa; item com fonte fantasma reprova; distrator também-correto reprova.
- [ ] T1.3 `SimuladoGenerator` (prism-php → Anthropic, saída JSON do `specs/02`): recupera → gera → valida → persiste; rejeita+regenera (máx 2). AC: schema válido; toda questão com `fontes`; `status=rejeitado` quando não ancora (mock do LLM nos testes).
- [ ] T1.4 Command `studywiki:simulado {disciplina} {--n=5} {--dif=medio}` imprime simulado + (depois) gabarito comentado. AC: roda no CLI ponta a ponta numa disciplina real da vault.

## Fase 2 — Resumo e Flashcards
- [ ] T2.1 `ResumoGenerator` (AC-R1..R3). AC: cada bullet com fonte; resumo < soma dos chunks.
- [ ] T2.2 `FlashcardsGenerator` (AC-F1..F3). AC: verso ancorado; sem duplicado.
- [ ] T2.3 Reuso: extrair pipeline comum (recupera→gera→valida→persiste) para um `AbstractGenerator`. AC: os 3 geradores compartilham o verificador; teste de cada AC continua verde.

## Fase 3 — Front
- [ ] T3.1 (A/L) Livewire: Biblioteca → Disciplina → botões de geração. AC: lista vem do `sync`; gerar dispara o serviço e mostra resultado com fontes.
- [ ] T3.2 Tela de Simulado: responde a–e, envia, mostra gabarito comentado com links de fonte, salva `N de M`. AC: histórico persistido; fonte clicável.
- [ ] T3.3 Admin Filament: disparar sync, listar Geracao (status/custo/regenerações), inspecionar chunks. AC: painel mostra taxa de rejeição do verificador.

## Fase 4 — RAG vetorial (quando o estruturado não escalar)
- [ ] T4.1 Command `studywiki:embed`: gera embeddings dos chunks (text-embedding-3-small), grava `embedding`+`embedding_model`; index HNSW cosseno. AC: re-rodar não re-embeda chunk inalterado.
- [ ] T4.2 Retrieval híbrido: vetor + full-text (`tsvector`) + rerank, com filtro de metadado. AC: query cross-disciplina recupera chunk relevante não-óbvio por tag; fonte viaja junto.
- [ ] T4.3 Plugar híbrido nos geradores para escopo amplo ("simulado de tudo"). AC: ancoragem (AC-G*) continua válida no modo híbrido.

## Fase 5 — Opcional
- [ ] T5.1 Repetição espaçada nos flashcards (`proxima_revisao`). 
- [ ] T5.2 Dashboard de custo/histórico de desempenho por disciplina.
