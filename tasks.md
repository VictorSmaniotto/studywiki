---
titulo: Backlog executável
tipo: tasks
criado: 2026-06-16
---

# Tasks

> Execute em ordem, uma por vez. Cada feature segue **MALT** (skill `malt-laravel`): Modelagem → Ação → Lógica → Teste.
> Após cada task: `php artisan test` verde + atualizar `progress.md`. AC = critério de aceite.

## Fase 0 — Scaffold
- [ ] T0.1 Criar projeto Laravel 12, configurar Postgres, habilitar extensão `pgvector`. AC: `php artisan migrate` roda; `SELECT '[1,2,3]'::vector;` funciona.
- [ ] T0.2 (M) Migrations + Models do `specs/01`: Disciplina, Pagina, Tag, pivot, Chunk (coluna `embedding vector(1536) null`), Geracao, GeracaoFonte, com `$fillable`/`$casts`/relacionamentos. AC: factories criam o grafo; teste com `Pagina::with('chunks','tags')` passa.
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
- [ ] T5.1 Repetição espaçada nos flashcards (`proxima_revisao`). 
- [ ] T5.2 Dashboard de custo/histórico de desempenho por disciplina.

## Fase 6 — Experiência de Aprendizado Avançada ⚠ REFINAMENTO PENDENTE

> Todas as tasks desta fase estão em rascunho. Antes de implementar cada uma, abrir sessão de refinamento para: detalhar AC, definir modelo de dados, decidir stack de UI e integração com as fases anteriores.

- [ ] T6.1 **Novo tipo de Simulado híbrido** ⚠ REFINAR — Prova com 3 questões de múltipla escolha + 3 questões dissertativas/escrita. Requer: novo schema de geração, novo validador de ancoragem para respostas abertas, critérios de avaliação (rubrica). AC e modelo de dados a definir.
- [ ] T6.2 **Níveis de prova configuráveis** ⚠ REFINAR — Além do `--dif`, definir "formato de prova" (vestibular, concurso, universitário, etc.) com perfis de dificuldade, distribuição de tipos de questão e tempo estimado. AC a definir.
- [ ] T6.3 **Exportação de prova em PDF** ⚠ REFINAR — Gerar PDF formatado da prova (sem e com gabarito), pronto para impressão. Decisões: biblioteca (wkhtmltopdf, Puppeteer, TCPDF?), template, fontes rastreáveis no rodapé. AC a definir.
- [ ] T6.4 **Gráficos de evolução de conhecimento** ⚠ REFINAR — Dashboard com histórico de desempenho por disciplina/tema ao longo do tempo: acertos, taxa de ancoragem, tópicos com mais erros, progresso entre sessões. Decisões: biblioteca de charts (Chart.js? ApexCharts?), modelo de dados de desempenho. AC a definir.
- [ ] T6.5 **Trilha de estudos e cronograma diário (estilo Duolingo)** ⚠ REFINAR — Geração de plano de estudos personalizado com metas diárias, sequência de tópicos, streak de prática, notificações de revisão. Decisões: algoritmo de priorização (baseado em erros? em data da última revisão?), integração com repetição espaçada (T5.1), gamificação (XP, streak). AC a definir.
- [ ] T6.6 **Histórico de gerações re-consultável** ⚠ REFINAR — Hoje a DisciplinaPage mostra só a geração mais recente por tipo; gerações anteriores se perdem na UI. Precisamos de: lista de gerações passadas por disciplina (tipo, data, tokens, status), expansão inline para reler conteúdo (resumo/flashcards/simulado), e indicação de "foi à prova com esse simulado". **Impacto Fase 4:** o histórico precisa guardar e exibir o escopo usado na geração (disciplina, páginas, query semântica) para que o usuário possa reproduzir ou comparar resultados. Modelo de dados: adicionar `escopo_input json` no `Geracao`. AC e stack de UI a definir.
- [ ] T6.7 **Configuração de escopo de geração** ⚠ REFINAR — Hoje o escopo é implícito (toda a disciplina da página em que o usuário está). O usuário quer controlar isso explicitamente. Mínimo (pré-Fase 4): seleção de disciplina + opção de filtrar por páginas específicas dentro dela. Com Fase 4: adicionar (a) query semântica livre ("mecanismos de herança e polimorfismo") e (b) modo cross-disciplina / "toda a vault" (T4.3). O formulário de escopo deve ser desenhado já com esses quatro modos em mente para não ter que refazer a UI. Decisões a refinar: onde entra o formulário (DisciplinaPage? modal antes de gerar? página dedicada?), como os quatro modos se alternam, e como o escopo escolhido fica salvo no `Geracao` para reprodutibilidade. AC a definir.
- [ ] T6.x **Backlog aberto** ⚠ A LEVANTAR — Outras melhorias identificadas pelo dono durante o uso. Coletar em sessão de refinamento dedicada antes da Fase 6.
