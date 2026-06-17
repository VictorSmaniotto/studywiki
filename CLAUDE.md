# CLAUDE.md — StudyWiki RAG (harness do projeto)

> Leia este arquivo no início de cada sessão. Ele é o contrato de como você (Claude Code) trabalha aqui.
> Toda regra abaixo veio de uma decisão real registrada em `specs/` ou `docs/adr/`. Se uma regra atrapalhar, abra um ADR — não a ignore.

## O que é
App que transforma uma vault Obsidian de estudos (markdown + frontmatter) em uma base consultável com geradores de IA: resumos, flashcards e simulados de prova **ancorados nas fontes**. Uso pessoal, um aluno. Stack do dono: Laravel.

## Stack (decidida — ver `plan.md` e ADRs)
- Laravel 13 · PHP 8.4 · Postgres 17 + **pgvector** · Livewire 4 · Filament 5 (admin/ingestão) · Pest 4.
- LLM e embeddings via **prism-php** (provider Anthropic para geração: `claude-sonnet-4-6`; embeddings `text-embedding-3-small`, 1536d). Chaves em `.env`, nunca hardcoded.

## Regras não-negociáveis
1. **Nunca invente conteúdo.** Todo resumo/flashcard/questão é derivado **exclusivamente** do texto recuperado da vault e carrega `fontes: [<page refs>]`. Item sem fonte rastreável é bug, não entrega. (Detalhe em `specs/02-geradores-ancorados.md`.)
2. **A vault é fonte da verdade e é read-only.** O app lê os arquivos markdown; nunca escreve de volta na vault. (Espelha o `raw/` imutável da wiki original.)
3. **Método MALT** para toda feature: Modelagem → Ação → Lógica → Teste, nessa ordem. Use a skill `malt-laravel`. Nunca entregue feature sem teste Pest verde.
4. **Gerador é separado do verificador.** Quem gera a questão não é quem valida que ela está ancorada. Toda saída de IA passa por um validador determinístico antes de persistir (ver `specs/02`).
5. **Estruturado primeiro, vetor depois.** Não implemente RAG vetorial antes da Fase 4. O retrieval inicial usa o índice + frontmatter (ADR-0001).

## Permissões (deny-first)
- Pode: criar/editar arquivos do projeto, rodar `php artisan`, `composer`, `npm`, `pest`, ler a vault.
- Peça antes de: rodar migration destrutiva (`migrate:fresh`) fora de teste, apagar arquivo de spec/ADR, instalar pacote não listado no `plan.md`, qualquer comando que toque dado fora do projeto.
- Nunca: escrever na pasta da vault; commitar `.env`; chamar API externa que não seja LLM/embeddings.

## Loop de trabalho
- Execute `tasks.md` em ordem, uma task por vez. Cada task tem critério de aceite (AC) e teste.
- Após cada task: rode o subagente '/test-runner'. Vermelho → conserte antes de seguir. Verde → atualize `progress.md` e siga.
- Tarefa longa cruzando sessões: leia `progress.md` no início e atualize no fim (o que foi feito / o que falta / decisões).

## Onde as coisas vivem
- `specs/` — o "o quê" (visão, dados, geradores, front). `plan.md` — o "como". `tasks.md` — o backlog executável.
- `docs/adr/` — decisões que envelhecem (imutáveis; supersede com ADR novo).
- Código: padrão Laravel. Lógica de IA em `app/Services/AI/`. Retrieval em `app/Services/Retrieval/`. Ingestão em `app/Console/Commands/`.

## Comandos úteis
`./vendor/bin/sail artisan studywiki:sync` · `./vendor/bin/sail artisan test` · `./vendor/bin/sail artisan studywiki:simulado {disciplina}` · `./vendor/bin/sail artisan studywiki:embed` (Fase 4).

> **Regra do test-runner:** sempre use `./vendor/bin/sail artisan test` (nunca `php artisan test` direto, nunca `docker exec`). Os hooks já fazem isso automaticamente; siga o mesmo padrão nos comandos manuais.

## Memória cross-session (mem0)
O projeto usa o MCP `mem0` para acumular contexto que não é derivável lendo o código.

**Quando usar:**
- **Início de sessão ou de task:** `mcp__mem0__search_memories` com os termos da task para recuperar decisões e padrões relevantes. Use `agent_id: "studywiki"`.
- **Ao concluir task:** `mcp__mem0__add_memory` com a decisão-chave tomada e o motivo (`agent_id: "studywiki"`). Uma memória por decisão, frase curta e direta.
- **Ao descobrir um padrão ou restrição nova:** registre imediatamente, não espere o fim da task.

**O que guardar:** decisões de arquitetura, regras de negócio descobertas, padrões do codebase, constraints que vieram da experiência (ex: "o container Sail precisa estar up para rodar testes").

**O que NUNCA guardar:** valores de chaves de API (`sk-ant-api…`), passwords, tokens. mem0 é serviço externo — secrets ficam no `.env`. Guardar *o fato* de que usamos Anthropic para geração é OK; o valor da chave nunca.

## Fiação do harness (`.claude/`)
Os guardrails e atalhos abaixo já estão ligados — confie neles, não os contorne.

- **Guardrails (`.claude/settings.json` + `hooks/`):** escrita fora do projeto é bloqueada (vault read-only, regra 2); `rm -rf /`, `DROP`, `git push --force` e `migrate:fresh` sem `--env=testing` são barrados; `.env` não é lido nem editado; secrets hardcoded são bloqueados antes de persistir.
- **Loop fechado:** todo `.php` editado passa pelo Pint; ao tentar finalizar, a suíte roda e, se vermelha, o encerramento é recusado até consertar.
- **Observabilidade:** todo evento (tool call, stop, prompt) vai para `.harness/events.jsonl`; uso de tokens vai para `.harness/tokens.jsonl`.
- **Commands (`.claude/commands/`):** `/proxima-task [id]` · `/sync` · `/simulado <disciplina>` · `/ancoragem [id]` · `/spec-check` · `/adr <título>`.
- **Ratchet:** quando você errar e eu corrigir, registre a correção como regra aqui ou como ADR — erro vira constraint permanente, não conserto solto.

> Requer `jq` e `python3` no PATH para os hooks (`apt install jq python3`).
