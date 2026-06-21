---
titulo: Plano técnico (o "como")
tipo: plan
criado: 2026-06-16
---

# Plano técnico

## Arquitetura

```
        Obsidian Vault (.md + frontmatter)         ← fonte da verdade, READ-ONLY
                    │  studywiki:sync (hash diff)
                    ▼
  ┌─────────────────────────────────────────────────────────┐
  │ Postgres + pgvector                                      │
  │  Disciplina · Pagina · Tag · Chunk(embedding?) · Geracao │
  └─────────────────────────────────────────────────────────┘
        │ Retrieval estruturado (F1)   │ Retrieval híbrido vetor+FTS+rerank (F4)
        ▼                              ▼
  ┌─────────────────────────────────────────────────────────┐
  │ app/Services/AI  (prism-php → Anthropic)                 │
  │  recupera → gera (JSON) → VERIFICA (ancoragem) → persiste│
  │  Resumo · Flashcards · Simulado                          │
  └─────────────────────────────────────────────────────────┘
        │ Livewire 4 (aluno)         │ Filament 5 (admin/observabilidade)
        ▼                            ▼
                     Front
```

## Stack e dependências (não adicionar fora desta lista sem ADR)
- `laravel/framework` ^13 · PHP 8.4 · `livewire/livewire` ^4 · `filament/filament` ^5 · `pestphp/pest` ^4.
- `pgvector/pgvector` (extensão) + driver; `prism-php/prism` para LLM e embeddings (Anthropic + OpenAI embeddings).
- `league/commonmark` ou parser markdown + `spatie/yaml-front-matter` para ler a vault.
- `chart.js` ^4 (npm, T6.4) — gráficos de evolução via Alpine.js na DisciplinaPage e no painel Filament.

## Contratos principais
- `studywiki:sync` → idempotente; re-processa só o que mudou (hash). 
- `RetrievalService::forScope(Scope $s): Collection<Chunk>` → sempre devolve chunks com `pagina_id` + `heading_path` + score.
- `Generator::generate(Scope $s, Options $o): Geracao` → nunca persiste item não-ancorado; `status=rejeitado` em vez de entregar lixo.
- `GroundingValidator::validate(item, chunks): bool` → o juiz determinístico, separado do gerador.

## Decisões registradas
- Retrieval estruturado antes de vetor → ADR-0001.
- pgvector + `text-embedding-3-small` 1536d, embedding versionado por chunk → ADR-0002.
- Vault read-only (app nunca escreve nela) → regra 2 do CLAUDE.md.

## Faseamento (ratchet — não pule fases)
- **F0** Scaffold: Laravel + Postgres/pgvector + migrations do modelo + `sync` lendo a vault.
- **F1** Retrieval estruturado + **gerador Simulado** ancorado, rodando no CLI com teste de ancoragem. (É o pedaço de maior valor e maior risco — começa por ele.)
- **F2** Geradores Resumo e Flashcards (mesma pipeline).
- **F3** Front fino (Livewire) + admin Filament.
- **F4** RAG vetorial: embeddings, retrieval híbrido + rerank, para queries cross-corpus.
- **F5** (opcional) repetição espaçada nos flashcards; dashboard de custo/histórico.

Por que essa ordem: valor e risco moram no gerador ancorado, não no front nem no vetor. Provar a ancoragem no CLI primeiro evita construir UI sobre um gerador que alucina. Vetor só quando o estruturado parar de escalar.
