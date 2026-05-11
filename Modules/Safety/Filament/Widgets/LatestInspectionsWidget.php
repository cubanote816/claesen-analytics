<?php

declare(strict_types=1);

namespace Modules\Safety\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Inspection;

class LatestInspectionsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'Recente Werkplekinspecties';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inspection::query()
                    ->with(['user', 'checklist'])
                    ->latest('completed_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('project_id')
                    ->label('Project')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Inspecteur')
                    ->sortable(),

                Tables\Columns\TextColumn::make('checklist.name')
                    ->label('Checklist')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Datum')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),

                Tables\Columns\IconColumn::make('pdf_generated')
                    ->label('PDF')
                    ->boolean()
                    ->getStateUsing(fn (Inspection $record): bool => !empty($record->pdf_path)),
            ])
            ->actions([
                Tables\Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Inspection $record): ?string =>
                        $record->pdf_path ? Storage::disk('public')->url($record->pdf_path) : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (Inspection $record): bool => !empty($record->pdf_path)),
            ])
            ->paginated(false);
    }
}
