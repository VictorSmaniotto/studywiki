<div
    x-data="{ tab: 'gerar' }"
    @geracaoCompleta.window="tab = $event.detail.tipo"
>
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
            'gerar'      => ['label' => 'Gerar', 'icon' => 'sparkles'],
            'resumo'     => ['label' => 'Resumo', 'icon' => 'document-text'],
            'flashcards' => ['label' => 'Flashcards', 'icon' => 'rectangle-stack'],
            'simulado'   => ['label' => 'Simulado', 'icon' => 'clipboard-document-check'],
        ] as $key => $item)
            <button
                @click="tab = '{{ $key }}'"
                :class="{
                    'border-b-2 text-[--color-accent] font-medium': tab === '{{ $key }}',
                    'text-zinc-500 hover:text-zinc-700': tab !== '{{ $key }}'
                }"
                style="{{ $key !== 'gerar' && (!$geracao || $geracao?->tipo !== $key) ? 'opacity: 0.4; cursor: default;' : '' }}"
                @if($key !== 'gerar' && (!$geracao || $geracao?->tipo !== $key)) disabled @endif
                class="flex items-center gap-1.5 px-3 py-2.5 text-sm transition-colors -mb-px"
                :style="tab === '{{ $key }}' ? 'border-color: var(--color-accent)' : ''"
            >
                <flux:icon name="{{ $item['icon'] }}" class="w-4 h-4" />
                {{ $item['label'] }}
                @if($geracao && $geracao->tipo === $key)
                    <flux:badge size="sm" color="zinc" class="ml-1">novo</flux:badge>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Tab: Gerar --}}
    <div x-show="tab === 'gerar'" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            {{-- Resumo --}}
            <flux:card class="p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: var(--sw-accent-tint);">
                        <flux:icon name="document-text" class="w-4 h-4" style="color: var(--color-accent)" />
                    </div>
                    <flux:heading size="sm">Resumo</flux:heading>
                </div>
                <flux:text size="sm" class="mb-4">Gera um resumo estruturado em bullets, cada ponto ancorado na fonte.</flux:text>
                <flux:button
                    wire:click="gerarResumo"
                    wire:loading.attr="disabled"
                    wire:target="gerarResumo"
                    variant="primary"
                    size="sm"
                    class="w-full"
                >
                    <span wire:loading.remove wire:target="gerarResumo">Gerar Resumo</span>
                    <span wire:loading wire:target="gerarResumo" class="flex items-center gap-2">
                        <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                        Gerando…
                    </span>
                </flux:button>
            </flux:card>

            {{-- Flashcards --}}
            <flux:card class="p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: var(--sw-accent-tint);">
                        <flux:icon name="rectangle-stack" class="w-4 h-4" style="color: var(--color-accent)" />
                    </div>
                    <flux:heading size="sm">Flashcards</flux:heading>
                </div>
                <flux:text size="sm" class="mb-4">Cria pares pergunta-resposta para revisão ativa baseados no conteúdo.</flux:text>
                <flux:button
                    wire:click="gerarFlashcards"
                    wire:loading.attr="disabled"
                    wire:target="gerarFlashcards"
                    variant="primary"
                    size="sm"
                    class="w-full"
                >
                    <span wire:loading.remove wire:target="gerarFlashcards">Gerar Flashcards</span>
                    <span wire:loading wire:target="gerarFlashcards" class="flex items-center gap-2">
                        <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                        Gerando…
                    </span>
                </flux:button>
            </flux:card>

            {{-- Simulado --}}
            <flux:card class="p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: var(--sw-accent-tint);">
                        <flux:icon name="clipboard-document-check" class="w-4 h-4" style="color: var(--color-accent)" />
                    </div>
                    <flux:heading size="sm">Simulado</flux:heading>
                </div>
                <flux:text size="sm" class="mb-4">Gera um simulado de múltipla escolha com gabarito comentado por questão.</flux:text>
                <flux:button
                    wire:click="gerarSimulado"
                    wire:loading.attr="disabled"
                    wire:target="gerarSimulado"
                    variant="primary"
                    size="sm"
                    class="w-full"
                >
                    <span wire:loading.remove wire:target="gerarSimulado">Gerar Simulado</span>
                    <span wire:loading wire:target="gerarSimulado" class="flex items-center gap-2">
                        <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                        Gerando…
                    </span>
                </flux:button>
            </flux:card>
        </div>

        {{-- Loading overlay --}}
        <div wire:loading wire:target="gerarResumo,gerarFlashcards,gerarSimulado">
            <flux:card class="p-6">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--sw-accent-tint);">
                            <flux:icon name="arrow-path" class="w-5 h-5 animate-spin" style="color: var(--color-accent)" />
                        </div>
                    </div>
                    <div>
                        <flux:heading size="sm">Consultando a vault…</flux:heading>
                        <flux:text size="sm">A IA está lendo suas notas e gerando o conteúdo. Pode levar alguns segundos.</flux:text>
                    </div>
                </div>
                <flux:progress class="mt-4" />
            </flux:card>
        </div>

        {{-- Erro --}}
        @if($erro)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4">
                {{ $erro }}
            </flux:callout>
        @endif

        {{-- Aviso de nova geração disponível --}}
        @if($geracao)
            <flux:callout variant="success" icon="check-circle" class="mt-4">
                <flux:callout.heading>Geração concluída</flux:callout.heading>
                Clique na aba <strong>{{ ucfirst($geracao->tipo) }}</strong> acima para ver o resultado.
                <flux:text size="xs" class="mt-1">{{ $geracao->custo_tokens }} tokens usados</flux:text>
            </flux:callout>
        @endif
    </div>

    {{-- Tab: Resumo --}}
    <div x-show="tab === 'resumo'" x-cloak>
        @if($geracao && $geracao->tipo === 'resumo')
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ $geracao->payload['titulo'] ?? 'Resumo' }}</flux:heading>
                <flux:badge color="zinc" size="sm">{{ $geracao->custo_tokens }} tokens</flux:badge>
            </div>
            @foreach($geracao->payload['secoes'] ?? [] as $secao)
                <div class="mb-6">
                    <flux:heading size="sm" class="mb-3 pb-2 border-b" style="border-color: var(--sw-card-border)">
                        {{ $secao['heading'] }}
                    </flux:heading>
                    <ul class="space-y-3">
                        @foreach($secao['bullets'] ?? [] as $bullet)
                            <li class="flex gap-3">
                                <div class="mt-1.5 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: var(--color-accent)"></div>
                                <div class="flex-1">
                                    <flux:text class="leading-relaxed">{{ $bullet['texto'] }}</flux:text>
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        @foreach($bullet['fontes'] ?? [] as $fonte)
                                            @php $paginaId = $fonte['pagina_id'] ?? null; @endphp
                                            @if($paginaId && isset($fontesPaginas[$paginaId]))
                                                <flux:badge size="sm" color="zinc">
                                                    {{ $fontesPaginas[$paginaId]->pagina->titulo ?? 'p.'.$paginaId }}
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
        @endif
    </div>

    {{-- Tab: Flashcards --}}
    <div x-show="tab === 'flashcards'" x-cloak>
        @if($geracao && $geracao->tipo === 'flashcards')
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Flashcards</flux:heading>
                <flux:badge color="zinc" size="sm">{{ count($geracao->payload['cards'] ?? []) }} cards · {{ $geracao->custo_tokens }} tokens</flux:badge>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($geracao->payload['cards'] ?? [] as $card)
                    <flux:card
                        class="p-4"
                        x-data="{ virado: false }"
                        @click="virado = !virado"
                        style="cursor: pointer; min-height: 120px;"
                    >
                        <div x-show="!virado">
                            <flux:badge size="sm" class="mb-2" style="background-color: var(--sw-accent-tint); color: var(--color-accent); border: none;">Pergunta</flux:badge>
                            <flux:text class="font-medium leading-snug">{{ $card['frente'] }}</flux:text>
                        </div>
                        <div x-show="virado" x-cloak>
                            <flux:badge size="sm" class="mb-2" style="background-color: #16a34a15; color: #16a34a; border: none;">Resposta</flux:badge>
                            <flux:text class="leading-snug">{{ $card['verso'] }}</flux:text>
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($card['fontes'] ?? [] as $fonte)
                                    @php $paginaId = $fonte['pagina_id'] ?? null; @endphp
                                    @if($paginaId && isset($fontesPaginas[$paginaId]))
                                        <flux:badge size="sm" color="zinc">
                                            {{ $fontesPaginas[$paginaId]->pagina->titulo ?? 'p.'.$paginaId }}
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
        @endif
    </div>

    {{-- Tab: Simulado --}}
    <div x-show="tab === 'simulado'" x-cloak>
        @if($geracao && $geracao->tipo === 'simulado')
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Simulado Gerado</flux:heading>
                <flux:badge color="zinc" size="sm">{{ count($geracao->payload['questoes'] ?? []) }} questões</flux:badge>
            </div>
            <flux:card class="p-6">
                <flux:text class="mb-4">O simulado foi gerado e está pronto para responder.</flux:text>
                <flux:button
                    href="{{ route('simulado', $geracao->id) }}"
                    variant="primary"
                    icon="arrow-right"
                    icon:trailing
                >
                    Iniciar Simulado
                </flux:button>
            </flux:card>
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
