<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ThemeSettings extends Page
{
    protected string $view = 'filament.pages.theme-settings';

    protected static ?string $title = 'Tema da Interface';

    protected static ?string $navigationLabel = 'Tema';

    public string $accent = 'indigo';

    public string $base = 'stone';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-swatch';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ferramentas';
    }

    public function mount(): void
    {
        $this->accent = Setting::get('accent_color', 'indigo');
        $this->base = Setting::get('base_color', 'stone');
    }

    public function save(): void
    {
        Setting::set('accent_color', $this->accent);
        Setting::set('base_color', $this->base);

        Notification::make()
            ->title('Tema salvo com sucesso')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar Tema')
                ->icon('heroicon-o-check')
                ->action('save'),
        ];
    }
}
