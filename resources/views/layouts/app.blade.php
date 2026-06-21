<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyWiki</title>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @php
        $accent = \App\Models\Setting::get('accent_color', 'indigo');
        $base   = \App\Models\Setting::get('base_color', 'stone');
    @endphp
    <style>
        :root {
            --color-accent: var(--color-{{ $accent }}-600);
            --color-accent-content: var(--color-{{ $accent }}-600);
            --color-accent-foreground: white;
            --sw-page-bg: var(--color-{{ $base }}-50);
            --sw-card-border: var(--color-{{ $base }}-200);
            --sw-muted: var(--color-{{ $base }}-400);
            --sw-muted-text: var(--color-{{ $base }}-500);
            --sw-accent-tint: color-mix(in oklab, var(--color-accent), transparent 85%);
        }
        .dark {
            --color-accent: var(--color-{{ $accent }}-400);
            --color-accent-content: var(--color-{{ $accent }}-400);
            --sw-page-bg: var(--color-{{ $base }}-950);
            --sw-card-border: var(--color-{{ $base }}-800);
            --sw-muted: var(--color-{{ $base }}-600);
            --sw-muted-text: var(--color-{{ $base }}-400);
            --sw-accent-tint: color-mix(in oklab, var(--color-accent), transparent 80%);
        }
        body {
            background-color: var(--sw-page-bg);
        }
    </style>
</head>
<body class="min-h-screen text-zinc-900 dark:text-zinc-100 antialiased">

    <flux:header class="bg-white dark:bg-zinc-900 border-b dark:border-zinc-800 px-6 lg:px-8" style="border-color: var(--sw-card-border)">
        <flux:brand href="/" class="me-6">
            <x-slot:logo>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5" style="color: var(--color-accent)">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
            </x-slot:logo>
            StudyWiki
        </flux:brand>

        <flux:navbar class="hidden sm:flex">
            <flux:navbar.item href="{{ route('biblioteca') }}" :current="request()->routeIs('biblioteca')">
                Biblioteca
            </flux:navbar.item>
            <flux:navbar.item href="{{ route('trilha') }}" :current="request()->routeIs('trilha')">
                Trilha
            </flux:navbar.item>
        </flux:navbar>

        <flux:spacer />

        <div class="flex items-center gap-2">
            <flux:button
                icon="moon"
                variant="ghost"
                size="sm"
                x-data
                x-on:click="$flux.dark = !$flux.dark"
                title="Alternar modo escuro"
            />
            <flux:separator vertical class="my-2 mx-1" />
            <flux:navbar.item href="/admin" icon="cog-6-tooth" class="text-zinc-500">
                Admin
            </flux:navbar.item>
        </div>
    </flux:header>

    <flux:main>
        <div class="max-w-5xl mx-auto w-full">
            {{ $slot }}
        </div>
    </flux:main>

    @livewireScripts
    @fluxScripts
</body>
</html>
