# ADR-0001: Retrieval estruturado antes de RAG vetorial

- Status: Accepted — 2026-06-16
- Revisão prevista: quando o corpus passar de ~comportar-se mal em queries amplas

## Context
A vault já tem índice e frontmatter (disciplina, tags, tipo). A maioria das queries é por disciplina/tópico, e o subconjunto relevante cabe na janela de contexto. RAG vetorial adiciona pipeline (embeddings, índice, re-embed em troca de modelo) e custo.

## Decision
Implementar retrieval estruturado (SQL + frontmatter) nas Fases 1–3. RAG vetorial (pgvector) só na Fase 4, para queries cross-corpus/semânticas onde o escopo amplo estoura o contexto.

## Consequences
- Positiva: entrega valor (geradores ancorados) sem o peso do vetor; menos a manter no início.
- Positiva: o contrato de retrieval (chunks com fonte) é o mesmo nos dois modos — F4 troca a implementação, não o contrato.
- Negativa: queries semânticas amplas ("onde já vi esse conceito?") só funcionam bem na F4.
- Supersede qualquer discussão de "começar com vetor" até a F4 doer.

## Compliance
`tasks.md` proíbe embeddings antes da T4.1; CLAUDE.md regra 5.
