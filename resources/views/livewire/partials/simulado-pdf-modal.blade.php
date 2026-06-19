{{--
    Botão + modal Alpine para exportar PDF do simulado.
    Parâmetros:
        $geracaoId    — int
        $temRespostas — bool (habilita checkbox "Minhas respostas")
--}}
<div x-data="{ modalPdf: false, prova_branca: true, gabarito: true, respostas: {{ $temRespostas ? 'true' : 'false' }} }">
    <flux:button size="sm" icon="arrow-down-tray" x-on:click="modalPdf = true">PDF</flux:button>

    <template x-teleport="body">
        <div
            x-show="modalPdf"
            class="fixed inset-0 z-50 flex items-center justify-center"
            style="background:rgba(0,0,0,0.5);"
            x-cloak
            x-on:keydown.escape.window="modalPdf = false"
        >
            <div
                class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl p-6 w-80 border"
                style="border-color: var(--sw-card-border);"
                @click.outside="modalPdf = false"
            >
                <flux:heading size="sm" class="mb-4">Exportar PDF</flux:heading>
                <flux:text size="xs" class="mb-3" style="color: var(--sw-muted-text)">
                    Selecione as seções a incluir:
                </flux:text>

                {{-- E1: checkboxes de seção --}}
                <div class="space-y-3 mb-5">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" x-model="prova_branca" class="rounded" style="accent-color: var(--color-accent)">
                        Prova em branco
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" x-model="gabarito" class="rounded" style="accent-color: var(--color-accent)">
                        Gabarito comentado
                    </label>
                    <label class="flex items-center gap-2 text-sm {{ $temRespostas ? 'cursor-pointer' : 'opacity-40 cursor-not-allowed' }}">
                        <input
                            type="checkbox"
                            x-model="respostas"
                            class="rounded"
                            style="accent-color: var(--color-accent)"
                            {{ $temRespostas ? '' : 'disabled' }}
                        >
                        Minhas respostas + resultado
                        @if(!$temRespostas)
                            <span class="text-xs" style="color: var(--sw-muted-text)">(não realizado)</span>
                        @endif
                    </label>
                </div>

                <div class="flex gap-2 justify-end">
                    <flux:button size="sm" variant="ghost" x-on:click="modalPdf = false">Cancelar</flux:button>
                    <a
                        x-bind:href="'{{ route('simulado.pdf', $geracaoId) }}?' + [prova_branca ? 'secoes[]=prova_branca' : '', gabarito ? 'secoes[]=gabarito' : '', respostas ? 'secoes[]=respostas' : ''].filter(Boolean).join('&')"
                        target="_blank"
                        x-on:click="modalPdf = false"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium text-white"
                        style="background-color: var(--color-accent)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Baixar PDF
                    </a>
                </div>
            </div>
        </div>
    </template>
</div>
