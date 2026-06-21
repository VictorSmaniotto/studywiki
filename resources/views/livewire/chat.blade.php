<div class="py-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Chat com a Vault</h1>
            <p class="text-sm mt-1" style="color: var(--sw-muted-text)">Pergunte qualquer coisa sobre seus apontamentos.</p>
        </div>
        @if(count($historico) > 0)
            <flux:button wire:click="limpar" variant="ghost" size="sm" icon="trash">
                Limpar
            </flux:button>
        @endif
    </div>

    {{-- Filtro de disciplina --}}
    <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap">Disciplina:</label>
        <select wire:model="escopoSlug"
                class="text-sm rounded-lg border px-3 py-1.5 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100"
                style="border-color: var(--sw-card-border)">
            <option value="">Todas</option>
            @foreach($disciplinas as $d)
                <option value="{{ $d->slug }}">{{ $d->nome }}</option>
            @endforeach
        </select>
    </div>

    {{-- Histórico de mensagens --}}
    <div class="space-y-4 min-h-[200px]">
        @forelse($historico as $msg)
            @if($msg['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[80%] rounded-2xl rounded-tr-sm px-4 py-3 text-sm font-medium"
                         style="background-color: var(--color-accent); color: white;">
                        {{ $msg['content'] }}
                    </div>
                </div>
            @else
                <div class="flex justify-start">
                    <div class="max-w-[85%] space-y-2">
                        <div class="rounded-2xl rounded-tl-sm px-4 py-3 text-sm leading-relaxed border"
                             style="background-color: var(--sw-page-bg); border-color: var(--sw-card-border); color: inherit;">
                            {!! nl2br(e($msg['content'])) !!}
                        </div>
                        @if(!empty($msg['fontes']))
                            <div class="text-xs space-y-0.5 px-1" style="color: var(--sw-muted-text)">
                                <span class="font-medium">Fontes:</span>
                                @foreach($msg['fontes'] as $fonte)
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        {{ $fonte['titulo_pagina'] }}
                                        @if($fonte['heading_path'])
                                            <span class="opacity-60">· {{ $fonte['heading_path'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <div class="text-center py-16" style="color: var(--sw-muted-text)">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-sm">Nenhuma mensagem ainda. Faça uma pergunta sobre seus apontamentos!</p>
            </div>
        @endforelse

        @if($carregando)
            <div class="flex justify-start">
                <div class="rounded-2xl rounded-tl-sm px-4 py-3 border"
                     style="background-color: var(--sw-page-bg); border-color: var(--sw-card-border)">
                    <div class="flex gap-1 items-center">
                        <div class="w-2 h-2 rounded-full animate-bounce" style="background-color: var(--color-accent); animation-delay: 0ms"></div>
                        <div class="w-2 h-2 rounded-full animate-bounce" style="background-color: var(--color-accent); animation-delay: 150ms"></div>
                        <div class="w-2 h-2 rounded-full animate-bounce" style="background-color: var(--color-accent); animation-delay: 300ms"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Input --}}
    <form wire:submit.prevent="enviar" class="flex gap-2">
        <flux:input
            wire:model="pergunta"
            placeholder="Pergunte algo sobre seus apontamentos..."
            class="flex-1"
            :disabled="$carregando"
            autocomplete="off"
        />
        <flux:button type="submit" :disabled="$carregando" icon="paper-airplane">
            Enviar
        </flux:button>
    </form>
    @error('pergunta')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

</div>
