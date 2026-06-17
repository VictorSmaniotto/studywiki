---
description: Pega a próxima task não concluída em tasks.md e executa via MALT, com testes.
argument-hint: "[id opcional, ex: T1.3]"
allowed-tools: Read, Edit, Write, Bash
---
Leia @tasks.md, @progress.md e o @CLAUDE.md.

0. **Mem0:** `mcp__mem0__search_memories` com os termos da task (ex: "sync vault", "chunking", "retrieval") usando `agent_id: "studywiki"`. Incorpore o que for relevante antes de começar.
1. Identifique a próxima task não marcada — ou a task **$ARGUMENTS**, se eu informei uma.
2. Anuncie qual task vai fazer e o critério de aceite (AC) dela.
3. Implemente seguindo **MALT** (skill `malt-laravel`): Modelagem → Ação → Lógica → Teste.
4. Escreva os testes Pest do AC e rode `./vendor/bin/sail artisan test`.
5. Vermelho → conserte antes de seguir. Verde → marque a task em `tasks.md` e atualize `progress.md` (feito / fazendo / falta / decisões).
6. **Mem0:** `mcp__mem0__add_memory` com a decisão-chave da task e o motivo (`agent_id: "studywiki"`). Nunca salve valores de secrets.
7. **PARE.** Mostre o diff resumido e o resultado dos testes. Não comece a próxima task sem meu OK.

Respeite as regras do CLAUDE.md: nunca escreva na vault; todo gerador de IA é ancorado e verificado; estruturado antes de vetor.
