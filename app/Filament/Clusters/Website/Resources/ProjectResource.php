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

use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class ProjectResource extends Resource
{
    use Translatable;

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
                Section::make('Project Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(Project::class, 'slug', ignoreRecord: true),
                        Select::make('category')
                            ->options(ProjectCategory::class)
                            ->required(),
                        TextInput::make('client'),
                        TextInput::make('location'),
                        TextInput::make('year')
                            ->numeric(),
                        RichEditor::make('description')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Media')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('featured_image')
                            ->collection('featured_image')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('200')
                            ->imageAspectRatio('16:9')
                            ->multiple()
                            ->maxFiles(1)
                            ->maxSize(20480),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('150')
                            ->panelLayout('grid')
                            ->multiple()
                            ->reorderable()
                            ->maxSize(20480),
                    ])->collapsible(),

                Section::make('Settings')
                    ->schema([
                        Toggle::make('published'),
                        Toggle::make('featured'),
                        TextInput::make('order_index')
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
                    ->collection('featured_image')
                    ->height(60),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge(),
                Tables\Columns\IconColumn::make('published')
                    ->boolean(),
                Tables\Columns\IconColumn::make('featured')
                    ->boolean(),
                Tables\Columns\TextColumn::make('year')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(ProjectCategory::class),
                Tables\Filters\TernaryFilter::make('published'),
                Tables\Filters\TernaryFilter::make('featured'),
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
