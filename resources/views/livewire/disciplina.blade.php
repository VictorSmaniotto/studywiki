<div x-data="{ tab: 'resumo' }" @sw-mudar-aba.window="tab = $event.detail.aba">
    {{-- Breadcrumb + header --}}
    <div class="mb-6">
        <flux:link href="{{ route('biblioteca') }}" class="text-sm mb-2 inline-flex items-center gap-1">
            <flux:icon name="arrow-left" class="w-3.5 h-3.5" />
            Biblioteca
        </flux:link>
        <flux:heading size="xl" class="mt-1">{{ $disciplina->nome }}</flux:heading>
        <flux:text size="sm" class="mt-1">{{ $paginas->count() }} {{ Str::plural('página', $paginas->count()) }} disponíveis</flux:text>
    </div>

    {{-- Tab nav --}}
    <div class="flex gap-1 border-b mb-6" style="border-color: var(--sw-card-border)">
        @foreach([
            'resumo'      => ['label' => 'Resumo', 'icon' => 'document-text'],
            'flashcards'  => ['label' => 'Flashcards', 'icon' => 'rectangle-stack'],
            'simulado'    => ['label' => 'Simulado', 'icon' => 'clipboard-document-check'],
            'mapa_mental' => ['label' => 'Mapa Mental', 'icon' => 'share'],
            'evolucao'    => ['label' => 'Evolução', 'icon' => 'chart-bar'],
        ] as $key => $item)
            <button
                @click="tab = '{{ $key }}'"
                :class="{
                    'border-b-2 text-[--color-accent] font-medium': tab === '{{ $key }}',
                    'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300': tab !== '{{ $key }}'
                }"
                class="flex items-center gap-1.5 px-3 py-2.5 text-sm transition-colors -mb-px"
                :style="tab === '{{ $key }}' ? 'border-color: var(--color-accent)' : ''"
            >
                <flux:icon name="{{ $item['icon'] }}" class="w-4 h-4" />
                {{ $item['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Tab: Resumo --}}
    <div x-show="tab === 'resumo'" x-cloak>
        {{-- Gerar novo --}}
        <flux:card class="p-4 mb-6">
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading size="sm">Gerar novo resumo</flux:heading>
                        <flux:text size="xs" class="mt-0.5">Bullets ancorados nas fontes da disciplina.</flux:text>
                    </div>
                    <flux:button
                        wire:click="gerarResumo"
                        wire:loading.attr="disabled"
                        wire:target="gerarResumo"
                        variant="primary"
                        size="sm"
                        data-gerar-foco
                    >
                        <span wire:loading.remove wire:target="gerarResumo">Gerar Resumo</span>
                        <span wire:loading wire:target="gerarResumo" class="flex items-center gap-2">
                            <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                            Gerando…
                        </span>
                    </flux:button>
                </div>
                <input
                    wire:model="queryResumo"
                    type="text"
                    placeholder="Focar em tópico (opcional — ex: 'camada de transporte')"
                    class="w-full text-sm border rounded-md px-3 py-1.5 bg-white dark:bg-zinc-900 placeholder-zinc-400"
                    style="border-color: var(--sw-card-border)"
                />
            </div>
        </flux:card>

        @if($erroResumo)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                {{ $erroResumo }}
            </flux:callout>
        @endif

        {{-- Histórico --}}
        @if($geracoesResumo->isEmpty())
            <flux:text size="sm" class="text-zinc-400">Nenhum resumo gerado ainda. Clique em "Gerar Resumo" para começar.</flux:text>
        @else
            <div class="space-y-3">
                @foreach($geracoesResumo as $ger)
                    @php $expandido = in_array($ger->id, $expandidos); @endphp
                    <flux:card>
                        <div
                            wire:click="toggleExpandir({{ $ger->id }})"
                            class="flex items-center justify-between p-4 cursor-pointer select-none"
                        >
                            <div class="flex items-center gap-3">
                                <flux:icon name="document-text" class="w-5 h-5" style="color: var(--sw-muted)" />
                                <div>
                                    <flux:text size="sm" class="font-medium">{{ $ger->created_at->format('d/m/Y H:i') }}</flux:text>
                                    <flux:badge size="sm" color="{{ $ger->status === 'ok' ? 'green' : 'red' }}" class="mt-0.5">{{ $ger->status }}</flux:badge>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" color="zinc">{{ $ger->custo_tokens }} tokens</flux:badge>
                                <flux:icon name="{{ $expandido ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" style="color: var(--sw-muted)" />
                            </div>
                        </div>

                        @if($expandido && $ger->status === 'ok')
                            @php $fontes = $ger->fontes->keyBy('pagina_id'); @endphp
                            <div class="border-t px-4 pb-4 pt-3" style="border-color: var(--sw-card-border)">
                                @if($ger->payload['titulo'] ?? null)
                                    <flux:heading size="base" class="mb-3">{{ $ger->payload['titulo'] }}</flux:heading>
                                @endif
                                @foreach($ger->payload['secoes'] ?? [] as $secao)
                                    <div class="mb-4">
                                        <flux:heading size="sm" class="mb-2 pb-1 border-b" style="border-color: var(--sw-card-border)">
                                            {{ $secao['heading'] }}
                                        </flux:heading>
                                        <ul class="space-y-2">
                                            @foreach($secao['bullets'] ?? [] as $bullet)
                                                <li class="flex gap-3">
                                                    <div class="mt-1.5 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: var(--color-accent)"></div>
                                                    <div class="flex-1">
                                                        <flux:text class="leading-relaxed">{{ $bullet['texto'] }}</flux:text>
                                                        <div class="flex flex-wrap gap-1 mt-1">
                                                            @foreach($bullet['fontes'] ?? [] as $f)
                                                                @if(isset($fontes[$f['pagina_id'] ?? null]))
                                                                    <flux:badge size="sm" color="zinc">
                                                                        {{ $fontes[$f['pagina_id']]->pagina?->titulo ?? 'p.'.$f['pagina_id'] }}
                                                                    </flux:badge>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Tab: Flashcards --}}
    <div x-show="tab === 'flashcards'" x-cloak>
        {{-- Gerar novo --}}
        <flux:card class="p-4 mb-6">
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading size="sm">Gerar novos flashcards</flux:heading>
                        <flux:text size="xs" class="mt-0.5">Pares pergunta-resposta para revisão ativa.</flux:text>
                    </div>
                    <flux:button
                        wire:click="gerarFlashcards"
                        wire:loading.attr="disabled"
                        wire:target="gerarFlashcards"
                        variant="primary"
                        size="sm"
                    >
                        <span wire:loading.remove wire:target="gerarFlashcards">Gerar Flashcards</span>
                        <span wire:loading wire:target="gerarFlashcards" class="flex items-center gap-2">
                            <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                            Gerando…
                        </span>
                    </flux:button>
                </div>
                <input
                    wire:model="queryFlashcards"
                    type="text"
                    placeholder="Focar em tópico (opcional — ex: 'endereçamento IP')"
                    class="w-full text-sm border rounded-md px-3 py-1.5 bg-white dark:bg-zinc-900 placeholder-zinc-400"
                    style="border-color: var(--sw-card-border)"
                />
            </div>
        </flux:card>

        @if($erroFlashcards)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                {{ $erroFlashcards }}
            </flux:callout>
        @endif

        {{-- Histórico --}}
        @if($geracoesFlashcards->isEmpty())
            <flux:text size="sm" class="text-zinc-400">Nenhum flashcard gerado ainda.</flux:text>
        @else
            <div class="space-y-3">
                @foreach($geracoesFlashcards as $ger)
                    @php $expandido = in_array($ger->id, $expandidos); @endphp
                    <flux:card>
                        <div
                            wire:click="toggleExpandir({{ $ger->id }})"
                            class="flex items-center justify-between p-4 cursor-pointer select-none"
                        >
                            <div class="flex items-center gap-3">
                                <flux:icon name="rectangle-stack" class="w-5 h-5" style="color: var(--sw-muted)" />
                                <div>
                                    <flux:text size="sm" class="font-medium">{{ $ger->created_at->format('d/m/Y H:i') }}</flux:text>
                                    <flux:text size="xs" class="mt-0.5" style="color: var(--sw-muted)">{{ count($ger->payload['cards'] ?? []) }} cards</flux:text>
                                    <flux:badge size="sm" color="{{ $ger->status === 'ok' ? 'green' : 'red' }}" class="ml-1">{{ $ger->status }}</flux:badge>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" color="zinc">{{ $ger->custo_tokens }} tokens</flux:badge>
                                <flux:icon name="{{ $expandido ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" style="color: var(--sw-muted)" />
                            </div>
                        </div>

                        @if($expandido && $ger->status === 'ok')
                            @php $fontes = $ger->fontes->keyBy('pagina_id'); @endphp
                            <div class="border-t px-4 pb-4 pt-3" style="border-color: var(--sw-card-border)">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @foreach($ger->payload['cards'] ?? [] as $card)
                                        <flux:card
                                            class="p-4"
                                            x-data="{ virado: false }"
                                            @click.stop="virado = !virado"
                                            style="cursor: pointer; min-height: 100px;"
                                        >
                                            <div x-show="!virado">
                                                <flux:badge size="sm" class="mb-2" style="background-color: var(--sw-accent-tint); color: var(--color-accent); border: none;">Pergunta</flux:badge>
                                                <flux:text class="font-medium leading-snug">{{ $card['frente'] }}</flux:text>
                                            </div>
                                            <div x-show="virado" x-cloak>
                                                <flux:badge size="sm" class="mb-2" style="background-color: #16a34a15; color: #16a34a; border: none;">Resposta</flux:badge>
                                                <flux:text class="leading-snug">{{ $card['verso'] }}</flux:text>
                                                <div class="flex flex-wrap gap-1 mt-2">
                                                    @foreach($card['fontes'] ?? [] as $f)
                                                        @if(isset($fontes[$f['pagina_id'] ?? null]))
                                                            <flux:badge size="sm" color="zinc">
                                                                {{ $fontes[$f['pagina_id']]->pagina?->titulo ?? 'p.'.$f['pagina_id'] }}
                                                            </flux:badge>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="mt-3 flex items-center gap-1" style="color: var(--sw-muted-text)">
                                                <flux:icon name="arrow-path" class="w-3 h-3" />
                                                <span class="text-xs" x-text="virado ? 'Ver pergunta' : 'Ver resposta'"></span>
                                            </div>
                                        </flux:card>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Tab: Simulado --}}
    <div x-show="tab === 'simulado'" x-cloak>
        {{-- Gerar novo com params --}}
        <flux:card class="p-4 mb-6">
            <div class="flex flex-col gap-3">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <flux:heading size="sm">Gerar novo simulado</flux:heading>
                        <flux:text size="xs" class="mt-0.5">Questões de múltipla escolha com gabarito comentado.</flux:text>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Perfil --}}
                        <select wire:model.live="perfil" class="text-sm border rounded-md px-2 py-1.5 bg-white dark:bg-zinc-900" style="border-color: var(--sw-card-border)">
                            <option value="personalizado">Personalizado</option>
                            <option value="universitario">Universitário (~36 min)</option>
                            <option value="vestibular">Vestibular (~120 min)</option>
                        </select>
                        <select wire:model="dificuldade" class="text-sm border rounded-md px-2 py-1.5 bg-white dark:bg-zinc-900" style="border-color: var(--sw-card-border)">
                            <option value="facil">Fácil</option>
                            <option value="medio">Médio</option>
                            <option value="dificil">Difícil</option>
                        </select>
                        <div class="flex items-center gap-1">
                            <input
                                wire:model="nQuestoes"
                                type="number"
                                min="0"
                                max="20"
                                class="w-14 text-sm border rounded-md px-2 py-1.5 bg-white dark:bg-zinc-900"
                                style="border-color: var(--sw-card-border)"
                                title="Questões ME"
                            />
                            <flux:text size="xs" style="color: var(--sw-muted-text)">ME</flux:text>
                            <span class="text-zinc-400">+</span>
                            <input
                                wire:model="nDissertativas"
                                type="number"
                                min="0"
                                max="10"
                                class="w-14 text-sm border rounded-md px-2 py-1.5 bg-white dark:bg-zinc-900"
                                style="border-color: var(--sw-card-border)"
                                title="Questões dissertativas"
                            />
                            <flux:text size="xs" style="color: var(--sw-muted-text)">Dis</flux:text>
                        </div>
                        <flux:button
                            wire:click="gerarSimulado"
                            wire:loading.attr="disabled"
                            wire:target="gerarSimulado"
                            variant="primary"
                            size="sm"
                        >
                            <span wire:loading.remove wire:target="gerarSimulado">Gerar Simulado</span>
                            <span wire:loading wire:target="gerarSimulado" class="flex items-center gap-2">
                                <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                                Gerando…
                            </span>
                        </flux:button>
                    </div>
                </div>
                <input
                    wire:model="querySimulado"
                    type="text"
                    placeholder="Focar em tópico (opcional — ex: 'camada de aplicação HTTP')"
                    class="w-full text-sm border rounded-md px-3 py-1.5 bg-white dark:bg-zinc-900 placeholder-zinc-400"
                    style="border-color: var(--sw-card-border)"
                />
            </div>
        </flux:card>

        @if($erroSimulado)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                {{ $erroSimulado }}
            </flux:callout>
        @endif

        {{-- Histórico --}}
        @if($geracoesSimulado->isEmpty())
            <flux:text size="sm" class="text-zinc-400">Nenhum simulado gerado ainda.</flux:text>
        @else
            <div class="space-y-3">
                @foreach($geracoesSimulado as $ger)
                    @php $expandido = in_array($ger->id, $expandidos); @endphp
                    <flux:card>
                        <div
                            wire:click="toggleExpandir({{ $ger->id }})"
                            class="flex items-center justify-between p-4 cursor-pointer select-none"
                        >
                            <div class="flex items-center gap-3">
                                <flux:icon name="clipboard-document-check" class="w-5 h-5" style="color: var(--sw-muted)" />
                                <div>
                                    <flux:text size="sm" class="font-medium">{{ $ger->created_at->format('d/m/Y H:i') }}</flux:text>
                                    <flux:text size="xs" class="mt-0.5" style="color: var(--sw-muted)">
                                        {{ count($ger->payload['questoes'] ?? []) }} questões
                                        @if($ger->escopo['dificuldade'] ?? null)
                                            · {{ $ger->escopo['dificuldade'] }}
                                        @endif
                                    </flux:text>
                                    <flux:badge size="sm" color="{{ $ger->status === 'ok' ? 'green' : 'red' }}" class="ml-1">{{ $ger->status }}</flux:badge>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" color="zinc">{{ $ger->custo_tokens }} tokens</flux:badge>
                                <flux:icon name="{{ $expandido ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" style="color: var(--sw-muted)" />
                            </div>
                        </div>

                        @if($expandido && $ger->status === 'ok')
                            <div class="border-t p-4 flex items-center gap-3" style="border-color: var(--sw-card-border)">
                                <flux:button
                                    href="{{ route('simulado', $ger->id) }}"
                                    variant="primary"
                                    icon="arrow-right"
                                    icon:trailing
                                    size="sm"
                                >
                                    Iniciar Simulado
                                </flux:button>
                                {{-- Exportar PDF da disciplina (E5) --}}
                                @include('livewire.partials.simulado-pdf-modal', [
                                    'geracaoId'    => $ger->id,
                                    'temRespostas' => ($ger->respostas_count ?? 0) > 0,
                                ])
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Tab: Evolução --}}
    <div x-show="tab === 'evolucao'" x-cloak>
        @php
            $temDados   = ! empty($scoresPorSessao);
            $temErros   = ! empty($errosPorTopico);
            $temTempo   = ! empty($tempoVsEstimado);
            $temDist    = ($distribuicaoQuestoes['me'] + $distribuicaoQuestoes['dissertativas']) > 0;
            $temRubrica = ! empty($criteriosMaisPerdidos);
        @endphp

        {{-- Dados PHP → JS via <script> (evita @json() dentro de atributos Alpine) --}}
        <script>
            window._swEvo = {
                scores:  @json($scoresPorSessao),
                erros:   @json($errosPorTopico),
                tempo:   @json($tempoVsEstimado),
                dist:    { me: {{ $distribuicaoQuestoes['me'] }}, dis: {{ $distribuicaoQuestoes['dissertativas'] }} },
                rubrica: @json($criteriosMaisPerdidos),
            };
        </script>

        {{-- Pontos fracos (L1..L3) --}}
        @if(! empty($lacunas))
            <flux:card class="p-4 mb-6" style="border-left: 4px solid #ef4444">
                <flux:heading size="sm" class="mb-3 flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4" style="color:#ef4444" />
                    Pontos fracos
                </flux:heading>
                <div class="space-y-2">
                    @foreach($lacunas as $lacuna)
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <span class="text-sm font-medium">{{ $lacuna['heading'] }}</span>
                                <span class="text-xs ml-2" style="color: var(--sw-muted)">
                                    {{ $lacuna['erros'] }}/{{ $lacuna['total'] }} erros ({{ $lacuna['taxa_erro'] }}%)
                                </span>
                            </div>
                            <flux:button
                                wire:click="revisarTopico(@js($lacuna['heading']))"
                                size="xs"
                                variant="ghost"
                            >
                                Revisar
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        @if(! $temDados && ! $temErros && ! $temDist && ! $temRubrica)
            {{-- G9: estado vazio --}}
            <flux:card class="p-8 text-center">
                <flux:icon name="chart-bar" class="w-10 h-10 mx-auto mb-3" style="color: var(--sw-muted)" />
                <flux:heading size="sm" class="mb-1">Sem dados de evolução ainda</flux:heading>
                <flux:text size="sm" class="text-zinc-400">
                    Complete pelo menos um simulado para ver seus gráficos de evolução.
                </flux:text>
            </flux:card>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- G1: Score por sessão --}}
                <flux:card class="p-4">
                    <flux:heading size="sm" class="mb-3">Score por sessão</flux:heading>
                    @if($temDados)
                        <div x-data="{ chart: null }"
                             x-effect="if (tab === 'evolucao' && !chart) $nextTick(() => {
                                var d = window._swEvo.scores;
                                chart = new Chart($refs.scoreChart, {
                                    type: 'line',
                                    data: {
                                        labels: d.map(function(r){ return r.data }),
                                        datasets: [
                                            { label: 'ME (%)', data: d.map(function(r){ return r.score_me }),
                                              borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)',
                                              tension: 0.3, fill: true, spanGaps: true },
                                            { label: 'Dissertativa (%)', data: d.map(function(r){ return r.score_dis }),
                                              borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)',
                                              tension: 0.3, fill: true, spanGaps: true }
                                        ]
                                    },
                                    options: { responsive: true, maintainAspectRatio: false,
                                        plugins: { legend: { position: 'bottom' } },
                                        scales: { y: { min: 0, max: 100, ticks: { callback: function(v){ return v + '%' } } } }
                                    }
                                })
                             })">
                            <div style="position:relative;height:220px">
                                <canvas x-ref="scoreChart"></canvas>
                            </div>
                        </div>
                    @else
                        <flux:text size="sm" class="text-zinc-400">Nenhum simulado respondido ainda.</flux:text>
                    @endif
                </flux:card>

                {{-- G2: Erros por tópico --}}
                <flux:card class="p-4">
                    <flux:heading size="sm" class="mb-3">Tópicos com mais erros</flux:heading>
                    @if($temErros)
                        <div x-data="{ chart: null }"
                             x-effect="if (tab === 'evolucao' && !chart) $nextTick(() => {
                                var d = window._swEvo.erros;
                                chart = new Chart($refs.errosChart, {
                                    type: 'bar',
                                    data: {
                                        labels: d.map(function(r){ return r.heading }),
                                        datasets: [{ label: 'Erros', data: d.map(function(r){ return r.erros }),
                                            backgroundColor: 'rgba(239,68,68,0.7)', borderColor: '#ef4444', borderWidth: 1 }]
                                    },
                                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                                        plugins: { legend: { display: false } },
                                        scales: { x: { ticks: { stepSize: 1 } } }
                                    }
                                })
                             })">
                            <div style="position:relative;height:220px">
                                <canvas x-ref="errosChart"></canvas>
                            </div>
                        </div>
                    @else
                        <flux:text size="sm" class="text-zinc-400">Nenhum erro registrado ainda.</flux:text>
                    @endif
                </flux:card>

                {{-- G3: Tempo realizado vs estimado --}}
                <flux:card class="p-4">
                    <flux:heading size="sm" class="mb-3">Tempo realizado vs estimado (min)</flux:heading>
                    @if($temTempo)
                        <div x-data="{ chart: null }"
                             x-effect="if (tab === 'evolucao' && !chart) $nextTick(() => {
                                var d = window._swEvo.tempo;
                                chart = new Chart($refs.tempoChart, {
                                    type: 'bar',
                                    data: {
                                        labels: d.map(function(r){ return r.data }),
                                        datasets: [
                                            { label: 'Realizado', data: d.map(function(r){ return r.realizado_min }),
                                              backgroundColor: 'rgba(99,102,241,0.7)' },
                                            { label: 'Estimado', data: d.map(function(r){ return r.estimado_min }),
                                              backgroundColor: 'rgba(148,163,184,0.5)' }
                                        ]
                                    },
                                    options: { responsive: true, maintainAspectRatio: false,
                                        plugins: { legend: { position: 'bottom' } },
                                        scales: { y: { beginAtZero: true } }
                                    }
                                })
                             })">
                            <div style="position:relative;height:220px">
                                <canvas x-ref="tempoChart"></canvas>
                            </div>
                        </div>
                    @else
                        <flux:text size="sm" class="text-zinc-400">Nenhum simulado com tempo registrado.</flux:text>
                    @endif
                </flux:card>

                {{-- G4: ME vs Dissertativa --}}
                <flux:card class="p-4">
                    <flux:heading size="sm" class="mb-3">Distribuição de questões</flux:heading>
                    @if($temDist)
                        <div x-data="{ chart: null }"
                             x-effect="if (tab === 'evolucao' && !chart) $nextTick(() => {
                                var d = window._swEvo.dist;
                                chart = new Chart($refs.distChart, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Múltipla escolha', 'Dissertativa'],
                                        datasets: [{ data: [d.me, d.dis],
                                            backgroundColor: ['rgba(99,102,241,0.8)', 'rgba(245,158,11,0.8)'],
                                            borderWidth: 2 }]
                                    },
                                    options: { responsive: true, maintainAspectRatio: false,
                                        plugins: { legend: { position: 'bottom' } } }
                                })
                             })">
                            <div style="position:relative;height:220px">
                                <canvas x-ref="distChart"></canvas>
                            </div>
                        </div>
                    @else
                        <flux:text size="sm" class="text-zinc-400">Nenhum simulado gerado ainda.</flux:text>
                    @endif
                </flux:card>

                {{-- G5: Critérios de rubrica com mais pontos perdidos --}}
                @if($temRubrica)
                    <flux:card class="p-4 lg:col-span-2">
                        <flux:heading size="sm" class="mb-3">Critérios de rubrica com mais pontos perdidos</flux:heading>
                        <div x-data="{ chart: null }"
                             x-effect="if (tab === 'evolucao' && !chart) $nextTick(() => {
                                var d = window._swEvo.rubrica;
                                chart = new Chart($refs.rubricaChart, {
                                    type: 'bar',
                                    data: {
                                        labels: d.map(function(r){ return r.criterio }),
                                        datasets: [{ label: 'Média pontos perdidos (%)',
                                            data: d.map(function(r){ return r.media_perdido }),
                                            backgroundColor: 'rgba(239,68,68,0.7)', borderColor: '#ef4444', borderWidth: 1 }]
                                    },
                                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                                        plugins: { legend: { display: false } },
                                        scales: { x: { min: 0, max: 100, ticks: { callback: function(v){ return v + '%' } } } }
                                    }
                                })
                             })">
                            <div style="position:relative;height:200px">
                                <canvas x-ref="rubricaChart"></canvas>
                            </div>
                        </div>
                    </flux:card>
                @endif

            </div>
        @endif
    </div>

    {{-- Tab: Mapa Mental --}}
    <div x-show="tab === 'mapa_mental'" x-cloak>
        <flux:card class="p-4 mb-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="sm">Gerar novo mapa mental</flux:heading>
                    <flux:text size="xs" class="mt-0.5">Mindmap Mermaid ancorado nas fontes da disciplina.</flux:text>
                </div>
                <flux:button
                    wire:click="gerarMapaMental"
                    wire:loading.attr="disabled"
                    wire:target="gerarMapaMental"
                    variant="primary"
                    size="sm"
                >
                    <span wire:loading.remove wire:target="gerarMapaMental">Gerar Mapa Mental</span>
                    <span wire:loading wire:target="gerarMapaMental" class="flex items-center gap-2">
                        <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                        Gerando…
                    </span>
                </flux:button>
            </div>
        </flux:card>

        @if($erroMapaMental)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                {{ $erroMapaMental }}
            </flux:callout>
        @endif

        @if($geracoesMapaMental->isEmpty())
            <flux:text size="sm" class="text-zinc-400">Nenhum mapa mental gerado ainda.</flux:text>
        @else
            <div class="space-y-3">
                @foreach($geracoesMapaMental as $ger)
                    @php $expandido = in_array($ger->id, $expandidos); @endphp
                    <flux:card>
                        <div
                            wire:click="toggleExpandir({{ $ger->id }})"
                            class="flex items-center justify-between p-4 cursor-pointer select-none"
                        >
                            <div class="flex items-center gap-3">
                                <flux:icon name="share" class="w-5 h-5" style="color: var(--sw-muted)" />
                                <div>
                                    <flux:text size="sm" class="font-medium">
                                        {{ $ger->payload['titulo'] ?? 'Mapa mental' }}
                                    </flux:text>
                                    <flux:text size="xs" style="color: var(--sw-muted)">
                                        {{ $ger->created_at->format('d/m/Y H:i') }}
                                        · {{ $ger->custo_tokens }} tokens
                                        · <flux:badge size="xs" color="{{ $ger->status === 'ok' ? 'green' : 'red' }}">{{ $ger->status }}</flux:badge>
                                    </flux:text>
                                </div>
                            </div>
                            <flux:icon name="{{ $expandido ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" style="color: var(--sw-muted)" />
                        </div>

                        @if($expandido && $ger->status === 'ok')
                            <div class="border-t px-4 pb-4 pt-3" style="border-color: var(--sw-card-border)">
                                @php
                                    $mermaidCode = \App\Services\AI\MapaMentalGenerator::gerarMermaidCode(
                                        $ger->payload['titulo'] ?? 'Tema',
                                        $ger->payload['nos'] ?? []
                                    );
                                @endphp
                                <div
                                    x-data="{ rendered: false }"
                                    x-init="$nextTick(() => {
                                        if (!rendered && window.mermaid) {
                                            window.mermaid.run({ nodes: [$el.querySelector('.mermaid')] });
                                            rendered = true;
                                        }
                                    })"
                                    class="overflow-x-auto"
                                >
                                    <div class="mermaid text-sm">{{ $mermaidCode }}</div>
                                </div>

                                @if($ger->fontes->isNotEmpty())
                                    <div class="mt-3 pt-3 border-t" style="border-color: var(--sw-card-border)">
                                        <flux:text size="xs" class="font-medium mb-1" style="color: var(--sw-muted)">Fontes:</flux:text>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($ger->fontes as $fonte)
                                                @if($fonte->pagina)
                                                    <flux:badge size="xs" color="zinc">{{ $fonte->pagina->titulo }}</flux:badge>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Lista de páginas --}}
    <flux:separator class="my-8" />
    <flux:heading size="base" class="mb-4 flex items-center gap-2">
        <flux:icon name="document" class="w-4 h-4" style="color: var(--sw-muted)" />
        Páginas da disciplina
    </flux:heading>

    @if($paginas->isEmpty())
        <flux:text size="sm">Nenhuma página sincronizada.</flux:text>
    @else
        <div class="space-y-1.5">
            @foreach($paginas as $pagina)
                <div class="flex items-center justify-between px-4 py-2.5 rounded-lg bg-white dark:bg-zinc-900 border" style="border-color: var(--sw-card-border)">
                    <flux:text size="sm" class="font-medium">{{ $pagina->titulo }}</flux:text>
                    @if($pagina->tags->isNotEmpty())
                        <div class="flex gap-1">
                            @foreach($pagina->tags as $tag)
                                <flux:badge size="sm" color="zinc">{{ $tag->nome }}</flux:badge>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
