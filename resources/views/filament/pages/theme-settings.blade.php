<x-filament-panels::page>
    @php
    $accentColors = [
        'red'     => ['label' => 'Red',     'hex' => '#dc2626'],
        'orange'  => ['label' => 'Orange',  'hex' => '#ea580c'],
        'amber'   => ['label' => 'Amber',   'hex' => '#d97706'],
        'yellow'  => ['label' => 'Yellow',  'hex' => '#ca8a04'],
        'lime'    => ['label' => 'Lime',    'hex' => '#65a30d'],
        'green'   => ['label' => 'Green',   'hex' => '#16a34a'],
        'emerald' => ['label' => 'Emerald', 'hex' => '#059669'],
        'teal'    => ['label' => 'Teal',    'hex' => '#0d9488'],
        'cyan'    => ['label' => 'Cyan',    'hex' => '#0891b2'],
        'sky'     => ['label' => 'Sky',     'hex' => '#0284c7'],
        'blue'    => ['label' => 'Blue',    'hex' => '#2563eb'],
        'indigo'  => ['label' => 'Indigo',  'hex' => '#4f46e5'],
        'violet'  => ['label' => 'Violet',  'hex' => '#7c3aed'],
        'purple'  => ['label' => 'Purple',  'hex' => '#9333ea'],
        'fuchsia' => ['label' => 'Fuchsia', 'hex' => '#c026d3'],
        'pink'    => ['label' => 'Pink',    'hex' => '#db2777'],
        'rose'    => ['label' => 'Rose',    'hex' => '#e11d48'],
    ];

    $baseColors = [
        'stone'   => ['label' => 'Stone',   'light' => '#e7e5e4', 'dark' => '#a8a29e'],
        'zinc'    => ['label' => 'Zinc',    'light' => '#e4e4e7', 'dark' => '#a1a1aa'],
        'gray'    => ['label' => 'Gray',    'light' => '#e5e7eb', 'dark' => '#9ca3af'],
        'slate'   => ['label' => 'Slate',   'light' => '#e2e8f0', 'dark' => '#94a3b8'],
        'neutral' => ['label' => 'Neutral', 'light' => '#e5e5e5', 'dark' => '#a3a3a3'],
    ];
    @endphp

    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        {{-- Preview --}}
        <x-filament::section>
            <x-slot name="heading">Prévia</x-slot>

            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <button
                    style="background-color: {{ $accentColors[$accent]['hex'] }}; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; border: none; cursor: default;"
                >
                    Botão de ação
                </button>
                <span style="color: {{ $accentColors[$accent]['hex'] }}; border-bottom: 2px solid {{ $accentColors[$accent]['hex'] }}; font-size: 0.875rem; font-weight: 500; padding-bottom: 2px;">
                    Link ativo
                </span>
                <div style="background-color: {{ $baseColors[$base]['light'] }}; border: 1px solid {{ $baseColors[$base]['dark'] }}40; padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; color: #374151;">
                    Card base <strong>{{ $baseColors[$base]['label'] }}</strong>
                </div>
                <span style="font-size: 0.75rem; color: #6b7280;">
                    Destaque: <strong>{{ $accentColors[$accent]['label'] }}</strong> · Base: <strong>{{ $baseColors[$base]['label'] }}</strong>
                </span>
            </div>
        </x-filament::section>

        {{-- Cor de Destaque --}}
        <x-filament::section>
            <x-slot name="heading">Cor de Destaque (Accent)</x-slot>
            <x-slot name="description">Usada em botões, links ativos e elementos interativos.</x-slot>

            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-start;">
                @foreach($accentColors as $name => $data)
                    <button
                        wire:click="$set('accent', '{{ $name }}')"
                        title="{{ $data['label'] }}"
                        style="display: flex; flex-direction: column; align-items: center; gap: 0.375rem; background: none; border: none; cursor: pointer; padding: 0.25rem; position: relative;"
                    >
                        <div style="
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            background-color: {{ $data['hex'] }};
                            transition: transform 0.15s;
                            {{ $accent === $name ? 'transform: scale(1.1); outline: 3px solid #fff; outline-offset: 2px; box-shadow: 0 0 0 5px ' . $data['hex'] . '60;' : '' }}
                        "></div>
                        @if($accent === $name)
                            <div style="
                                position: absolute;
                                top: 0;
                                right: 0;
                                width: 16px;
                                height: 16px;
                                background: white;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
                            ">
                                <svg width="10" height="10" viewBox="0 0 20 20" fill="{{ $data['hex'] }}">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        @endif
                        <span style="font-size: 0.65rem; color: #6b7280; font-weight: 500; white-space: nowrap;">{{ $data['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Cor Base --}}
        <x-filament::section>
            <x-slot name="heading">Cor Base (Neutro)</x-slot>
            <x-slot name="description">Define a paleta de cinzas usada nos fundos e bordas da interface.</x-slot>

            <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start;">
                @foreach($baseColors as $name => $data)
                    <button
                        wire:click="$set('base', '{{ $name }}')"
                        title="{{ $data['label'] }}"
                        style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; background: none; border: none; cursor: pointer; padding: 0.25rem; position: relative;"
                    >
                        <div style="
                            width: 64px;
                            height: 40px;
                            border-radius: 0.5rem;
                            background: linear-gradient(135deg, {{ $data['light'] }} 0%, {{ $data['dark'] }} 100%);
                            transition: transform 0.15s;
                            {{ $base === $name ? 'transform: scale(1.05); outline: 3px solid #fff; outline-offset: 2px; box-shadow: 0 0 0 5px ' . $data['dark'] . '60;' : '' }}
                        "></div>
                        @if($base === $name)
                            <div style="
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -80%);
                                width: 20px;
                                height: 20px;
                                background: white;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
                            ">
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="{{ $data['dark'] }}">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        @endif
                        <span style="font-size: 0.75rem; color: #6b7280; font-weight: 500;">{{ $data['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
