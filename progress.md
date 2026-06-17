---
titulo: Progresso (estado da obra)
tipo: progress
atualizado: 2026-06-17 (sessão 3)
---

# Progresso

> Claude Code: leia no início de cada sessão, atualize no fim. Formato: o que foi feito / o que falta / decisões.

## Feito
- **T0.1** — Scaffold Laravel 13 + Postgres 16 + pgvector. Migration `create_vector_extension` via `pgvector/pgvector` service provider. `SELECT '[1,2,3]'::vector;` OK. Suíte verde (2/2).
- **T0.2** — Migrations + Models: Disciplina, Pagina (SoftDeletes), Tag, pagina_tag (pivot), Chunk (embedding vector(1536) nullable), Geracao (`$table='geracoes'`), GeracaoFonte (id + unique geracao_id+pagina_id). Factories + 6 testes Pest verdes (8/8 total). Decisão: `geracao_fontes` ganhou coluna `id` para compatibilidade com Eloquent; rastreabilidade garantida via unique(geracao_id, pagina_id).
- **T0.3** — `studywiki:sync` + `VaultSyncService`. Varre vault recursivamente, faz upsert por hash SHA-256, soft-delete de removidos, sincroniza disciplina e tags do frontmatter. 20/20 testes verdes. Bugs corrigidos no sync real contra a vault: (1) YAML inválido no frontmatter tratado com try/catch; (2) `firstOrCreate` de Tag/Disciplina busca por `slug` (não `nome`) para evitar UniqueConstraintViolation com variantes acentuadas; (3) páginas "ignoradas" re-sincronizam tags do frontmatter salvo para recuperar syncs interrompidos por crash.
- **T0.4** — `ChunkingService` heading-aware + integração no `VaultSyncService`. Quebra por `#`/`##`/`###`, guarda `heading_path` hierárquico ("A > B > C"), alvo 400 tokens / máx 512, overlap 15%, parágrafos sem `\n\n` são subdivididos por palavras via `splitByWords`. `embedding` nulo. 30/30 testes verdes.

## Fazendo agora
- Próxima task: **T1.1** (RetrievalService estruturado).

## Falta
- T1.1 e Fases 1–5 conforme `tasks.md`.

## Decisões tomadas (resumo; detalhe em docs/adr)
- Retrieval estruturado antes de vetor (ADR-0001).
- pgvector + text-embedding-3-small, embedding versionado (ADR-0002).
- Vault read-only; geradores sempre ancorados e verificados.
- `OBSIDIAN_VAULT_PATH = /var/www/vault` dentro do container Sail. A vault Windows (`C:\Users\Interfocus\Documents\engenharia-de-software-estudos`) é montada como `/var/www/vault:ro` no serviço `laravel.test` do `compose.yaml` (não no pgsql — bug corrigido).
- Harness reforçado: guard-bash bloqueia leitura de `.env` e chave Anthropic inline; hooks sem Sail bloqueiam em vez de fazer fallback para `php artisan`; commands atualizados para `./vendor/bin/sail artisan`; mem0 integrado com seção no CLAUDE.md e passos no `/proxima-task`.

## Aberto / a confirmar com o dono
- Provider/chave de embeddings (OpenAI) para a Fase 4.
