<?php

namespace Modules\Safety\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Filament\Resources\InspectionResource\Pages;
use Modules\Safety\Models\Inspection;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Inspecties';
    protected static ?string $modelLabel = 'Inspectie';
    protected static ?string $pluralModelLabel = 'Inspecties';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.safety_vca');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project_id')
                    ->label('Project ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Inspecteur')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('checklist.name')
                    ->label('Checklist')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Datum')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_pdf')
                    ->label('PDF')
                    ->boolean()
                    ->getStateUsing(fn (Inspection $record): bool => !empty($record->pdf_path)),
            ])
            ->filters([
                Filter::make('completed_at')
                    ->form([
                        DatePicker::make('from')
                            ->label('Van')
                            ->displayFormat('d-m-Y'),
                        DatePicker::make('until')
                            ->label('Tot')
                            ->displayFormat('d-m-Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('completed_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('completed_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) $indicators[] = 'Van: ' . $data['from'];
                        if ($data['until'] ?? null) $indicators[] = 'Tot: ' . $data['until'];
                        return $indicators;
                    }),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make(),
                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Inspection $record): ?string => $record->pdf_path ? Storage::disk('public')->url($record->pdf_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Inspection $record): bool => !empty($record->pdf_path)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('completed_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Inspectie Details')
                    ->schema([
                        TextEntry::make('project_id')->label('Project ID'),
                        TextEntry::make('user.name')->label('Inspecteur'),
                        TextEntry::make('checklist.name')->label('Checklist'),
                        TextEntry::make('completed_at')
                            ->label('Voltooid op')
                            ->dateTime('d-m-Y H:i:s'),
                    ])->columns(4),

                Section::make('Antwoorden')
                    ->schema([
                        RepeatableEntry::make('answers')
                            ->label('')
                            ->schema([
                                TextEntry::make('question.text_nl')
                                    ->label('Vraag')
                                    ->columnSpan(2),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'ok' => 'success',
                                        'nok' => 'danger',
                                        'na' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'ok' => 'Akkoord (OK)',
                                        'nok' => 'Niet Akkoord (NOK)',
                                        'na' => 'N/A',
                                        default => $state,
                                    }),
                                TextEntry::make('remark')
                                    ->label('Opmerking')
                                    ->default('-'),
                                ImageEntry::make('photo_path')
                                    ->label('Foto')
                                    ->disk('public')
                                    ->visibility('private')
                                    ->hidden(fn ($state) => empty($state)),
                            ])->columns(5),
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
            'index' => Pages\ListInspections::route('/'),
            'view' => Pages\ViewInspection::route('/{record}'),
        ];
    }
}
