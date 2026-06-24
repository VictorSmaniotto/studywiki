<div class="flex" style="height: calc(100svh - 6.5rem)">

    @php
        $temPendente = collect($historico)->contains(fn($m) => ($m['status'] ?? 'done') === 'pending');
    @endphp

    @if($temPendente)
        <div wire:poll.2s="refreshHistorico" class="hidden"></div>
    @endif

    {{-- Sidebar de histórico --}}
    @if($sidebarAberta)
    <div class="w-60 shrink-0 flex flex-col border-r" style="border-color: var(--sw-card-border)">

        {{-- Nova conversa --}}
        <div class="p-3 shrink-0 border-b" style="border-color: var(--sw-card-border)">
            <button
                wire:click="limpar"
                class="w-full flex items-center gap-2 text-sm px-3 py-2 rounded-lg border hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                style="border-color: var(--sw-card-border)">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nova conversa
            </button>
        </div>

        {{-- Lista de sessões --}}
        <div class="flex-1 overflow-y-auto py-2">
            @forelse($sessoes as $s)
                <div class="group flex items-center gap-1 px-2 rounded-lg mx-2 mb-0.5 {{ $s->id === $sessaoId ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}">
                    <button
                        wire:click="carregarSessao({{ $s->id }})"
                        class="flex-1 text-left py-2 px-1 text-sm truncate"
                        title="{{ $s->titulo }}">
                        {{ $s->titulo ?? 'Conversa' }}
                    </button>
                    <button
                        wire:click="deletarSessao({{ $s->id }})"
                        class="shrink-0 p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity hover:text-red-500"
                        style="color: var(--sw-muted-text)"
                        title="Apagar">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @empty
                <p class="text-xs text-center py-6 px-4" style="color: var(--sw-muted-text)">
                    Nenhuma conversa salva ainda.
                </p>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Área principal do chat --}}
    <div class="flex-1 min-w-0 flex flex-col">

        {{-- Top bar --}}
        <div class="shrink-0 flex flex-col gap-3 pt-6 pb-4 px-6 border-b" style="border-color: var(--sw-card-border)">

            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    {{-- Toggle sidebar --}}
                    <button
                        wire:click="$toggle('sidebarAberta')"
                        class="p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors shrink-0"
                        title="{{ $sidebarAberta ? 'Fechar histórico' : 'Abrir histórico' }}"
                        style="color: var(--sw-muted-text)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-tight">Chat com a Vault</h1>
                        <p class="text-xs mt-0.5" style="color: var(--sw-muted-text)">Pergunte qualquer coisa sobre seus apontamentos.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 mt-0.5">
                    {{-- Dropdown multi-disciplina --}}
                    <div class="relative" x-data="{ aberto: false }">
                        <button
                            type="button"
                            @click="aberto = !aberto"
                            class="flex items-center gap-1.5 text-sm rounded-lg border px-3 py-1.5 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                            style="border-color: var(--sw-card-border)">
                            <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 010 2H4a1 1 0 01-1-1zm3 4a1 1 0 011-1h10a1 1 0 010 2H7a1 1 0 01-1-1zm3 4a1 1 0 011-1h4a1 1 0 010 2h-4a1 1 0 01-1-1z"/>
                            </svg>
                            @if(count($disciplinasSlugs) === 0)
                                Todas
                            @elseif(count($disciplinasSlugs) === 1)
                                1 disciplina
                            @else
                                {{ count($disciplinasSlugs) }} disciplinas
                            @endif
                            <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div
                            x-show="aberto"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            @click.outside="aberto = false"
                            class="absolute top-full right-0 mt-1 z-20 rounded-xl border shadow-lg py-1 min-w-52 max-h-72 overflow-y-auto"
                            style="background-color: var(--sw-page-bg); border-color: var(--sw-card-border)">

                            @foreach($disciplinas as $d)
                                <label class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 text-sm select-none">
                                    <input
                                        type="checkbox"
                                        wire:model.live="disciplinasSlugs"
                                        value="{{ $d->slug }}"
                                        class="rounded border-zinc-300 dark:border-zinc-600">
                                    {{ $d->nome }}
                                </label>
                            @endforeach

                            @if(count($disciplinasSlugs) > 0)
                                <div class="border-t mt-1 pt-1" style="border-color: var(--sw-card-border)">
                                    <button
                                        type="button"
                                        wire:click="$set('disciplinasSlugs', [])"
                                        class="w-full text-left px-3 py-2 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        style="color: var(--sw-muted-text)">
                                        Limpar seleção
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if(count($historico) > 0)
                        <flux:button wire:click="limpar" variant="ghost" size="sm" icon="trash" />
                    @endif
                </div>
            </div>

            {{-- Pills das disciplinas selecionadas --}}
            @if(count($disciplinasSlugs) > 0)
                <div class="flex flex-wrap gap-1.5 pl-8">
                    @foreach($disciplinas->whereIn('slug', $disciplinasSlugs) as $d)
                        <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full"
                              style="background-color: var(--sw-accent-tint); color: var(--color-accent)">
                            {{ $d->nome }}
                            <button type="button"
                                    wire:click="removerDisciplina('{{ $d->slug }}')"
                                    class="hover:opacity-70 leading-none ml-0.5">×</button>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Área de mensagens (scroll) --}}
        <div class="flex-1 min-h-0 overflow-y-auto py-4 px-6 space-y-4">

            @forelse($historico as $msg)
                @if($msg['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-2xl rounded-tr-sm px-4 py-3 text-sm"
                             style="background-color: var(--color-accent); color: white;">
                            {{ $msg['content'] }}
                        </div>
                    </div>
                @elseif(($msg['status'] ?? 'done') === 'pending')
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
                @else
                    <div class="flex justify-start">
                        <div class="max-w-[85%] space-y-2">
                            <div class="rounded-2xl rounded-tl-sm px-4 py-3 border"
                                 style="background-color: var(--sw-page-bg); border-color: var(--sw-card-border)">
                                <x-markdown :content="$msg['content']" />
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
                <div class="flex flex-col items-center justify-center h-full text-center" style="color: var(--sw-muted-text)">
                    <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-sm">Nenhuma mensagem ainda. Faça uma pergunta!</p>
                </div>
            @endforelse
        </div>

        {{-- Input --}}
        <div class="shrink-0 px-6 pt-3 pb-6 border-t space-y-1" style="border-color: var(--sw-card-border)">
            <form wire:submit.prevent="enviar" class="flex gap-2">
                <flux:input
                    wire:model="pergunta"
                    placeholder="Pergunte algo sobre seus apontamentos..."
                    class="flex-1"
                    :disabled="$temPendente"
                    autocomplete="off"
                />
                <flux:button type="submit" :disabled="$temPendente" icon="paper-airplane">
                    Enviar
                </flux:button>
            </form>
            @error('pergunta')
                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

    </div>{{-- /chat column --}}

</div>
