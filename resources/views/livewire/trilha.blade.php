<div>
    {{-- Header --}}
    <div class="py-10">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                @if($modoRevisao && !$sessaoConcluida)
                    <flux:heading size="xl" class="text-3xl! font-bold! tracking-tight!">
                        Revisão de Flashcards
                    </flux:heading>
                    <flux:subheading class="mt-1">
                        Card {{ $indiceAtual + 1 }} de {{ $totalCards }}
                    </flux:subheading>
                @elseif($sessaoConcluida)
                    <flux:heading size="xl" class="text-3xl! font-bold! tracking-tight!">
                        Sessão concluída
                    </flux:heading>
                    <flux:subheading class="mt-1">
                        Veja seu desempenho e o streak atualizado.
                    </flux:subheading>
                @else
                    <flux:heading size="xl" class="text-3xl! font-bold! tracking-tight!">
                        Trilha de Estudos
                    </flux:heading>
                    <flux:subheading class="mt-1">
                        Seu plano para hoje, baseado em revisões vencidas e tópicos com mais erros.
                    </flux:subheading>
                @endif
            </div>

            {{-- Streak --}}
            @if($streak > 0)
                <div class="flex items-center gap-3 px-5 py-3 rounded-xl" style="background-color: var(--sw-accent-tint); border: 1px solid color-mix(in oklab, var(--color-accent), transparent 70%)">
                    <span class="text-2xl">🔥</span>
                    <div>
                        <div class="text-2xl font-bold leading-none" style="color: var(--color-accent)">
                            {{ $streak }}
                        </div>
                        <div class="text-xs mt-0.5" style="color: var(--sw-muted-text)">
                            {{ $streak === 1 ? 'dia seguido' : 'dias seguidos' }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <flux:separator class="mb-8" />

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- ESTADO 3: Sessão concluída                     --}}
    {{-- ═══════════════════════════════════════════════ --}}
    @if($sessaoConcluida)
        <div class="max-w-sm mx-auto text-center py-6">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6"
                 style="background-color: var(--sw-accent-tint)">
                <flux:icon name="check-circle" class="w-9 h-9" style="color: var(--color-accent)" />
            </div>

            <flux:heading size="lg" class="mb-1">Muito bem!</flux:heading>
            <flux:text class="text-sm mb-8" style="color: var(--sw-muted-text)">
                Você revisou {{ $acertos + $erros }} {{ ($acertos + $erros) === 1 ? 'flashcard' : 'flashcards' }} hoje.
            </flux:text>

            <div class="flex justify-center gap-8 mb-8">
                <div class="text-center">
                    <div class="text-3xl font-bold mb-1" style="color: var(--color-accent)">{{ $acertos }}</div>
                    <div class="text-xs" style="color: var(--sw-muted-text)">acertos</div>
                </div>
                <div class="w-px" style="background-color: var(--sw-card-border)"></div>
                <div class="text-center">
                    <div class="text-3xl font-bold mb-1 text-red-500">{{ $erros }}</div>
                    <div class="text-xs" style="color: var(--sw-muted-text)">erros</div>
                </div>
            </div>

            <flux:button wire:click="encerrarRevisao" variant="primary" class="w-full">
                Voltar para a trilha
            </flux:button>
        </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- ESTADO 2: Modo revisão                         --}}
    {{-- ═══════════════════════════════════════════════ --}}
    @elseif($modoRevisao)
        <div class="max-w-xl mx-auto">

            {{-- Barra de progresso --}}
            <div class="mb-6">
                <div class="flex justify-between text-xs mb-1.5" style="color: var(--sw-muted-text)">
                    <span>Progresso</span>
                    <span>{{ $indiceAtual }} / {{ $totalCards }}</span>
                </div>
                <div class="w-full rounded-full h-1.5" style="background-color: var(--sw-card-border)">
                    <div class="h-1.5 rounded-full transition-all duration-300"
                         style="width: {{ $totalCards > 0 ? ($indiceAtual / $totalCards) * 100 : 0 }}%; background-color: var(--color-accent)">
                    </div>
                </div>
            </div>

            @if($flashcardAtual)
                {{-- Card da pergunta --}}
                <flux:card class="p-6 mb-4">
                    <div class="text-xs font-medium uppercase tracking-wide mb-3" style="color: var(--sw-muted-text)">
                        Pergunta
                    </div>
                    <p class="text-base font-medium leading-relaxed">{{ $flashcardAtual->frente }}</p>
                </flux:card>

                @if(!$respostaRevelada)
                    {{-- Campo de resposta --}}
                    <flux:card class="p-6 mb-4">
                        <div class="text-xs font-medium uppercase tracking-wide mb-3" style="color: var(--sw-muted-text)">
                            Sua resposta
                        </div>
                        <flux:textarea
                            wire:model="respostaUsuario"
                            placeholder="Digite sua resposta antes de revelar..."
                            rows="4"
                            class="w-full"
                        />
                    </flux:card>

                    <div class="flex items-center justify-between">
                        <flux:button wire:click="encerrarRevisao" variant="ghost" size="sm" icon="arrow-left">
                            Encerrar revisão
                        </flux:button>
                        <flux:button wire:click="revelarResposta" variant="primary">
                            Revelar resposta
                        </flux:button>
                    </div>

                @else
                    {{-- Comparação: resposta do usuário vs. correta --}}
                    <div class="grid grid-cols-1 gap-3 mb-4">
                        <flux:card class="p-4">
                            <div class="text-xs font-medium uppercase tracking-wide mb-2" style="color: var(--sw-muted-text)">
                                Sua resposta
                            </div>
                            <p class="text-sm leading-relaxed">
                                {{ $respostaUsuario !== '' ? $respostaUsuario : '—' }}
                            </p>
                        </flux:card>

                        <flux:card class="p-4" style="border-color: color-mix(in oklab, var(--color-accent), transparent 60%)">
                            <div class="text-xs font-medium uppercase tracking-wide mb-2" style="color: var(--color-accent)">
                                Resposta correta
                            </div>
                            <p class="text-sm leading-relaxed">{{ $flashcardAtual->verso }}</p>
                        </flux:card>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            wire:click="avaliar(false)"
                            variant="ghost"
                            class="flex-1"
                            icon="x-circle"
                        >
                            Errei
                        </flux:button>
                        <flux:button
                            wire:click="avaliar(true)"
                            variant="primary"
                            class="flex-1"
                            icon="check-circle"
                        >
                            Acertei
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- ESTADO 1: Sumário                              --}}
    {{-- ═══════════════════════════════════════════════ --}}
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Flashcards vencidos --}}
            <div>
                <flux:heading size="lg" class="mb-4">Revisões de hoje</flux:heading>

                @if($flashcardsVencidos->isEmpty())
                    <flux:card class="py-10 text-center">
                        <flux:icon name="check-circle" class="w-8 h-8 mx-auto mb-3" style="color: var(--color-accent)" />
                        <flux:text class="text-sm font-medium">Tudo em dia!</flux:text>
                        <flux:text class="text-sm mt-1" style="color: var(--sw-muted-text)">
                            Nenhum flashcard vencido para revisar hoje.
                        </flux:text>
                    </flux:card>
                @else
                    <flux:card class="p-6">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                 style="background-color: var(--sw-accent-tint)">
                                <flux:icon name="rectangle-stack" class="w-6 h-6" style="color: var(--color-accent)" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold leading-none">{{ $flashcardsVencidos->count() }}</div>
                                <div class="text-sm mt-0.5" style="color: var(--sw-muted-text)">
                                    {{ $flashcardsVencidos->count() === 1 ? 'flashcard para revisar' : 'flashcards para revisar' }}
                                </div>
                            </div>
                        </div>

                        <flux:button wire:click="iniciarRevisao" variant="primary" class="w-full">
                            Iniciar revisão
                        </flux:button>
                    </flux:card>
                @endif
            </div>

            {{-- Tópicos prioritários --}}
            <div>
                <flux:heading size="lg" class="mb-4">Tópicos para reforçar</flux:heading>

                @if(empty($topicosPrioritarios))
                    <flux:card class="py-10 text-center">
                        <flux:icon name="chart-bar" class="w-8 h-8 mx-auto mb-3 text-zinc-300" />
                        <flux:text class="text-sm font-medium">Sem dados ainda</flux:text>
                        <flux:text class="text-sm mt-1" style="color: var(--sw-muted-text)">
                            Faça simulados para ver os tópicos com mais erros.
                        </flux:text>
                    </flux:card>
                @else
                    <div class="space-y-2">
                        @foreach($topicosPrioritarios as $index => $topico)
                            <flux:card class="p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold"
                                             style="background-color: var(--sw-accent-tint); color: var(--color-accent)">
                                            {{ $index + 1 }}
                                        </div>
                                        <span class="text-sm font-medium truncate">{{ $topico['heading'] }}</span>
                                    </div>
                                    <flux:badge color="red" class="flex-shrink-0">
                                        {{ $topico['erros'] }} {{ $topico['erros'] === 1 ? 'erro' : 'erros' }}
                                    </flux:badge>
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
