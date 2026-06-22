<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        $this->registrarMenu();

        Window::open()
            ->title('StudyWiki')
            ->width(1280)
            ->height(800)
            ->minWidth(1024)
            ->minHeight(700)
            ->rememberState();
    }

    /**
     * Menu nativo do app com atalhos de teclado.
     *
     * - Navegação direta às telas principais (CmdOrCtrl+1..4).
     * - "Focar em Gerar" (CmdOrCtrl+G) dispara o evento `focus-gerar`, capturado
     *   no front (resources/js/app.js) para focar o formulário de geração.
     * - O menu View nativo já fornece "Reload" em CmdOrCtrl+R (refresh).
     */
    protected function registrarMenu(): void
    {
        Menu::create(
            Menu::app(),
            Menu::make(
                Menu::route('biblioteca', 'Biblioteca', 'CmdOrCtrl+1'),
                Menu::route('trilha', 'Trilha', 'CmdOrCtrl+2'),
                Menu::route('chat', 'Chat', 'CmdOrCtrl+3'),
                Menu::route('metas', 'Metas', 'CmdOrCtrl+4'),
                Menu::separator(),
                Menu::label('Focar em Gerar', 'CmdOrCtrl+G')->event('focus-gerar'),
            )->label('Navegar'),
            Menu::view(),
            Menu::window(),
            Menu::help(),
        );
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
