<div
    x-data="{
        get meRespondidas() {
            return Object.keys($wire.respostas).length;
        },
        get totalMe() {
            return {{ count($questoesME) }};
        },
        get progresso() {
            return this.totalMe > 0 ? Math.round((this.meRespondidas / this.totalMe) * 100) : 100;
        },
        decorrido: 0,
        intervalo: null,
        iniciarCronometro() {
            if (!this.intervalo) {
                this.intervalo = setInterval(() => this.decorrido++, 1000);
            }
        },
        pararCronometro() {
            clearInterval(this.intervalo);
            this.intervalo = null;
        },
        formatarTempo(s) {
            let m = Math.floor(s / 60);
            let ss = s % 60;
            return String(m).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
        },
        submeter() {
            this.pararCronometro();
            $wire.tempoDecorrido = this.decorrido;
            $wire.enviar();
        }
    }"
    x-init="$wire.enviado || iniciarCronometro(); $watch('$wire.enviado', v => { if (v) pararCronometro(); })"
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
                    @if(count($questoesDis) > 0)
                        {{ count($questoesME) }} ME + {{ count($questoesDis) }} dissertativas
                    @else
                        <span x-text="meRespondidas"></span> de {{ count($questoesME) }} respondidas
                    @endif
                </flux:text>
            </div>

            @if($enviado && $resultado)
                @php
                    $pontosME = $resultado->acertos;
                    $totalME = $resultado->total;
                    $notasDis = $resultado->notas_dissertativas ?? [];
                    $pontosDis = collect($notasDis)->sum('nota_total');
                    $totalPontos = $totalME + count($notasDis);
                    $pontosTotal = $pontosME + $pontosDis;
                    $pct = $totalPontos > 0 ? round(($pontosTotal / $totalPontos) * 100) : 0;
                @endphp
                <div class="flex items-center gap-4">
                    @if($resultado->tempo_realizado_segundos)
                        <div class="text-center">
                            <div class="text-xs" style="color: var(--sw-muted-text)">Tempo</div>
                            <div class="font-mono font-bold text-sm">{{ gmdate('i:s', $resultado->tempo_realizado_segundos) }}</div>
                        </div>
                    @endif
                    <div class="text-center px-4 py-2 rounded-lg" style="background-color: var(--sw-accent-tint);">
                        <div class="text-2xl font-bold" style="color: var(--color-accent)">
                            {{ number_format($pontosTotal, 1) }}<span class="text-base font-normal text-zinc-400">/{{ $totalPontos }}</span>
                        </div>
                        <div class="text-xs mt-0.5" style="color: var(--sw-muted-text)">
                            {{ $pct }}% de acerto
                        </div>
                    </div>
                    {{-- Botão Exportar PDF após conclusão (E6) --}}
                    @include('livewire.partials.simulado-pdf-modal', ['geracaoId' => $geracao->id, 'temRespostas' => true, 'nativeSave' => true])
                </div>
            @else
                <div class="flex items-center gap-3">
                    {{-- Cronômetro --}}
                    <div class="text-center">
                        <div class="font-mono font-bold text-lg" style="color: var(--color-accent)" x-text="formatarTempo(decorrido)">00:00</div>
                        @if(($geracao->escopo['tempo_estimado_segundos'] ?? 0) > 0)
                            <div class="text-xs" style="color: var(--sw-muted-text)">/ {{ floor($geracao->escopo['tempo_estimado_segundos'] / 60) }} min</div>
                        @endif
                    </div>
                    <flux:button
                        variant="primary"
                        size="sm"
                        x-on:click="submeter()"
                        x-bind:disabled="meRespondidas < totalMe"
                        x-bind:class="meRespondidas < totalMe ? 'opacity-50' : ''"
                    >
                        Enviar respostas
                    </flux:button>
                    {{-- Botão Exportar PDF antes da conclusão (E5) --}}
                    @include('livewire.partials.simulado-pdf-modal', ['geracaoId' => $geracao->id, 'temRespostas' => false, 'nativeSave' => true])
                </div>
            @endif
        </div>

        {{-- Barra de progresso (só ME) --}}
        @if(count($questoesME) > 0)
            <div class="mt-3">
                <flux:progress x-bind:value="progresso" class="h-1.5" />
            </div>
        @endif
    </flux:card>

    {{-- Resultado geral --}}
    @if($enviado && $resultado)
        @php
            $taxaAcerto = $totalPontos > 0 ? ($pontosTotal / $totalPontos) : 0;
            $calloutVariant = $taxaAcerto >= 0.7 ? 'success' : 'warning';
            $calloutIcon    = $taxaAcerto >= 0.7 ? 'check-circle' : 'exclamation-triangle';
        @endphp
        <flux:callout variant="{{ $calloutVariant }}" icon="{{ $calloutIcon }}" class="mb-6">
            <flux:callout.heading>
                @if($taxaAcerto >= 0.7)
                    Bom resultado! {{ number_format($pontosTotal, 1) }} de {{ $totalPontos }} pontos.
                @else
                    {{ number_format($pontosTotal, 1) }} de {{ $totalPontos }} pontos. Revise as questões erradas.
                @endif
            </flux:callout.heading>
            @if(count($questoesME) > 0 && count($questoesDis) > 0)
                ME: {{ $pontosME }}/{{ $totalME }} acertos · Dissertativas: {{ number_format($pontosDis, 1) }}/{{ count($notasDis) }} pontos.
            @endif
            Gabarito comentado disponível em cada questão abaixo.
        </flux:callout>
    @endif

    {{-- Questões ME --}}
    @if(count($questoesME) > 0)
        <flux:heading size="base" class="mb-4 flex items-center gap-2">
            <flux:badge color="zinc">Múltipla Escolha</flux:badge>
            <flux:text size="sm" style="color: var(--sw-muted-text)">{{ count($questoesME) }} questões</flux:text>
        </flux:heading>

        @foreach($questoesME as $i => $questao)
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
    @endif

    {{-- Questões Dissertativas --}}
    @if(count($questoesDis) > 0)
        <flux:separator class="{{ count($questoesME) > 0 ? 'my-8' : 'mb-6' }}" />

        <flux:heading size="base" class="mb-4 flex items-center gap-2">
            <flux:badge color="blue">Dissertativas</flux:badge>
            <flux:text size="sm" style="color: var(--sw-muted-text)">{{ count($questoesDis) }} questões · cada uma vale 1 ponto</flux:text>
        </flux:heading>

        @foreach($questoesDis as $i => $questao)
            @php
                $notaDis = $resultado?->notas_dissertativas[$i] ?? null;
                $notaTotal = $notaDis['nota_total'] ?? null;

                $cardBorderStyle = '';
                if ($enviado && $notaTotal !== null) {
                    $cardBorderStyle = $notaTotal >= 0.7 ? 'border-left: 4px solid #16a34a;' : 'border-left: 4px solid #f59e0b;';
                }
            @endphp
            <flux:card class="p-6 mb-4" style="{{ $cardBorderStyle }}">

                <div class="flex items-center gap-2 mb-3">
                    <flux:badge size="sm" color="blue">Dissertativa {{ $i + 1 }}</flux:badge>
                    @if($enviado && $notaTotal !== null)
                        <flux:badge size="sm" color="{{ $notaTotal >= 0.7 ? 'green' : 'yellow' }}">
                            {{ number_format($notaTotal, 2) }} / 1,00
                        </flux:badge>
                    @endif
                </div>

                <flux:text class="font-medium mb-4 text-base">{{ $questao['enunciado'] }}</flux:text>

                {{-- Rubrica (sempre visível) --}}
                @if(!empty($questao['rubrica']))
                    <div class="mb-4 p-3 rounded-lg" style="background-color: var(--sw-accent-tint);">
                        <flux:text size="xs" class="font-semibold mb-2" style="color: var(--color-accent)">Critérios de avaliação</flux:text>
                        <ul class="space-y-1">
                            @foreach($questao['rubrica'] as $criterio)
                                <li class="flex justify-between text-xs">
                                    <span style="color: var(--sw-muted-text)">{{ $criterio['criterio'] }}</span>
                                    <span class="font-medium">peso {{ number_format($criterio['peso'], 0, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Textarea de resposta --}}
                <div class="mb-2">
                    <flux:text size="xs" class="mb-1 font-medium" style="color: var(--sw-muted-text)">Sua resposta</flux:text>
                    <textarea
                        wire:model="respostasDissertativas.{{ $i }}"
                        @disabled($enviado)
                        rows="5"
                        placeholder="Digite sua resposta aqui..."
                        class="w-full text-sm border rounded-lg p-3 resize-y bg-white dark:bg-zinc-900 focus:outline-none focus:ring-1"
                        style="border-color: var(--sw-card-border); focus:border-color: var(--color-accent)"
                    ></textarea>
                </div>

                {{-- Gabarito dissertativa após envio --}}
                @if($enviado && $notaDis)
                    <div class="mt-4 pt-4 border-t space-y-3" style="border-color: var(--sw-card-border)">
                        <flux:text size="sm" class="font-semibold" style="color: var(--sw-muted-text)">Avaliação por critério</flux:text>
                        @foreach($notaDis['notas'] ?? [] as $notaCriterio)
                            <div class="text-sm">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="font-medium">{{ $notaCriterio['criterio'] }}</span>
                                    <flux:badge size="sm" color="{{ ($notaCriterio['nota'] ?? 0) >= 0.7 ? 'green' : 'yellow' }}">
                                        {{ number_format($notaCriterio['nota'] ?? 0, 2) }}
                                    </flux:badge>
                                </div>
                                <flux:text size="xs" style="color: var(--sw-muted-text)">{{ $notaCriterio['feedback'] ?? '' }}</flux:text>
                            </div>
                        @endforeach

                        @if(!empty($notaDis['feedback_geral']))
                            <div class="pt-2 border-t" style="border-color: var(--sw-card-border)">
                                <flux:text size="xs" class="font-semibold mb-1" style="color: var(--sw-muted-text)">Feedback geral</flux:text>
                                <flux:text size="sm">{{ $notaDis['feedback_geral'] }}</flux:text>
                            </div>
                        @endif

                        {{-- Gabarito referência --}}
                        @if(!empty($questao['gabarito_referencia']))
                            <details class="mt-2">
                                <summary class="text-xs cursor-pointer" style="color: var(--sw-muted-text)">Ver gabarito de referência</summary>
                                <div class="mt-2 p-3 rounded-lg text-sm" style="background-color: var(--sw-card-bg); border: 1px solid var(--sw-card-border)">
                                    {{ $questao['gabarito_referencia'] }}
                                </div>
                            </details>
                        @endif

                        @php $fonteIds = collect($questao['fontes'] ?? [])->pluck('pagina_id')->unique(); @endphp
                        @if($fonteIds->isNotEmpty())
                            <div class="flex flex-wrap gap-1 pt-2 border-t" style="border-color: var(--sw-card-border)">
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
    @endif

    {{-- Botão de envio (bottom) --}}
    @if(!$enviado)
        <div class="mt-6 flex justify-end">
            <flux:button
                variant="primary"
                x-on:click="submeter()"
                x-bind:disabled="meRespondidas < totalMe"
                x-bind:class="meRespondidas < totalMe ? 'opacity-50' : ''"
                icon="check"
            >
                <span>Enviar respostas</span>
                <span x-show="meRespondidas < totalMe" class="ml-1 text-xs opacity-70">
                    (<span x-text="totalMe - meRespondidas"></span> ME faltando)
                </span>
            </flux:button>
        </div>
    @endif
</div>
