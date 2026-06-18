<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeracaoResource\Pages;
use App\Models\Geracao;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GeracaoResource extends Resource
{
    protected static ?string $model = Geracao::class;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function getModelLabel(): string
    {
        return 'Geração';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Gerações';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Observabilidade';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->label('ID'),
                TextColumn::make('tipo')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'simulado' => 'warning',
                        'resumo' => 'info',
                        'flashcards' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state === 'ok' ? 'success' : 'danger'),
                TextColumn::make('modelo')->label('Modelo')->toggleable(),
                TextColumn::make('custo_tokens')->label('Tokens')->numeric()->sortable(),
                TextColumn::make('regeneracoes')->label('Regenerações')->numeric(),
                TextColumn::make('escopo.disciplina')->label('Disciplina'),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo')->options([
                    'simulado' => 'Simulado',
                    'resumo' => 'Resumo',
                    'flashcards' => 'Flashcards',
                ]),
                SelectFilter::make('status')->options([
                    'ok' => 'Aprovado',
                    'rejeitado' => 'Rejeitado',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeracoes::route('/'),
        ];
    }
}
