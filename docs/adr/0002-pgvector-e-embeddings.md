# ADR-0002: pgvector + text-embedding-3-small, embedding versionado por chunk

- Status: Accepted — 2026-06-16
- Revisão prevista: se o volume justificar índice dedicado, ou se houver embedding melhor/mais barato

## Context
Dono usa Postgres no stack Laravel. Trocar de modelo de embedding obriga re-indexar tudo — é a decisão mais cara de reverter do projeto.

## Decision
Vetores em Postgres via **pgvector** (index HNSW, distância cosseno). Embedding default **text-embedding-3-small** (1536d) via prism-php. **Guardar `embedding_model` em cada chunk**; chunking heading-aware (~400–512 tokens, overlap 10–20%).

## Consequences
- Positiva: sem serviço de vetor separado; usa o banco que já existe.
- Positiva: `embedding_model` por chunk permite migração incremental se trocar de modelo.
- Negativa: re-embed total ao trocar de modelo continua sendo um batch caro (mitigado, não eliminado).
- Negativa: pgvector exige extensão no Postgres (passo no T0.1).

## Compliance
Migration do Chunk inclui `embedding vector(1536)` + `embedding_model`; T4.1 não re-embeda chunk inalterado.
