---
titulo: Geradores Ancorados (resumo, flashcards, simulado)
tipo: spec
nivel_de_rigor: firme
criado: 2026-06-16
---

# 02 — Geradores Ancorados

> Spec **firme**: é aqui que mora o risco do projeto (alucinação). A regra "nunca invente" precisa ser critério verificável, não adjetivo no prompt.

## Anatomia comum (todos os geradores)
Cada gerador é a mesma pipeline (padrão do harness): **recupera → gera (saída estruturada JSON) → verifica → persiste**.

1. **Recupera** os chunks do escopo (retrieval do `specs/01`), com `pagina_id` + `heading_path`.
2. **Gera** com `claude-sonnet-4-6`, system prompt que injeta os chunks **delimitados como dado** (não instrução) e exige JSON no schema do gerador. Instrução central: *use exclusivamente o conteúdo fornecido; se faltar base, retorne menos itens, nunca invente.*
3. **Verifica** (gerador ≠ verificador — regra 4 do CLAUDE.md): um passo determinístico valida ancoragem. Falhou → rejeita e regenera (máx. 2 tentativas); persistente → marca `status=rejeitado` e reporta, não entrega lixo.
4. **Persiste** em `Geracao` + `GeracaoFonte` (toda fonte registrada).

## Contrato de ancoragem (AC global, vale para os três)
- **AC-G1:** todo item gerado referencia ≥1 `pagina_id`/`chunk_id` que estava no contexto recuperado.
- **AC-G2:** o validador rejeita item cuja referência não existe no escopo (fonte fantasma).
- **AC-G3:** nenhum item depende de fato que não aparece nos chunks recuperados (checagem por overlap léxico/semântico do item com os chunks citados acima de um limiar; abaixo → rejeita).

## Gerador: Resumo
- Input: escopo (disciplina/tópico). Output JSON: `{ titulo, secoes: [{ heading, bullets: [{texto, fontes:[ref]}] }], fontes_globais:[ref] }`.
- AC-R1: cada bullet cita fonte. AC-R2: não introduz conceito ausente nos chunks. AC-R3: resumo é mais curto que a soma dos chunks (senão não é resumo).

## Gerador: Flashcards
- Output JSON: `{ cards: [{ frente, verso, fontes:[ref], disciplina, tags }] }`.
- AC-F1: `verso` suportado pelo texto-fonte citado. AC-F2: `frente` é pergunta/conceito, `verso` é resposta objetiva. AC-F3: sem card duplicado no mesmo baralho.
- (Fase 5 opcional: repetição espaçada — campo `proxima_revisao` por card.)

## Gerador: Simulado (porta a operação TEST do CLAUDE.md original)
- Input: escopo + opções (`quantidade` default 5, `dificuldade` facil|medio|dificil).
- Output JSON: `{ questoes: [{ contexto, enunciado, formato(direto|I_II_III), alternativas:{a..e}, correta, fontes:[ref], comentario_gabarito:{a..e} }] }`.
- Regras (do CLAUDE.md original): parágrafo de contexto antes; alternativas a–e, **uma** correta; misturar formato direto e I/II/III conforme o conteúdo; distratores **plausíveis** (conceito real usado errado/invertido, não absurdo).
- **Verificador (o passo que mais importa):**
  - AC-S1: a alternativa `correta` é suportada pelos chunks citados (overlap acima do limiar).
  - AC-S2: **nenhum distrator é também correto** — cada distrator é checado contra as fontes; se um distrator também se sustenta, a questão é ambígua → rejeita e regenera.
  - AC-S3: todo `comentario_gabarito` explica por que cada distrator está errado **com referência à página** (igual ao gabarito comentado da operação TEST).
- Fluxo de uso: gera (sem revelar respostas) → aluno responde → mostra gabarito comentado + links de fonte → registra resultado (`N acertos de M`) para histórico.

## Telemetria mínima (desde o início, barato)
Toda `Geracao` registra `custo_tokens`, `modelo`, `status` e nº de regenerações. Permite ver custo por simulado e taxa de rejeição do verificador (se subir, o prompt/retrieval regrediu).
