---
titulo: Arquitetura e Modelo de Dados
tipo: spec
nivel_de_rigor: firme
criado: 2026-06-16
---

# 01 — Arquitetura e Modelo de Dados

> Spec **firme**: reverter o modelo de dados ou a estratégia de chunking/embedding obriga re-indexar tudo. Decida com cuidado aqui.

## Princípio de retrieval (ver ADR-0001)
**Estruturado primeiro, vetor depois.** A vault já tem índice e frontmatter — isso é retrieval determinístico de graça. Vetor (pgvector) só entra na Fase 4, para queries cross-corpus/semânticas.

## Sincronização da vault (read-only)
- Comando `studywiki:sync` varre o diretório da vault (path em `.env`: `OBSIDIAN_VAULT_PATH`).
- Para cada `.md`: lê frontmatter (`titulo`, `tipo`, `tags`, `disciplina`, `fontes`, `criado`, `atualizado`) + corpo.
- Detecta mudança por hash do conteúdo. Arquivo novo/alterado → re-processa; removido → marca `deleted_at`.
- **Nunca escreve na vault.**

## Modelo de dados

```
Disciplina:  id, nome, slug, timestamps
Pagina:      id, disciplina_id?, tipo(disciplina|conceito|autor|fonte|sintese),
             titulo, slug, path_relativo, frontmatter(jsonb), corpo(text),
             hash, atualizado_na_vault, deleted_at?, timestamps
Tag:         id, nome, slug
pagina_tag:  pagina_id, tag_id            (N:N)
Chunk:       id, pagina_id, ordem, conteudo(text), heading_path, tokens,
             embedding(vector(1536))?, embedding_model?, timestamps   ← embedding nulo até Fase 4
Geracao:     id, tipo(resumo|flashcards|simulado), escopo(jsonb: disciplina/tags/paginas),
             status(ok|rejeitado), payload(jsonb), custo_tokens, modelo, timestamps
GeracaoFonte: geracao_id, pagina_id, chunk_id?   ← rastreabilidade obrigatória (regra 1 do CLAUDE.md)
```

Relacionamentos: Disciplina hasMany Pagina · Pagina hasMany Chunk · Pagina belongsToMany Tag · Geracao belongsToMany Pagina (via GeracaoFonte).

## Chunking (decidido — ADR-0002)
- **Por seção de markdown** (heading-aware): respeita `#`/`##`/`###`, guarda `heading_path` (ex: "Compiladores > Análise Léxica").
- Alvo ~400–512 tokens por chunk, overlap 10–20%. Frontmatter vira metadado do chunk, não conteúdo.
- Cada chunk sabe a página e o heading de origem → toda citação aponta para `pagina#heading`.

## Embeddings (Fase 4 — ADR-0002)
- `text-embedding-3-small` (1536d) como default, via prism-php. **Guarde `embedding_model` por chunk** — trocar de modelo = re-embed, então é versionado.
- Index pgvector HNSW, distância cosseno.

## Retrieval (contrato)
- **Estruturado (Fase 1):** dado `escopo` (disciplina/tags/páginas), carrega as páginas/chunks relevantes via SQL + frontmatter. Para a maioria das queries por disciplina, o subconjunto cabe em contexto.
- **Híbrido (Fase 4):** vetor (cosseno) + full-text (Postgres `tsvector`) + rerank, com filtro de metadado por `disciplina`/`tags`. Usado quando o escopo é amplo/semântico.
- Toda função de retrieval retorna chunks **com** `pagina_id`, `heading_path` e score — a fonte viaja junto do texto, sempre.
