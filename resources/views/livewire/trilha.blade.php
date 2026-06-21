<div>
    {{-- Header --}}
    <div class="py-10">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <flux:heading size="xl" class="text-3xl! font-bold! tracking-tight!">
                    Trilha de Estudos
                </flux:heading>
                <flux:subheading class="mt-1">
                    Seu plano para hoje, baseado em revisões vencidas e tópicos com mais erros.
                </flux:subheading>
            </div>

            {{-- Streak --}}
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
        </div>
    </div>

    <flux:separator class="mb-8" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Flashcards vencidos --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">
                    Revisões de hoje
                    @if($flashcardsVencidos->isNotEmpty())
                        <flux:badge color="amber" class="ml-2">{{ $flashcardsVencidos->count() }}</flux:badge>
                    @endif
                </flux:heading>
            </div>

            @if($flashcardsVencidos->isEmpty())
                <flux:card class="py-10 text-center">
                    <flux:icon name="check-circle" class="w-8 h-8 mx-auto mb-3" style="color: var(--color-accent)" />
                    <flux:text class="text-sm font-medium">Tudo em dia!</flux:text>
                    <flux:text class="text-sm mt-1" style="color: var(--sw-muted-text)">
                        Nenhum flashcard vencido para revisar hoje.
                    </flux:text>
                </flux:card>
            @else
                <div class="space-y-2">
                    @foreach($flashcardsVencidos as $card)
                        <flux:card class="p-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: var(--sw-accent-tint)">
                                    <flux:icon name="rectangle-stack" class="w-3.5 h-3.5" style="color: var(--color-accent)" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium leading-snug">{{ $card->frente }}</div>
                                    @if($card->proxima_revisao->lt(\Illuminate\Support\Carbon::today()))
                                        <div class="text-xs mt-1" style="color: var(--sw-muted-text)">
                                            Atrasado desde {{ $card->proxima_revisao->format('d/m') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
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
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold" style="background-color: var(--sw-accent-tint); color: var(--color-accent)">
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

    {{-- CTA: registrar sessão --}}
    <div class="mt-10">
        <flux:card class="p-6 text-center">
            @if($sessaoRegistrada)
                <div class="flex items-center justify-center gap-2 mb-2">
                    <flux:icon name="check-circle" class="w-6 h-6" style="color: var(--color-accent)" />
                    <flux:heading size="sm">Sessão registrada!</flux:heading>
                </div>
                <flux:text class="text-sm" style="color: var(--sw-muted-text)">
                    Streak atualizado para {{ $streak }} {{ $streak === 1 ? 'dia' : 'dias' }}. Continue assim!
                </flux:text>
            @else
                <flux:heading size="sm" class="mb-2">Concluiu sua sessão de hoje?</flux:heading>
                <flux:text class="text-sm mb-4" style="color: var(--sw-muted-text)">
                    Marque a sessão para manter seu streak de estudos.
                </flux:text>
                <flux:button wire:click="registrarSessao" variant="primary">
                    Registrar sessão de hoje
                </flux:button>
            @endif
        </flux:card>
    </div>
</div>
