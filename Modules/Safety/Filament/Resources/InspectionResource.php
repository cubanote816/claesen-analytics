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
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Modules\Safety\Filament\Resources\InspectionResource\Pages;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Inspection;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';
    public static function getNavigationLabel(): string
    {
        return __('safety::inspections.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('safety::inspections.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('safety::inspections.plural_model_label');
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.safety_vca');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'inspection' => 'Site Inspection',
                        'incident' => 'Incident Report',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inspection' => 'success',
                        'incident' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('project_id')
                    ->label(__('safety::inspections.columns.project_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('safety::inspections.columns.inspector'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('checklist.name')
                    ->label(__('safety::inspections.columns.checklist'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('safety::inspections.columns.date'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('has_pdf')
                    ->label('PDF')
                    ->badge()
                    ->state(fn(Inspection $record): string => $record->pdf_path ? 'Gegenereerd' : 'Niet gegenereerd')
                    ->color(fn(string $state): string => $state === 'Gegenereerd' ? 'success' : 'gray'),
            ])
            ->filters([
                Filter::make('has_nok')
                    ->label(__('safety::inspections.filters.has_nok'))
                    ->query(fn(Builder $query) => $query->whereHas('answers', fn($q) => $q->where('status', 'nok'))),

                Filter::make('from')
                    ->form([
                        DatePicker::make('from')
                            ->label(__('safety::inspections.filters.from'))
                            ->displayFormat('d-m-Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query->when($data['from'], fn($q, $date) => $q->whereDate('completed_at', '>=', $date)))
                    ->indicateUsing(fn(array $data) => $data['from'] ? __('safety::inspections.filters.from') . ': ' . $data['from'] : null),

                Filter::make('until')
                    ->form([
                        DatePicker::make('until')
                            ->label(__('safety::inspections.filters.until'))
                            ->displayFormat('d-m-Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query->when($data['until'], fn($q, $date) => $q->whereDate('completed_at', '<=', $date)))
                    ->indicateUsing(fn(array $data) => $data['until'] ? __('safety::inspections.filters.until') . ': ' . $data['until'] : null),
            ])
            ->filtersFormColumns(3)
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('download_pdf')
                        ->label(__('safety::inspections.actions.download_pdf'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->url(fn (Inspection $record): ?string => $record->pdf_path ? route('safety.admin.pdf', $record) : null)
                        ->openUrlInNewTab()
                        ->visible(fn(Inspection $record): bool => !empty($record->pdf_path)),
                    Action::make('regenerate_pdf')
                        ->label(__('safety::inspections.actions.regenerate_pdf'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function (Inspection $record) {
                            try {
                                \Modules\Safety\Jobs\GenerateSafetyPdfJob::dispatchSync($record->id, auth()->id());

                                Notification::make()
                                    ->title(__('safety::inspections.actions.regenerate_success'))
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error bij genereren PDF')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                \Illuminate\Support\Facades\Log::error("PDF Generation failed for inspection {$record->id}: " . $e->getMessage());
                            }
                        }),
                ])->button()->label('Acties'),
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
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'inspection' => 'Site Inspection',
                                'incident' => 'Incident Report',
                                default => $state,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'inspection' => 'success',
                                'incident' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('project_id')->label('Project ID'),
                        TextEntry::make('user.name')->label('Inspecteur / Melder'),
                        TextEntry::make('incidentWorker.name')
                            ->label('Betrokken Medewerker')
                            ->visible(fn ($record) => $record->type === 'incident' && $record->incident_worker_id),
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
                                    ->color(fn(string $state): string => match ($state) {
                                        'ok' => 'success',
                                        'nok' => 'danger',
                                        'na' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
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
                                    ->getStateUsing(fn (Answer $record): ?string =>
                                        $record->photo_path ? route('safety.admin.photo', $record) : null)
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
