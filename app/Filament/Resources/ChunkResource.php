<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChunkResource\Pages;
use App\Models\Chunk;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChunkResource extends Resource
{
    protected static ?string $model = Chunk::class;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-puzzle-piece';
    }

    public static function getModelLabel(): string
    {
        return 'Chunk';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Chunks';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Observabilidade';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('pagina.titulo')->label('Página')->searchable()->limit(40),
                TextColumn::make('pagina.disciplina.nome')->label('Disciplina'),
                TextColumn::make('heading_path')->label('Heading')->limit(40)->toggleable(),
                TextColumn::make('conteudo')->label('Conteúdo')->limit(80)->wrap(),
                TextColumn::make('tokens')->label('Tokens')->numeric()->sortable(),
                TextColumn::make('ordem')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('pagina.disciplina')
                    ->relationship('pagina.disciplina', 'nome')
                    ->label('Disciplina'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChunks::route('/'),
        ];
    }
}
