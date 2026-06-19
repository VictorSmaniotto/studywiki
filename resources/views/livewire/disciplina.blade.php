<div x-data="{ tab: 'resumo' }">
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
            'resumo'     => ['label' => 'Resumo', 'icon' => 'document-text'],
            'flashcards' => ['label' => 'Flashcards', 'icon' => 'rectangle-stack'],
            'simulado'   => ['label' => 'Simulado', 'icon' => 'clipboard-document-check'],
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
                >
                    <span wire:loading.remove wire:target="gerarResumo">Gerar Resumo</span>
                    <span wire:loading wire:target="gerarResumo" class="flex items-center gap-2">
                        <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                        Gerando…
                    </span>
                </flux:button>
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
