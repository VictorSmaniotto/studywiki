# StudyWiki RAG — pacote de planejamento (pronto para o Claude Code)

App que transforma sua vault Obsidian de estudos em base consultável com geradores de IA ancorados (resumo, flashcards, simulado). Este repositório contém **o planejamento SDD completo** — o Claude Code constrói a partir daqui.

## Como usar
1. Crie um repositório vazio e jogue estes arquivos dentro (mantendo a estrutura `specs/`, `docs/adr/`).
2. Ajuste o que for seu: em `progress.md`, o path da vault; em `plan.md`, versões se quiser.
3. Abra o **Claude Code** na raiz. Ele lê o `CLAUDE.md` (o harness) automaticamente.
4. Diga: *"Leia o CLAUDE.md, specs/ e tasks.md. Execute a tasks.md em ordem, uma task por vez, seguindo MALT, rodando os testes e atualizando o progress.md."*
5. Revise cada task ao fim (o gerador ancorado, T1.3, é o que mais merece sua revisão).

## O que cada arquivo é
- `CLAUDE.md` — o harness: stack, regras não-negociáveis (ancoragem, vault read-only, MALT), permissões deny-first, loop de trabalho.
- `specs/00-visao.md` — o porquê e o escopo (uso pessoal). `01` — dados/retrieval (spec firme). `02` — geradores ancorados + contrato anti-alucinação (spec firme). `03` — front (leve).
- `plan.md` — arquitetura, stack, contratos, faseamento.
- `tasks.md` — o backlog que o Claude Code executa.
- `docs/adr/` — decisões que envelhecem (retrieval estruturado-primeiro; pgvector/embeddings).
- `progress.md` — estado da obra entre sessões.

## Princípios de projeto (por que está assim)
- **Spec proporcional ao risco:** só `01` e `02` são firmes (reverter custa caro); o resto é leve.
- **Estruturado antes de vetor:** RAG vetorial só na Fase 4, quando o retrieval estruturado parar de escalar.
- **Gerador ≠ verificador:** toda saída de IA passa por um juiz determinístico de ancoragem antes de persistir. Item sem fonte é bug.
- **A vault é sua; o app só lê.** Apagar o app não apaga seus estudos.
