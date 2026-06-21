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
- [x] T6.5 **Trilha de estudos e cronograma diário (estilo Duolingo)** — Plano diário determinístico: flashcards vencidos (T5.1) + tópicos prioritários por taxa de erro (T6.4). Streak de dias consecutivos persistido em `settings`. Página `/trilha` com Livewire + link no navbar. AC: TL1 (flashcards vencidos + tópicos priorizados), TL2 (streak incrementa/reseta), TL3 (persistência), TL4 (18 testes Pest verdes).
- [x] T6.6 **Histórico de gerações re-consultável** — Coberto integralmente por T6.0 (foreach em todas as gerações por tipo). Sem escopo residual.
- [x] T6.7 **Configuração de escopo de geração** — Campo "Focar em tópico" (query semântica livre, opcional) adicionado às abas Resumo, Flashcards e Simulado. Quando preenchido, `Escopo::$query` ativa `forQuery` (retrieval híbrido T4.2). 5 testes novos; 261/261 verdes.
- [x] T6.8 **Novos templates de resumo + entidade Tema (cross-disciplina)** — Duas partes: (1) Tema: nova entidade no banco, RetrievalService aceita `tema_id` para buscar chunks de múltiplas disciplinas (ex: "POO" cruza Redes + SO). (2) Mapa Mental: IA gera Mermaid mindmap, renderizado com Mermaid.js, ancoragem obrigatória. Templates Cornell/Feynman/SQ3R/Fichamento/ABNT ⚠ REFINAR (decisão IA vs manual pendente). AC: T1..T4 + MM1..MM6 (ROC-38). Depende de T4.3, T6.0.
- [ ] T6.x **Backlog aberto** ⚠ A LEVANTAR — Outras melhorias identificadas pelo dono durante o uso. Coletar em sessão de refinamento dedicada.

## Fase 7 — App Nativo (NativePHP)

> Branch: `feature/nativephp`. Arquitetura: **API-first**.
> O backend Sail (Postgres + pgvector + IA) não muda — ganha endpoints REST.
> Dois apps NativePHP são clientes finos: desktop via `nativephp/electron`, mobile via `nativephp/mobile`.
> O projeto nativo fica em `../studywiki-native/` (repo separado); o backend fica em `studywiki-app/`.
>
> ⚠ **Acesso de rede:** o app mobile não acessa `localhost`. Antes de T7.3, o backend precisa estar acessível na rede local (ex: `192.168.x.x:80`) ou via túnel (Cloudflare Tunnel / ngrok).

- [ ] T7.1 **Camada de API REST** — Adicionar ao backend existente: `routes/api.php` com Sanctum (token pessoal gerado via `php artisan sanctum:token`). Recursos: `GET /api/disciplinas`, `GET /api/disciplinas/{slug}`, `GET /api/disciplinas/{slug}/geracoes`, `POST /api/disciplinas/{slug}/gerar` (body: tipo/params), `GET /api/flashcards/vencidos`, `POST /api/flashcards/{id}/revisar` (body: lembrei bool), `GET /api/trilha`, `GET /api/temas`. Controllers em `app/Http/Controllers/Api/`. Responses JSON com paginação onde aplicável. AC: todos os endpoints retornam JSON válido com Bearer token; 401 sem token; testes Pest para cada controller (sem LLM — mock nos testes de geração).

- [ ] T7.2 **Projeto NativePHP base (desktop)** — Criar `../studywiki-native/` com `laravel new studywiki-native`. Instalar `nativephp/laravel` + `nativephp/electron`. `php artisan native:install`. Criar `ApiClient` service (Http facade, base URL + Bearer token via `.env`: `SW_API_URL`, `SW_API_TOKEN`). SQLite local para cache de gerações e estado SM-2. `config/nativephp.php`: janela 1280×800, título "StudyWiki", ícone. AC: `php artisan native:serve` abre janela nativa; `ApiClient::disciplinas()` retorna dados reais do backend; sem erros de CORS.

- [ ] T7.3 **Views desktop** — Livewire dentro do NativePHP/electron. Layout: sidebar fixa (lista de disciplinas + Trilha + Temas) + área de conteúdo. Telas: `Biblioteca` (search + cards), `DisciplinaPage` (tabs Resumo/Flashcards/Simulado/Mapa Mental — chama API para listar gerações e POST para gerar), `SimuladoPage` (igual ao web, mas state via SQLite local), `Trilha` (streak + flashcards do dia). Atalhos nativos: `CmdOrCtrl+R` refresh, `CmdOrCtrl+G` gerar. AC: fluxo completo funcionando na janela Electron (listar → gerar → responder simulado → revisar flashcard); PDF abre dialog nativo de download.

- [ ] T7.4 **Views mobile + build Android** — Adicionar `nativephp/mobile` ao `studywiki-native`. `php artisan native:install --mobile`. Layout bottom-navigation com 3 abas: **Trilha** (home: streak visual + lista de flashcards do dia), **Disciplinas** (lista → gerar → ler gerações), **Temas** (mapa mental cross-disciplina). Flashcard player: card com frente/verso, swipe ou botões "Lembrei / Esqueci" (chama `POST /api/flashcards/{id}/revisar`). Simulado simplificado: apenas ME, sem PDF. AC: `php artisan native:run android` sobe no emulador; flashcards vencidos aparecem; revisão persiste via API (SM-2 atualizado no backend).
  - [ ] T7.4.1 **Acesso de rede para o mobile** — O app mobile não acessa `localhost`. Configurar acesso ao backend via IP da rede local (`192.168.x.x`) ou túnel permanente (Cloudflare Tunnel, gratuito). `SW_API_URL` no `.env` do `studywiki-native` aponta para o endereço acessível. AC: app no emulador/dispositivo físico consegue chamar `GET /api/disciplinas` e receber resposta real.
