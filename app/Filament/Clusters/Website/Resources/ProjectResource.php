<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\Resources\ProjectResource\Pages;
use Modules\Website\Models\Project;
use Modules\Website\App\Enums\ProjectCategory;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class ProjectResource extends Resource
{


    protected static ?string $model = Project::class;

    public static function getNavigationLabel(): string
    {
        return __('website.projects.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('website.projects.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('website.projects.plural_label');
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('website.projects.sections.details'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('website.projects.fields.title'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(string $operation, $state, Set $set) => ($operation === 'create' || $operation === 'edit') ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->label(__('website.projects.fields.slug'))
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(Project::class, 'slug', ignoreRecord: true),
                        Select::make('category')
                            ->label(__('website.projects.fields.category'))
                            ->options(ProjectCategory::class)
                            ->required(),
                        TextInput::make('client')
                            ->label(__('website.projects.fields.client')),
                        TextInput::make('location')
                            ->label(__('website.projects.fields.location')),
                        TextInput::make('year')
                            ->label(__('website.projects.fields.year'))
                            ->numeric(),
                        RichEditor::make('description')
                            ->label(__('website.projects.fields.description'))
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make(__('website.projects.sections.media'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('featured_image')
                            ->label(__('website.projects.fields.featured_image'))
                            ->collection('featured_image')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('200')
                            ->multiple() // Kept for compatibility with Spatie Media Library
                            ->maxFiles(1)
                            ->maxSize(20480),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label(__('website.projects.fields.gallery'))
                            ->collection('gallery')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('150')
                            ->panelLayout('grid')
                            ->multiple()
                            ->reorderable()
                            ->maxSize(20480),
                    ])->collapsible(),

                Section::make(__('website.projects.sections.settings'))
                    ->schema([
                        Toggle::make('published')
                            ->label(__('website.projects.fields.published')),
                        Toggle::make('featured')
                            ->label(__('website.projects.fields.featured')),
                        TextInput::make('order_index')
                            ->label(__('website.projects.fields.order_index'))
                            ->numeric()
                            ->default(0),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label(__('website.projects.fields.featured_image'))
                    ->collection('featured_image')
                    ->height(60),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('website.projects.fields.title'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('website.projects.fields.category'))
                    ->badge(),
                Tables\Columns\IconColumn::make('published')
                    ->label(__('website.projects.fields.published'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('featured')
                    ->label(__('website.projects.fields.featured'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('year')
                    ->label(__('website.projects.fields.year'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('website.activities.fields.date'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('website.projects.fields.category'))
                    ->options(ProjectCategory::class),
                Tables\Filters\TernaryFilter::make('published')
                    ->label(__('website.projects.fields.published')),
                Tables\Filters\TernaryFilter::make('featured')
                    ->label(__('website.projects.fields.featured')),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('order_index')
            ->defaultSort('order_index');
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
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
