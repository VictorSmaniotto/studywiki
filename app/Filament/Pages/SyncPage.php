<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class SyncPage extends Page
{
    protected string $view = 'filament.pages.sync-page';

    protected static ?string $title = 'Sincronização';

    protected static ?string $navigationLabel = 'Sync Vault';

    public string $output = '';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-arrow-path';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ferramentas';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Executar Sync')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    Artisan::call('studywiki:sync');
                    $this->output = Artisan::output();

                    Notification::make()
                        ->title('Sync concluído')
                        ->success()
                        ->send();
                }),
        ];
    }
}
