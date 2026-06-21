<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TemaResource\Pages;
use App\Models\Disciplina;
use App\Models\Tema;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TemaResource extends Resource
{
    protected static ?string $model = Tema::class;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-tag';
    }

    public static function getModelLabel(): string
    {
        return 'Tema';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Temas';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Conteúdo';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            CheckboxList::make('disciplinas')
                ->relationship('disciplinas', 'nome')
                ->options(Disciplina::orderBy('nome')->pluck('nome', 'id'))
                ->label('Disciplinas do Tema')
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->label('ID'),
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('slug'),
                TextColumn::make('disciplinas_count')
                    ->counts('disciplinas')
                    ->label('Disciplinas'),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemas::route('/'),
            'create' => Pages\CreateTema::route('/create'),
            'edit' => Pages\EditTema::route('/{record}/edit'),
        ];
    }
}
