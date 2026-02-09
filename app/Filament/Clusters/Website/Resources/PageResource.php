<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\Resources\PageResource\Pages;
use Modules\Website\Models\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class PageResource extends Resource
{
    use Translatable;

    protected static ?string $model = Page::class;

    public static function getNavigationLabel(): string
    {
        return __('website.pages.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('website.pages.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('website.pages.plural_label');
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('website.pages.sections.content'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('website.pages.fields.title'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->label(__('website.pages.fields.slug'))
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(Page::class, 'slug', ignoreRecord: true),
                        RichEditor::make('content')
                            ->label(__('website.pages.fields.content'))
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make(__('website.pages.sections.seo'))
                    ->schema([
                        KeyValue::make('meta_description')
                            ->label(__('website.pages.fields.meta_description')),
                        KeyValue::make('meta_keywords')
                            ->label(__('website.pages.fields.meta_keywords')),
                    ])->collapsed(),

                Section::make(__('website.pages.sections.settings'))
                    ->schema([
                        Toggle::make('published')
                            ->label(__('website.pages.fields.published')),
                        DateTimePicker::make('published_at')
                            ->label(__('website.pages.fields.published_at')),
                        TextInput::make('order_index')
                            ->label(__('website.pages.fields.order_index'))
                            ->numeric()
                            ->default(0),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('website.pages.fields.title'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('website.pages.fields.slug'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('published')
                    ->label(__('website.pages.fields.published'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label(__('website.pages.fields.published_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('website.activities.fields.date'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
