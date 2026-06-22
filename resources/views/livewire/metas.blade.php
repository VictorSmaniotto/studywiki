<div wire:poll.30000ms>
    {{-- Header --}}
    <div class="py-10">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <flux:heading size="xl" class="text-3xl! font-bold! tracking-tight!">
                    Metas Semanais
                </flux:heading>
                <flux:subheading class="mt-1">
                    Semana de {{ \Illuminate\Support\Carbon::now()->startOfWeek()->format('d/m') }}
                    a {{ \Illuminate\Support\Carbon::now()->endOfWeek()->format('d/m') }}
                </flux:subheading>
            </div>
        </div>
    </div>

    <flux:separator class="mb-8" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        {{-- Barras de progresso --}}
        <div class="space-y-4">
            <flux:heading size="lg" class="mb-4">Progresso desta semana</flux:heading>

            @php
                $categorias = [
                    'simulados'  => ['label' => 'Simulados concluídos', 'icon' => 'academic-cap'],
                    'flashcards' => ['label' => 'Flashcards revisados', 'icon' => 'rectangle-stack'],
                    'geracoes'   => ['label' => 'Gerações criadas',      'icon' => 'sparkles'],
                ];
            @endphp

            @foreach($categorias as $chave => $cat)
                @php
                    $meta  = $progresso[$chave]['meta'];
                    $atual = $progresso[$chave]['atual'];
                    $pct   = $meta > 0 ? min(100, round($atual / $meta * 100)) : 0;
                    $cor   = $pct >= 100 ? 'var(--color-green-500)' : 'var(--color-accent)';
                @endphp
                <flux:card class="p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                             style="background-color: var(--sw-accent-tint)">
                            <flux:icon name="{{ $cat['icon'] }}" class="w-4 h-4" style="color: var(--color-accent)" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium">{{ $cat['label'] }}</div>
                            <div class="text-xs mt-0.5" style="color: var(--sw-muted-text)">
                                {{ $atual }} / {{ $meta > 0 ? $meta : '—' }}
                                @if($meta > 0 && $pct >= 100)
                                    <span class="ml-1 font-semibold text-green-600 dark:text-green-400">✓ Meta atingida!</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-lg font-bold flex-shrink-0" style="color: {{ $cor }}">
                            {{ $meta > 0 ? $pct . '%' : '—' }}
                        </div>
                    </div>

                    <div class="w-full h-2 rounded-full" style="background-color: var(--sw-card-border)">
                        @if($meta > 0)
                            <div class="h-2 rounded-full transition-all duration-500"
                                 style="width: {{ $pct }}%; background-color: {{ $cor }}">
                            </div>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Form de configuração --}}
        <div>
            <flux:heading size="lg" class="mb-4">Configurar metas</flux:heading>
            <flux:card class="p-6">
                <div class="space-y-4">
                    <div>
                        <flux:label for="metaSimulados">Simulados por semana</flux:label>
                        <flux:input
                            id="metaSimulados"
                            type="number"
                            min="0"
                            wire:model="metaSimulados"
                            class="mt-1"
                            placeholder="0 = sem meta"
                        />
                        @error('metaSimulados')
                            <flux:text class="text-red-500 text-xs mt-1">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="metaFlashcards">Flashcards revisados por semana</flux:label>
                        <flux:input
                            id="metaFlashcards"
                            type="number"
                            min="0"
                            wire:model="metaFlashcards"
                            class="mt-1"
                            placeholder="0 = sem meta"
                        />
                        @error('metaFlashcards')
                            <flux:text class="text-red-500 text-xs mt-1">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="metaGeracoes">Gerações por semana</flux:label>
                        <flux:input
                            id="metaGeracoes"
                            type="number"
                            min="0"
                            wire:model="metaGeracoes"
                            class="mt-1"
                            placeholder="0 = sem meta"
                        />
                        @error('metaGeracoes')
                            <flux:text class="text-red-500 text-xs mt-1">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="pt-2 flex items-center gap-3">
                        <flux:button wire:click="salvar" variant="primary">
                            Salvar metas
                        </flux:button>
                        @if($salvo)
                            <flux:text class="text-sm" style="color: var(--color-accent)">
                                Metas salvas!
                            </flux:text>
                        @endif
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>
