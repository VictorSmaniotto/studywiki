<div>
    {{-- Hero --}}
    <div class="py-12 text-center">
        <flux:heading size="xl" class="text-4xl! font-bold! tracking-tight!">
            Sua vault. Seu estudo.<br>Turbinado com IA.
        </flux:heading>
        <flux:subheading class="mt-3 text-base max-w-xl mx-auto">
            O StudyWiki transforma suas notas Obsidian em resumos, flashcards e simulados
            ancorados nas suas próprias fontes — sem inventar nada.
        </flux:subheading>

        {{-- Stats --}}
        @if($totalDisciplinas > 0)
        <div class="flex justify-center gap-10 mt-8">
            <div class="text-center">
                <div class="text-3xl font-bold" style="color: var(--color-accent)">{{ $totalDisciplinas }}</div>
                <div class="text-sm mt-1" style="color: var(--sw-muted-text)">{{ Str::plural('Disciplina', $totalDisciplinas) }}</div>
            </div>
            <div class="w-px" style="background-color: var(--sw-card-border)"></div>
            <div class="text-center">
                <div class="text-3xl font-bold" style="color: var(--color-accent)">{{ $totalPaginas }}</div>
                <div class="text-sm mt-1" style="color: var(--sw-muted-text)">{{ Str::plural('Página', $totalPaginas) }}</div>
            </div>
            <div class="w-px" style="background-color: var(--sw-card-border)"></div>
            <div class="text-center">
                <div class="text-3xl font-bold" style="color: var(--color-accent)">{{ $totalGeracoes }}</div>
                <div class="text-sm mt-1" style="color: var(--sw-muted-text)">{{ Str::plural('Geração', $totalGeracoes) }}</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Feature blocks --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-12">
        <flux:card class="p-6">
            <div class="mb-4 w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: var(--sw-accent-tint);">
                <flux:icon name="document-text" class="w-5 h-5" style="color: var(--color-accent)" />
            </div>
            <flux:heading size="sm" class="mb-2">Resumos</flux:heading>
            <flux:text class="text-sm leading-relaxed">
                Resumos estruturados em tópicos, cada bullet rastreado até a página de origem nas suas notas.
            </flux:text>
        </flux:card>

        <flux:card class="p-6">
            <div class="mb-4 w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: var(--sw-accent-tint);">
                <flux:icon name="rectangle-stack" class="w-5 h-5" style="color: var(--color-accent)" />
            </div>
            <flux:heading size="sm" class="mb-2">Flashcards</flux:heading>
            <flux:text class="text-sm leading-relaxed">
                Pares pergunta-resposta para revisão ativa, gerados a partir do conteúdo real das suas anotações.
            </flux:text>
        </flux:card>

        <flux:card class="p-6">
            <div class="mb-4 w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: var(--sw-accent-tint);">
                <flux:icon name="clipboard-document-check" class="w-5 h-5" style="color: var(--color-accent)" />
            </div>
            <flux:heading size="sm" class="mb-2">Simulados</flux:heading>
            <flux:text class="text-sm leading-relaxed">
                Questões de múltipla escolha com gabarito comentado. Cada questão ancorada nas fontes usadas.
            </flux:text>
        </flux:card>
    </div>

    {{-- Divider --}}
    <flux:separator class="mb-8" />

    {{-- Search + heading --}}
    <div class="flex items-center justify-between gap-4 mb-6">
        <flux:heading size="lg">Disciplinas</flux:heading>
        <flux:input
            wire:model.live.debounce.300ms="busca"
            placeholder="Buscar disciplina…"
            icon="magnifying-glass"
            size="sm"
            class="w-64"
            clearable
        />
    </div>

    {{-- Discipline grid --}}
    @if($disciplinas->isEmpty())
        <flux:card class="py-12 text-center">
            <flux:icon name="folder-open" class="w-10 h-10 mx-auto mb-3 text-zinc-300" />
            <flux:heading size="sm" class="mb-1">
                @if($busca)
                    Nenhuma disciplina encontrada para "{{ $busca }}"
                @else
                    Nenhuma disciplina sincronizada
                @endif
            </flux:heading>
            @unless($busca)
                <flux:text size="sm">
                    Execute <code class="bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-xs">studywiki:sync</code> para importar a vault.
                </flux:text>
            @endunless
        </flux:card>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($disciplinas as $disciplina)
                <a href="{{ route('disciplina', $disciplina->slug) }}" class="group block">
                    <flux:card class="p-5 h-full transition-shadow group-hover:shadow-md group-hover:border-[--color-accent]/30">
                        <div class="flex items-start justify-between">
                            <flux:heading size="sm" class="group-hover:text-[--color-accent] transition-colors">
                                {{ $disciplina->nome }}
                            </flux:heading>
                            <flux:icon name="arrow-right" class="w-4 h-4 mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity" style="color: var(--color-accent)" />
                        </div>
                        <flux:text size="sm" class="mt-1">
                            {{ $disciplina->paginas_count }} {{ Str::plural('página', $disciplina->paginas_count) }}
                        </flux:text>
                    </flux:card>
                </a>
            @endforeach
        </div>
    @endif
</div>
