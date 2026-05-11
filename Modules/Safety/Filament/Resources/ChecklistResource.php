<?php

namespace Modules\Safety\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Modules\Safety\Filament\Resources\ChecklistResource\Pages;
use Modules\Safety\Models\Checklist;

class ChecklistResource extends Resource
{
    protected static ?string $model = Checklist::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    public static function getNavigationLabel(): string
    {
        return __('safety::checklists.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('safety::checklists.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('safety::checklists.plural_model_label');
    }

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.safety_vca');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('safety::checklists.sections.general'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('safety::checklists.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label(__('safety::checklists.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('safety::checklists.sections.questions'))
                    ->headerActions([
                        \Filament\Actions\Action::make('add_question_header')
                            ->label('Nieuwe Vraag Toevoegen')
                            ->icon('heroicon-o-plus')
                            ->color('primary')
                            ->action(function ($set, $get) {
                                $questions = $get('questions') ?? [];
                                $questions[] = [
                                    'text_nl' => '',
                                    'order' => count($questions),
                                ];
                                $set('questions', $questions);
                            }),
                    ])
                    ->schema([
                        Repeater::make('questions')
                            ->relationship()
                            ->label(__('safety::checklists.plural_model_label'))
                            ->schema([
                                Textarea::make('text_nl')
                                    ->label('Vraag (Nederlands)')
                                    ->required()
                                    ->rows(2)
                                    ->columnSpanFull(),
                                TextInput::make('order')
                                    ->label('Volgorde')
                                    ->numeric()
                                    ->default(0)
                                    ->hidden(),
                            ])
                            ->orderColumn('order')
                            ->addActionLabel('Nieuwe Vraag Toevoegen')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['text_nl'] ?? null)
                            ->grid(3) // Gallery format
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
                Tables\Columns\TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Aantal Vragen'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aangemaakt op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Laatst gewijzigd')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklists::route('/'),
            'create' => Pages\CreateChecklist::route('/create'),
            'edit' => Pages\EditChecklist::route('/{record}/edit'),
        ];
    }
}
