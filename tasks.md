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
- [x] T6.9 **Chat com a vault** — Interface conversacional ancorada nos chunks via RAG híbrido (T4.2). O usuário digita uma pergunta livre ("me explica X pelos meus apontamentos"); o sistema recupera chunks relevantes e responde em stream com citações de fonte. Diferente dos geradores: não cria `Geracao`, é efêmero. `ChatService` em `app/Services/AI/` (Prism stream), Livewire `Chat` com histórico de mensagens na sessão, link no navbar. AC: resposta sempre cita chunk de origem; sem chunk relevante → responde "não encontrei isso nos seus apontamentos"; histórico da conversa persiste na sessão (não no banco); 8 testes Pest (mock LLM).

- [x] T6.10 **Detecção de lacunas de conhecimento** — Após cada simulado concluído, analisar erros por `heading_path` e identificar os 3 tópicos com maior taxa de erro. Exibir card "Pontos fracos" na `DisciplinaPage` (aba Evolução) com links diretos para "Gerar resumo deste tópico" (preenche o campo query de T6.7). `LacunaService::detectar(Disciplina)` agrega `resposta_simulados` + `heading_path` dos chunks das questões erradas. AC: após 2+ simulados com erros, card aparece com tópicos ordenados por taxa de erro; clique em "Revisar" pré-preenche query e abre aba Resumo; 6 testes Pest.

- [x] T6.11 **Lembretes diários** — Job agendado (`schedule()->dailyAt('08:00')`) que verifica: (1) flashcards vencidos hoje, (2) streak em risco (última sessão foi ontem). Se qualquer condição for verdadeira, envia email via `Mail::to()` com resumo do dia (N flashcards pendentes, streak atual). Template Blade simples. Configurável via `settings` (ativo/inativo + horário). AC: job dispara no horário; email chega com contagem correta; desligar via settings suprime o envio; 5 testes Pest (mail fake).

- [x] T6.12 **Metas semanais** — Usuário define meta por semana em `settings`: N simulados, M flashcards revisados, K gerações. Dashboard (`/trilha` ou nova aba no admin) mostra barra de progresso semanal por categoria. `MetaService::progressoSemana()` agrega `resposta_simulados` e `flashcards` da semana atual. Sem nova tabela — tudo calculado on-the-fly. AC: meta configurada via form; barra atualiza em tempo real (Livewire polling 30s); semana encerrada reseta contadores; 6 testes Pest.

- [ ] T6.13 **Import de PDF** — Command `studywiki:import-pdf {arquivo}` extrai texto via `smalot/pdfparser`, divide em chunks heading-aware (fallback por parágrafo quando sem headings), cria `Pagina` com `origem='pdf'` e `Disciplina` inferida do frontmatter ou do nome do arquivo. Salva em `raw_pdf/` (read-only, como a vault). Chunking e embedding seguem o mesmo pipeline de T0.4 e T4.1. AC: PDF de 10 páginas vira ≥10 chunks com `pagina_id` válido; re-importar o mesmo PDF não duplica; chunks ficam disponíveis para todos os geradores; 8 testes Pest.

- [ ] T6.14 **Auto-sync da vault** — Watcher em tempo real usando `Illuminate\Support\Facades\File` + loop com `inotifywait` (Linux) ou polling de `filemtime` a cada 30s (fallback cross-platform). Command `studywiki:watch` roda em background e dispara `VaultSyncService::sync()` somente nos arquivos modificados desde o último check (delta sync). Integração opcional com Horizon/queue para não bloquear o processo. AC: editar um arquivo `.md` na vault re-sincroniza só ele em ≤ 60s sem rodar sync manual; parar o watcher não corrompe nada; 5 testes Pest.

## Fase 7 — App Nativo (NativePHP)

> Branch: `feature/nativephp`. Arquitetura: **monorepo** — NativePHP instalado diretamente no `studywiki-app`.
> O backend Sail (Postgres + pgvector + IA) não muda. As views Livewire existentes são reaproveitadas sem reescrita.
> Desktop via `nativephp/desktop` ^2 · Mobile via `nativephp/mobile` ^3. Ambos suportam Laravel 13.
> Sem projeto separado `studywiki-native` (foi descartado: adicionaria complexidade sem ganho para uso pessoal).
>
> ⚠ **Banco de dados nativo:** NativePHP empacota o PHP na janela/APK, mas não o Postgres. Para o desktop, o app conecta ao Sail rodando localmente (dev) ou usa SQLite em produção standalone. Para mobile, o app usa SQLite — adaptar `DB_CONNECTION` via `NATIVEPHP_RUNNING` env quando necessário.

- [x] T7.1 **Camada de API REST** — Adicionar ao backend existente: `routes/api.php` com Sanctum (token pessoal gerado via `php artisan sanctum:token`). Recursos: `GET /api/disciplinas`, `GET /api/disciplinas/{slug}`, `GET /api/disciplinas/{slug}/geracoes`, `POST /api/disciplinas/{slug}/gerar` (body: tipo/params), `GET /api/flashcards/vencidos`, `POST /api/flashcards/{id}/revisar` (body: lembrei bool), `GET /api/trilha`, `GET /api/temas`. Controllers em `app/Http/Controllers/Api/`. Responses JSON com paginação onde aplicável. AC: todos os endpoints retornam JSON válido com Bearer token; 401 sem token; testes Pest para cada controller (sem LLM — mock nos testes de geração).

- [ ] T7.2 **NativePHP base (desktop)** — No `studywiki-app`, instalar `nativephp/desktop` ^2 (`composer require nativephp/desktop`). Rodar `php artisan native:install`. Configurar `config/nativephp.php`: janela 1280×800, título "StudyWiki", ícone. Apagar `../studywiki-native/` criado por engano (rodar manualmente: `rm -rf ../studywiki-native`). AC: `php artisan native:run` abre janela Electron com o app carregando; as views Livewire existentes (Disciplinas, Trilha, Chat, Metas) funcionam sem erros; sem tela branca.

- [ ] T7.3 **Ajustes de experiência desktop** — Atalhos de teclado nativos via `Menu` facade do NativePHP: `CmdOrCtrl+R` (refresh), `CmdOrCtrl+G` (foco no form gerar). Título da janela dinâmico por rota. Download de PDF via dialog nativo (`NativeDialog`). AC: atalhos funcionam na janela Electron; PDF exporta via diálogo nativo do OS.

- [ ] T7.4 **Mobile + build Android** — Instalar `nativephp/mobile` ^3 no `studywiki-app` (`composer require nativephp/mobile`). `php artisan native:install`. Adaptar `config/database.php` para usar SQLite quando `NATIVEPHP_RUNNING=true`. Criar layout mobile com bottom-navigation (3 abas: **Trilha**, **Disciplinas**, **Temas**). Flashcard player: swipe ou botões "Lembrei / Esqueci". Simulado simplificado: apenas ME, sem PDF. AC: `php artisan native:run android` sobe no emulador; Trilha carrega; revisão de flashcard persiste (SM-2 via SQLite local).
  - [ ] T7.4.1 **Acesso de rede para emulador/dispositivo** — Em desenvolvimento, o emulador Android não acessa `localhost` do host. Configurar `APP_URL` com IP da rede local (`192.168.x.x`) ou túnel (Cloudflare Tunnel). AC: emulador consegue carregar o app e realizar operações que dependem do Sail (LLM, sync).

## Fase 6 — Continuação (features pessoais adicionais)

- [ ] T6.15 **Exportar flashcards para Anki** — Gerar arquivo `.apkg` (formato Anki) a partir de um deck de flashcards gerado. Usar `\Moxio\AnkiConnect` ou gerar o SQLite do pacote manualmente (formato documentado). Botão "Exportar para Anki" na aba Flashcards da `DisciplinaPage`. AC: arquivo `.apkg` importa no Anki Desktop sem erros; frente/verso e fontes preservados; 5 testes Pest.

- [ ] T6.16 **OCR em imagens da vault** — Durante o sync, detectar imagens referenciadas nos markdowns (`![[img.png]]`), extrair texto via `thiagoalessio/tesseract_ocr` (Tesseract PHP wrapper), anexar o texto extraído ao chunk da seção que contém a imagem. AC: página com diagrama/print tem texto do OCR no chunk correspondente; re-sync não re-processa imagens inalteradas; 6 testes Pest.

- [ ] T6.17 **Suporte a LaTeX / equações** — Renderizar equações LaTeX nos flashcards e simulados usando MathJax (CDN, carregado via `app.blade.php`). No gerador, instruir o LLM a preservar delimitadores `$...$` e `$$...$$` nos enunciados. AC: flashcard com equação renderiza corretamente no browser; equação no enunciado de simulado não quebra o layout; PDF exportado inclui equação como imagem (DomPDF + MathJax pré-renderizado via Puppeteer ou fallback texto).

- [ ] T6.18 **Calendário de provas** — Usuário cadastra datas de prova por disciplina (`provas` table: disciplina_id, data, descricao). `TrilhaService` pondera tópicos prioritários pela proximidade da prova (peso cresce na última semana). Widget no dashboard da Trilha mostra contagem regressiva. AC: disciplina com prova em 3 dias aparece no topo da Trilha independente de taxa de erro; 6 testes Pest.

- [ ] T6.19 **Modo grupo — compartilhar deck/simulado via link** — Gerar token único para uma `Geracao` (flashcards ou simulado). Link público `/compartilhar/{token}` exibe o conteúdo sem login, permite fazer o simulado e ver o gabarito, mas não salva progresso. Expira em 7 dias. AC: link funciona sem autenticação; expirado retorna 410; resultados não poluem o histórico do dono; 5 testes Pest.

## Fase 8 — SaaS / Produto Monetizado

> ⚠ **Virada arquitetural grande** — requer ~15–20 tasks tocando todas as camadas.
> Pré-requisito: produto pessoal validado no uso real. Implementar somente se decidir abrir para outros usuários.
> Diferencial de mercado: `GroundingValidator` determinístico — nenhum concorrente (Anki, Quizlet, Notion AI, ChatPDF) garante ancoragem. Foco em medicina, direito e engenharia.

- [ ] T8.1 **Auth multi-usuário** — Instalar Laravel Breeze (Livewire stack) + Socialite (Google). Cada usuário tem seu próprio escopo de dados. `user_id` em `disciplinas`, `paginas`, `chunks`, `geracoes`, `flashcards`, `resposta_simulados`. Migration de multi-tenancy com foreign keys. AC: dois usuários isolados não veem dados um do outro; login via Google funciona.

- [ ] T8.2 **Upload de vault** — Substituir `OBSIDIAN_VAULT_PATH` (filesystem local) por upload de arquivo ZIP de markdown. `VaultUploadService` extrai, valida (só `.md`) e armazena em S3 (`league/flysystem-aws-s3-v3`). `studywiki:sync` passa a ler do S3 para o usuário autenticado. AC: upload de 50 arquivos `.md` sincroniza corretamente; vault de outro usuário é inacessível; arquivos não-md são ignorados.

- [ ] T8.3 **Planos e billing (Stripe + Cashier)** — Planos: Gratuito (20 gerações/mês, sem simulado dissertativo), Estudante R$29/mês (200 gerações, tudo), Pro R$59/mês (ilimitado + suporte). `UsageService` rastreia gerações por usuário/mês (já temos token count). Middleware `CheckPlanLimit` barra geração quando cota esgotada. Webhooks Stripe para ativar/cancelar plano. AC: usuário gratuito bloqueado na 21ª geração; upgrade imediato libera; cancelamento no Stripe reflete em ≤ 5min.

- [ ] T8.4 **Fila robusta com Horizon** — Mover todas as chamadas LLM para jobs (`GenerateJob`, `EmbedJob`). `php artisan horizon` gerencia workers. Usuário vê status em tempo real via Livewire polling (`pendente → processando → concluído`). Prioridade de fila por plano (Pro > Estudante > Gratuito). AC: geração não bloqueia request HTTP; falha no job notifica o usuário via email; Horizon dashboard no admin Filament.

- [ ] T8.5 **Landing page e onboarding** — Página `/` pública com proposta de valor (ancoragem, sem alucinação), demo em vídeo, comparativo com concorrentes, CTA para cadastro. Onboarding guiado pós-cadastro: (1) upload da vault, (2) sync, (3) gerar primeiro resumo. AC: página carrega em < 2s; onboarding de 3 passos completa sem suporte; taxa de ativação (primeiro resumo gerado) mensurável via evento.

- [ ] T8.6 **Observabilidade e analytics de produto** — Integrar Plausible (privacy-first) para pageviews. Eventos internos: `geração_criada`, `simulado_concluído`, `flashcard_revisado`, `plano_upgraded`. Dashboard admin com MRR, churn, gerações/usuário/mês, taxa de rejeição do validador por plano. AC: eventos chegam em tempo real; nenhum dado pessoal vaza para terceiros.

- [ ] T8.7 **Suporte e documentação** — Página `/docs` com guia de formato da vault (frontmatter esperado, estrutura de headings), FAQ de ancoragem ("por que minha questão foi rejeitada?"), changelog público. Formulário de suporte que abre issue no Linear. AC: docs acessíveis sem login; changelog atualizado a cada release.
