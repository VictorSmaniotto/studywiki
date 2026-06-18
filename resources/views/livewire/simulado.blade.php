<div
    x-data="{
        get respondidas() {
            return Object.keys($wire.respostas).length;
        },
        get total() {
            return {{ count($geracao->payload['questoes'] ?? []) }};
        },
        get progresso() {
            return this.total > 0 ? Math.round((this.respondidas / this.total) * 100) : 0;
        }
    }"
>
    {{-- Breadcrumb --}}
    <div class="mb-6">
        <flux:link href="{{ route('biblioteca') }}" class="text-sm inline-flex items-center gap-1">
            <flux:icon name="arrow-left" class="w-3.5 h-3.5" />
            Biblioteca
        </flux:link>
    </div>

    {{-- Header do simulado --}}
    <flux:card class="p-5 mb-6 sticky top-16 z-10 border shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="base">Simulado</flux:heading>
                <flux:text size="sm" class="mt-0.5">
                    <span x-text="respondidas"></span> de {{ count($geracao->payload['questoes'] ?? []) }} respondidas
                </flux:text>
            </div>

            @if($enviado && $resultado)
                <div class="text-center px-4 py-2 rounded-lg" style="background-color: var(--sw-accent-tint);">
                    <div class="text-2xl font-bold" style="color: var(--color-accent)">
                        {{ $resultado->acertos }}<span class="text-base font-normal text-zinc-400">/{{ $resultado->total }}</span>
                    </div>
                    <div class="text-xs mt-0.5" style="color: var(--sw-muted-text)">
                        {{ round(($resultado->acertos / max($resultado->total, 1)) * 100) }}% de acerto
                    </div>
                </div>
            @else
                <flux:button
                    wire:click="enviar"
                    variant="primary"
                    size="sm"
                    x-bind:disabled="respondidas < total"
                    x-bind:class="respondidas < total ? 'opacity-50' : ''"
                >
                    Enviar respostas
                </flux:button>
            @endif
        </div>

        {{-- Barra de progresso --}}
        <div class="mt-3">
            <flux:progress x-bind:value="progresso" class="h-1.5" />
        </div>
    </flux:card>

    {{-- Resultado geral --}}
    @if($enviado && $resultado)
        @php
            $taxaAcerto = $resultado->acertos / max($resultado->total, 1);
            $calloutVariant = $taxaAcerto >= 0.7 ? 'success' : 'warning';
            $calloutIcon    = $taxaAcerto >= 0.7 ? 'check-circle' : 'exclamation-triangle';
        @endphp
        <flux:callout variant="{{ $calloutVariant }}" icon="{{ $calloutIcon }}" class="mb-6">
            <flux:callout.heading>
                @if($taxaAcerto >= 0.7)
                    Bom resultado! {{ $resultado->acertos }} de {{ $resultado->total }} acertos.
                @else
                    {{ $resultado->acertos }} de {{ $resultado->total }} acertos. Revise as questões erradas.
                @endif
            </flux:callout.heading>
            Gabarito comentado disponível em cada questão abaixo.
        </flux:callout>
    @endif

    {{-- Questões --}}
    @foreach($geracao->payload['questoes'] ?? [] as $i => $questao)
        @php
            $respostaDada = $respostas[(string)$i] ?? null;
            $correta      = $questao['correta'];
            $acertou      = $enviado && $respostaDada === $correta;
            $errou        = $enviado && $respostaDada && $respostaDada !== $correta;

            $cardBorderStyle = '';
            if ($acertou) $cardBorderStyle = 'border-left: 4px solid #16a34a;';
            elseif ($errou) $cardBorderStyle = 'border-left: 4px solid #dc2626;';
        @endphp
        <flux:card class="p-6 mb-4" style="{{ $cardBorderStyle }}">

            {{-- Número + status --}}
            <div class="flex items-center gap-2 mb-1">
                <flux:badge size="sm" color="zinc">Questão {{ $i + 1 }}</flux:badge>
                @if($enviado)
                    @if($acertou)
                        <flux:badge size="sm" style="background-color:#16a34a15;color:#16a34a;border:none;">Correta</flux:badge>
                    @elseif($errou)
                        <flux:badge size="sm" style="background-color:#dc262615;color:#dc2626;border:none;">Incorreta</flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc">Não respondida</flux:badge>
                    @endif
                @endif
            </div>

            @if(!empty($questao['contexto']))
                <flux:text size="sm" class="italic mb-3" style="color: var(--sw-muted-text)">{{ $questao['contexto'] }}</flux:text>
            @endif
            <flux:text class="font-medium mb-4 text-base">{{ $questao['enunciado'] }}</flux:text>

            {{-- Alternativas --}}
            <div class="space-y-2">
                @foreach(['a', 'b', 'c', 'd', 'e'] as $letra)
                    @php
                        $alternativa = $questao['alternativas'][$letra] ?? '';
                        if (!$alternativa) { continue; }
                        $selecionada = $respostaDada === $letra;
                        $isCorreta   = $letra === $correta;

                        if ($enviado && $isCorreta) {
                            $labelClass = 'border-emerald-300 bg-emerald-50 dark:bg-emerald-950/20';
                            $spanClass  = 'text-emerald-800 dark:text-emerald-300 font-medium';
                        } elseif ($enviado && $selecionada && !$isCorreta) {
                            $labelClass = 'border-red-300 bg-red-50 dark:bg-red-950/20';
                            $spanClass  = 'text-red-700 dark:text-red-400 line-through';
                        } else {
                            $labelClass = $enviado ? 'border-transparent' : 'border-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer';
                            $spanClass  = 'text-zinc-700 dark:text-zinc-300';
                        }
                    @endphp
                    <label class="flex items-start gap-3 p-3 rounded-lg border transition-colors {{ $labelClass }}">
                        <input
                            type="radio"
                            wire:model="respostas.{{ $i }}"
                            value="{{ $letra }}"
                            @disabled($enviado)
                            class="mt-0.5 flex-shrink-0"
                            style="accent-color: var(--color-accent)"
                        >
                        <span class="text-sm leading-relaxed {{ $spanClass }}">
                            <span class="font-semibold">{{ strtoupper($letra) }})</span> {{ $alternativa }}
                        </span>
                    </label>
                @endforeach
            </div>

            {{-- Gabarito comentado --}}
            @if($enviado)
                <div class="mt-4 pt-4 border-t space-y-1.5" style="border-color: var(--sw-card-border)">
                    <flux:text size="sm" class="font-semibold mb-2" style="color: var(--sw-muted-text)">Comentário do gabarito</flux:text>
                    @foreach(['a', 'b', 'c', 'd', 'e'] as $letra)
                        @if(!empty($questao['comentario_gabarito'][$letra]))
                            @php
                                $isCorrectaGab = $letra === $correta;
                                $letClass = $isCorrectaGab ? 'text-emerald-700' : 'text-zinc-400';
                                $txtClass = $isCorrectaGab ? 'text-emerald-800 dark:text-emerald-300' : 'text-zinc-500';
                            @endphp
                            <div class="flex gap-2 text-xs">
                                <span class="font-bold flex-shrink-0 {{ $letClass }}">{{ strtoupper($letra) }})</span>
                                <span class="{{ $txtClass }}">{{ $questao['comentario_gabarito'][$letra] }}</span>
                            </div>
                        @endif
                    @endforeach

                    {{-- Fontes --}}
                    @php $fonteIds = collect($questao['fontes'] ?? [])->pluck('pagina_id')->unique(); @endphp
                    @if($fonteIds->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mt-2 pt-2 border-t" style="border-color: var(--sw-card-border)">
                            <flux:text size="xs" style="color: var(--sw-muted-text)">Fontes:</flux:text>
                            @foreach($fonteIds as $paginaId)
                                @if(isset($fontesPaginas[$paginaId]))
                                    <flux:badge size="sm" color="zinc">
                                        {{ $fontesPaginas[$paginaId]->pagina->titulo ?? 'p.'.$paginaId }}
                                    </flux:badge>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </flux:card>
    @endforeach

    {{-- Botão de envio (bottom) --}}
    @if(!$enviado)
        <div class="mt-6 flex justify-end">
            <flux:button
                wire:click="enviar"
                variant="primary"
                x-bind:disabled="respondidas < total"
                x-bind:class="respondidas < total ? 'opacity-50' : ''"
                icon="check"
            >
                <span>Enviar respostas</span>
                <span x-show="respondidas < total" class="ml-1 text-xs opacity-70">
                    (<span x-text="total - respondidas"></span> faltando)
                </span>
            </flux:button>
        </div>
    @endif
</div>
