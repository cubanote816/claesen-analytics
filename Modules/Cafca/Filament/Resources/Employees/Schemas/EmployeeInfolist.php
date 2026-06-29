<?php

namespace Modules\Cafca\Filament\Resources\Employees\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Profile hero card ────────────────────────────────────────
                ViewEntry::make('profile_hero')
                    ->label(false)
                    ->view('filament.components.employee-profile-hero')
                    ->state(fn($record) => $record)
                    ->columnSpanFull(),

                // ── Talent snapshot strip ────────────────────────────────────
                ViewEntry::make('talent_snapshot')
                    ->label(false)
                    ->view('filament.components.employee-talent-snapshot')
                    ->state(fn($record) => $record)
                    ->columnSpanFull(),
            ]);
    }
}
