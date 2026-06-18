<x-filament-panels::page>
    <p class="text-sm text-gray-600 mb-4">
        Sincroniza a vault Obsidian com o banco de dados. Apenas arquivos alterados são reprocessados.
    </p>

    @if($output)
        <div class="bg-gray-900 text-green-400 rounded-lg p-4 font-mono text-xs whitespace-pre-wrap overflow-auto max-h-96">{{ $output }}</div>
    @else
        <p class="text-sm text-gray-400 italic">Clique em "Executar Sync" para iniciar.</p>
    @endif
</x-filament-panels::page>
