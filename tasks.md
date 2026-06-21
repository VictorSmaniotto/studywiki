---
titulo: Backlog executável
tipo: tasks
criado: 2026-06-16
---

# Tasks

> Execute em ordem, uma por vez. Cada feature segue **MALT** (skill `malt-laravel`): Modelagem → Ação → Lógica → Teste.
> Após cada task: `php artisan test` verde + atualizar `progress.md`. AC = critério de aceite.

## Fase 0 — Scaffold
- [x] T0.1 Criar projeto Laravel 12, configurar Postgres, habilitar extensão `pgvector`. AC: `php artisan migrate` roda; `SELECT '[1,2,3]'::vector;` funciona.
- [x] T0.2 (M) Migrations + Models do `specs/01`: Disciplina, Pagina, Tag, pivot, Chunk (coluna `embedding vector(1536) null`), Geracao, GeracaoFonte, com `$fillable`/`$casts`/relacionamentos. AC: factories criam o grafo; teste com `Pagina::with('chunks','tags')` passa.
- [x] T0.3 Command `studywiki:sync`: varre `OBSIDIAN_VAULT_PATH`, lê frontmatter + corpo, upsert por hash, marca removidos. AC: rodar 2x não duplica; alterar 1 arquivo re-processa só ele; **nunca escreve na vault** (teste assertando que a vault não muda).
- [x] T0.4 (L) Chunking heading-aware no sync: quebra por seção, ~400–512 tokens, overlap, guarda `heading_path`. AC: página com 3 headings vira ≥3 chunks com `heading_path` correto. (embedding fica nulo até F4.)

## Fase 1 — Simulado ancorado (núcleo)
- [x] T1.1 `RetrievalService::forScope` estruturado (disciplina/tags/páginas → chunks com fonte). AC: retorna chunks com `pagina_id`+`heading_path`; respeita filtro de disciplina.
- [x] T1.2 `GroundingValidator` determinístico (AC-G1..G3 do `specs/02`). AC (Pest, sem chamar LLM): item ancorado passa; item com fonte fantasma reprova; distrator também-correto reprova.
- [x] T1.3 `SimuladoGenerator` (prism-php → Anthropic, saída JSON do `specs/02`): recupera → gera → valida → persiste; rejeita+regenera (máx 2). AC: schema válido; toda questão com `fontes`; `status=rejeitado` quando não ancora (mock do LLM nos testes).
- [x] T1.4 Command `studywiki:simulado {disciplina} {--n=5} {--dif=medio}` imprime simulado + (depois) gabarito comentado. AC: roda no CLI ponta a ponta numa disciplina real da vault.

## Fase 2 — Resumo e Flashcards
- [x] T2.1 `ResumoGenerator` (AC-R1..R3). AC: cada bullet com fonte; resumo < soma dos chunks.
- [x] T2.2 `FlashcardsGenerator` (AC-F1..F3). AC: verso ancorado; sem duplicado.
- [x] T2.3 Reuso: extrair pipeline comum (recupera→gera→valida→persiste) para um `AbstractGenerator`. AC: os 3 geradores compartilham o verificador; teste de cada AC continua verde.

## Fase 3 — Front
- [x] T3.1 (A/L) Livewire: Biblioteca → Disciplina → botões de geração. AC: lista vem do `sync`; gerar dispara o serviço e mostra resultado com fontes.
- [x] T3.2 Tela de Simulado: responde a–e, envia, mostra gabarito comentado com links de fonte, salva `N de M`. AC: histórico persistido; fonte clicável.
- [x] T3.3 Admin Filament: disparar sync, listar Geracao (status/custo/regenerações), inspecionar chunks. AC: painel mostra taxa de rejeição do verificador.

## Fase 4 — RAG vetorial (quando o estruturado não escalar)
- [x] T4.1 Command `studywiki:embed`: gera embeddings dos chunks (text-embedding-3-small), grava `embedding`+`embedding_model`; index HNSW cosseno. AC: re-rodar não re-embeda chunk inalterado.
- [x] T4.2 Retrieval híbrido: vetor + full-text (`tsvector`) + rerank, com filtro de metadado. AC: query cross-disciplina recupera chunk relevante não-óbvio por tag; fonte viaja junto.
- [x] T4.3 Plugar híbrido nos geradores para escopo amplo ("simulado de tudo"). AC: ancoragem (AC-G*) continua válida no modo híbrido.

## Fase 5 — Opcional
- [x] T5.1 Repetição espaçada nos flashcards (`proxima_revisao`). 
- [x] T5.2 Dashboard de custo/histórico de desempenho por disciplina.

## Fase 6 — Experiência de Aprendizado Avançada

> Tasks marcadas com ⚠ REFINAR ainda precisam de sessão de refinamento antes de implementar.

- [x] T6.0 **Reestruturação de UI: guias Resumo / Flashcards / Simulado + histórico consultável** — Página da disciplina ganha 3 guias separadas. Cada guia lista todas as gerações passadas (data, status, tokens), permite expandir para reler conteúdo completo, e tem botão "Gerar novo" com formulário de parâmetros (dificuldade; Simulado também aceita N ME + M dissertativas). Nenhuma geração anterior é perdida. AC: U1..U8 (ROC-39). Pré-requisito para T6.1, T6.2. Consolida T6.6 e T6.7.
- [x] T6.1 **Simulado híbrido: múltipla escolha + dissertativas com rubrica** — N questões ME + M dissertativas configuráveis (padrão 3+3, 1 questão = 1 ponto). Dissertativas têm rubrica explícita por tópico; IA avalia semanticamente e devolve pontuação fracionada por critério. Gabarito visível após conclusão. Histórico de erros por critério persistido (alimenta T6.4). AC: H1..H8 (ROC-31). Depende de T6.0.
- [x] T6.2 **Perfis de prova: Universitário e Vestibular** — Seleção de perfil no formulário (T6.0) preenche defaults (N ME + M dissertativas, dificuldade, estilo de enunciado). Universitário: 3+3, médio, técnico, ~36 min. Vestibular: 10+10, difícil, formal/literário, ~120 min. Usuário pode sobrescrever N e M. Retrieval distribui questões automaticamente entre headings. Cronômetro em tela + tempo realizado registrado ao concluir. AC: P1..P8 (ROC-32). Depende de T6.0.
- [x] T6.3 **Exportação de prova em PDF** — Modal com checkboxes para selecionar seções: Prova em branco / Gabarito / Minhas respostas + resultado. Disponível após geração (antes de fazer) e após conclusão. Layout simples: cabeçalho com disciplina/perfil/tempo, questões numeradas, fontes no rodapé. DomPDF (barryvdh/laravel-dompdf). AC: E1..E8 (ROC-33). Depende de T6.0, T6.1, T6.2.
- [x] T6.4 **Gráficos de evolução de conhecimento** — Dashboard por disciplina (aba "Evolução" em T6.0) + painel global no Admin Filament. 5 gráficos: score por sessão (linha), tópicos com mais erros por heading (barra), tempo realizado vs estimado, ME vs dissertativa, critérios de rubrica com mais pontos perdidos. Chart.js via Alpine.js. Sem nova tabela — dados já existem após T6.1 e T6.2. AC: G1..G9 (ROC-34). Depende de T6.0, T6.1, T6.2.
- [ ] T6.5 **Trilha de estudos e cronograma diário (estilo Duolingo)** ⚠ REFINAR — Geração de plano de estudos personalizado com metas diárias, sequência de tópicos, streak de prática, notificações de revisão. Decisões: algoritmo de priorização, integração com T5.1, gamificação. AC a definir.
- [ ] T6.6 **Histórico de gerações re-consultável** — Coberto por T6.0. Manter apenas se sobrar escopo não atendido após T6.0.
- [ ] T6.7 **Configuração de escopo de geração** — Coberto parcialmente por T6.0 (formulário de parâmetros por guia). Escopo avançado (query semântica, cross-disciplina) depende de Fase 4 e será refinado junto com T6.1+.
- [ ] T6.8 **Novos templates de resumo + entidade Tema (cross-disciplina)** — Duas partes: (1) Tema: nova entidade no banco, RetrievalService aceita `tema_id` para buscar chunks de múltiplas disciplinas (ex: "POO" cruza Redes + SO). (2) Mapa Mental: IA gera Mermaid mindmap, renderizado com Mermaid.js, ancoragem obrigatória. Templates Cornell/Feynman/SQ3R/Fichamento/ABNT ⚠ REFINAR (decisão IA vs manual pendente). AC: T1..T4 + MM1..MM6 (ROC-38). Depende de T4.3, T6.0.
- [ ] T6.x **Backlog aberto** ⚠ A LEVANTAR — Outras melhorias identificadas pelo dono durante o uso. Coletar em sessão de refinamento dedicada.
