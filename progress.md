---
titulo: Progresso (estado da obra)
tipo: progress
atualizado: 2026-06-17 (sessão 4)
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

## Fazendo agora
- Próxima task: **T2.1** (ResumoGenerator).

## Falta
- T2.1 e Fases 2–6 conforme `tasks.md`.

## Decisões tomadas (resumo; detalhe em docs/adr)
- Retrieval estruturado antes de vetor (ADR-0001).
- pgvector + text-embedding-3-small, embedding versionado (ADR-0002).
- Vault read-only; geradores sempre ancorados e verificados.
- `OBSIDIAN_VAULT_PATH = /var/www/vault` dentro do container Sail. A vault Windows (`C:\Users\Interfocus\Documents\engenharia-de-software-estudos`) é montada como `/var/www/vault:ro` no serviço `laravel.test` do `compose.yaml` (não no pgsql — bug corrigido).
- Harness reforçado: guard-bash bloqueia leitura de `.env` e chave Anthropic inline; hooks sem Sail bloqueiam em vez de fazer fallback para `php artisan`; commands atualizados para `./vendor/bin/sail artisan`; mem0 integrado com seção no CLAUDE.md e passos no `/proxima-task`.

## Aberto / a confirmar com o dono
- Provider/chave de embeddings (OpenAI) para a Fase 4.
