<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\RelationManagers;
use Modules\Website\Models\ConsultationRequest;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components as Infolists;
use Illuminate\Database\Eloquent\Builder;
use Modules\Website\Services\RetentionService;

class ConsultationRequestResource extends Resource
{
    protected static ?string $model = ConsultationRequest::class;

    public static function getNavigationLabel(): string
    {
        return __('website.consultation_requests.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('website.consultation_requests.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('website.consultation_requests.plural_label');
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?int $navigationSort = 3;

    /**
     * F4/CLA-477 — the lead-inbox workflow status set (single source of
     * truth for the form Select, the table column/filter, so the three
     * never drift). See Modules\Website\Models\ConsultationRequest's
     * STATUS_* constants and the 2026_09_20_120100 migration for the
     * historical value mapping.
     */
    protected static function statusOptions(): array
    {
        return [
            ConsultationRequest::STATUS_NEW => __('website.consultation_requests.status_options.new'),
            ConsultationRequest::STATUS_ASSIGNED => __('website.consultation_requests.status_options.assigned'),
            ConsultationRequest::STATUS_IN_PROGRESS => __('website.consultation_requests.status_options.in_progress'),
            ConsultationRequest::STATUS_WAITING_CLIENT => __('website.consultation_requests.status_options.waiting_client'),
            ConsultationRequest::STATUS_CLOSED => __('website.consultation_requests.status_options.closed'),
            ConsultationRequest::STATUS_SPAM => __('website.consultation_requests.status_options.spam'),
        ];
    }

    protected static function statusColor(string $state): string
    {
        return match ($state) {
            ConsultationRequest::STATUS_NEW => 'gray',
            ConsultationRequest::STATUS_ASSIGNED => 'info',
            ConsultationRequest::STATUS_IN_PROGRESS => 'warning',
            ConsultationRequest::STATUS_WAITING_CLIENT => 'warning',
            ConsultationRequest::STATUS_CLOSED => 'success',
            ConsultationRequest::STATUS_SPAM => 'danger',
            default => 'gray',
        };
    }

    /**
     * F4/CLA-477 — "responsable debe pertenecer a la misma empresa": scopes
     * the option list to the record's own site's organization once
     * enforcement is on (D4). Inert (every user) with the flag off, the
     * default in every environment today — zero change for Claesen.
     * ConsultationRequestObserver::saving() is the server-side backstop
     * (this only narrows the UI's own options, not a validated request).
     */
    protected static function assignableUsersQuery(?ConsultationRequest $record, Builder $query): Builder
    {
        if (! config('organizations.enforce') || ! $record?->site) {
            return $query;
        }

        return $query->where('organization_id', $record->site->organization_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Group::make()
                    ->schema([
                        Section::make(__('website.consultation_requests.sections.contact'))
                            ->schema([
                                // F4/CLA-476: "procedimiento para... corrección" — these
                                // used to be ->disabledOn('edit'), meaning a GDPR
                                // rectification request (e.g. a typo'd email) had no
                                // way to be actioned from the panel at all. Editable now;
                                // still required so a correction can't blank them out.
                                TextInput::make('name')
                                    ->label(__('website.consultation_requests.fields.name'))
                                    ->required(),
                                TextInput::make('email')
                                    ->label(__('website.consultation_requests.fields.email'))
                                    ->email()
                                    ->required(),
                                TextInput::make('phone')
                                    ->label(__('website.consultation_requests.fields.phone')),
                                TextInput::make('company')
                                    ->label(__('website.consultation_requests.fields.company')),
                            ])->columns(2),

                        Section::make(__('website.consultation_requests.sections.details'))
                            ->schema([
                                Select::make('type')
                                    ->label(__('website.consultation_requests.fields.type'))
                                    ->options([
                                        'consultation' => __('website.consultation_requests.types.consultation'),
                                        'quote' => __('website.consultation_requests.types.quote'),
                                        'project' => __('website.consultation_requests.types.project'),
                                    ])->required()->disabledOn('edit'),
                                Select::make('project_type')
                                    ->label(__('website.consultation_requests.fields.project_type'))
                                    ->options([
                                        'sport' => __('website.consultation_requests.project_types.sport'),
                                        'industrial' => __('website.consultation_requests.project_types.industrial'),
                                        'public' => __('website.consultation_requests.project_types.public'),
                                        'masts' => __('website.consultation_requests.project_types.masts'),
                                        'other' => __('website.consultation_requests.project_types.other'),
                                    ])->disabledOn('edit'),
                                Textarea::make('message')
                                    ->label(__('website.consultation_requests.fields.message'))
                                    ->columnSpanFull()->disabledOn('edit'),
                            ])->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()
                    ->schema([
                        Section::make(__('website.consultation_requests.sections.management'))
                            ->schema([
                                Select::make('status')
                                    ->label(__('website.consultation_requests.fields.status'))
                                    ->options(self::statusOptions())
                                    ->required(),
                                Select::make('priority')
                                    ->label(__('website.consultation_requests.fields.priority'))
                                    ->options([
                                        'low' => __('website.consultation_requests.priority_options.low'),
                                        'medium' => __('website.consultation_requests.priority_options.medium'),
                                        'high' => __('website.consultation_requests.priority_options.high'),
                                        'urgent' => __('website.consultation_requests.priority_options.urgent'),
                                    ]),
                                Select::make('assigned_to')
                                    ->label(__('website.consultation_requests.fields.assigned_to'))
                                    ->relationship(
                                        'assignedUser',
                                        'name',
                                        fn (Builder $query, ?ConsultationRequest $record) => self::assignableUsersQuery($record, $query)
                                    ),
                                DatePicker::make('follow_up_date')
                                    ->label(__('website.consultation_requests.fields.follow_up_date')),
                                TagsInput::make('tags')
                                    ->label(__('website.consultation_requests.fields.tags')),
                                Textarea::make('internal_notes')
                                    ->label(__('website.consultation_requests.fields.internal_notes')),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('website.consultation_requests.fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('website.consultation_requests.fields.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('website.consultation_requests.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.types.{$state}")),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('website.consultation_requests.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.status_options.{$state}")),
                Tables\Columns\TextColumn::make('priority')
                    ->label(__('website.consultation_requests.fields.priority'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.priority_options.{$state}")),
                Tables\Columns\TextColumn::make('tags')
                    ->label(__('website.consultation_requests.fields.tags'))
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('first_response_sla')
                    ->label(__('website.consultation_requests.fields.first_response_sla'))
                    ->state(fn (ConsultationRequest $record): ?int => $record->firstResponseSlaMinutes())
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.' min')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('first_response_at', $direction)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('website.activities.fields.date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('website.consultation_requests.fields.status'))
                    ->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('website.consultation_requests.fields.type')),
            ])
            ->headerActions([
                // F4/CLA-476: "exportaciones limitadas por rol, tenant y
                // propósito; quedan auditadas" — role via the permission
                // check, tenant via the resource's own Eloquent query
                // (BelongsToSite, already scoped), purpose via a single
                // purpose-built lead-reporting CSV (not a generic dump),
                // audited via a dedicated activity_log entry per export.
                // F2/CLA-464: "reautenticación para... exportación" — a
                // fresh MFA challenge on top of the permission check above.
                \Filament\Actions\Action::make('export')
                    ->label(__('website.consultation_requests.actions.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('website.export-consultation-requests') ?? false)
                    ->mountUsing(function (): void {
                        if ($user = auth()->user()) {
                            app(\Modules\Core\Services\StepUpAuthenticator::class)->sendChallengeIfNeeded($user);
                        }
                    })
                    ->schema(fn (): array => app(\Modules\Core\Services\StepUpAuthenticator::class)->guardedSchema(auth()->user(), []))
                    ->action(fn () => self::streamCsvExport()),
            ])
            ->actions([
                // \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                // F4/CLA-476: "procedimiento para... eliminación" — on-demand
                // GDPR erasure, any lead age, independent of the automated
                // retention job's cutoffs. Hidden once already anonymized —
                // nothing left to erase a second time.
                // F2/CLA-464: "reautenticación para... acciones
                // destructivas" — a fresh MFA challenge before an
                // irreversible GDPR erasure.
                \Filament\Actions\Action::make('erase')
                    ->label(__('website.consultation_requests.actions.erase'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ConsultationRequest $record): bool => $record->anonymized_at === null
                        && (auth()->user()?->can('website.erase-consultation-pii') ?? false))
                    ->mountUsing(function (): void {
                        if ($user = auth()->user()) {
                            app(\Modules\Core\Services\StepUpAuthenticator::class)->sendChallengeIfNeeded($user);
                        }
                    })
                    ->schema(fn (): array => app(\Modules\Core\Services\StepUpAuthenticator::class)->guardedSchema(auth()->user(), []))
                    ->action(function (ConsultationRequest $record): void {
                        app(RetentionService::class)->eraseNow($record, auth()->id());
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    // \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * F4/CLA-476: plain CSV, not Filament's native export subsystem
     * (ExportAction/Exporter — never adopted in this codebase, would need
     * new vendor migrations/queued jobs for a single, modest-volume
     * resource) — a synchronous streamed download is simpler and
     * sufficient here. Each text field is passed through
     * sanitizeCsvValue() — CSV formula injection is a real risk for any
     * export of free-text fields a public form visitor controls (name/
     * company are both attacker-controlled input), per Filament\Actions\
     * Exports\Exporter's own documented warning about this exact class of
     * vulnerability.
     */
    private static function streamCsvExport(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $records = ConsultationRequest::query()->with('assignedUser')->latest()->get();
        $actor = auth()->user();

        activity()
            ->causedBy($actor)
            ->log("Exported {$records->count()} consultation request(s) to CSV");

        // F2/CLA-465: "alertas por exportaciones" — see
        // Modules\Website\Notifications\ConsultationExportPerformedNotification's
        // own docblock for why this criterion stopped being vacuous.
        if ($actor) {
            $roleIds = \Spatie\Permission\Models\Role::whereIn('name', ['super_admin', 'admin'])->pluck('id');
            $recipients = \Modules\Core\Models\User::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('id', $roleIds))
                ->inOrganization($actor->organization_id)
                ->get();

            \Illuminate\Support\Facades\Notification::send(
                $recipients,
                new \Modules\Website\Notifications\ConsultationExportPerformedNotification($actor, $records->count())
            );
        }

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Type', 'Project type', 'Status', 'Priority', 'Assigned to', 'Created at']);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->id,
                    self::sanitizeCsvValue($record->name),
                    self::sanitizeCsvValue($record->email),
                    self::sanitizeCsvValue($record->phone),
                    self::sanitizeCsvValue($record->company),
                    $record->type,
                    $record->project_type,
                    $record->status,
                    $record->priority,
                    self::sanitizeCsvValue($record->assignedUser?->name),
                    $record->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'consultation-requests-'.now()->format('Y-m-d-His').'.csv');
    }

    private static function sanitizeCsvValue(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Section::make(__('website.consultation_requests.sections.contact'))
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label(__('website.consultation_requests.fields.name')),
                        \Filament\Infolists\Components\TextEntry::make('email')
                            ->label(__('website.consultation_requests.fields.email')),
                        \Filament\Infolists\Components\TextEntry::make('phone')
                            ->label(__('website.consultation_requests.fields.phone')),
                        \Filament\Infolists\Components\TextEntry::make('company')
                            ->label(__('website.consultation_requests.fields.company')),
                    ])->columns(2),
                Infolists\Section::make(__('website.consultation_requests.sections.message'))
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('type')
                            ->label(__('website.consultation_requests.fields.type'))
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.types.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('project_type')
                            ->label(__('website.consultation_requests.fields.project_type'))
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.project_types.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('message')
                            ->label(__('website.consultation_requests.fields.message'))
                            ->columnSpanFull(),
                    ])->columns(2),
                Infolists\Section::make(__('website.consultation_requests.sections.status'))
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label(__('website.consultation_requests.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.status_options.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('priority')
                            ->label(__('website.consultation_requests.fields.priority'))
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.priority_options.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('assignedUser.name')
                            ->label(__('website.consultation_requests.fields.assigned_to')),
                        \Filament\Infolists\Components\TextEntry::make('tags')
                            ->label(__('website.consultation_requests.fields.tags'))
                            ->badge()
                            ->separator(','),
                        \Filament\Infolists\Components\TextEntry::make('first_response_sla')
                            ->label(__('website.consultation_requests.fields.first_response_sla'))
                            ->state(fn (ConsultationRequest $record): ?int => $record->firstResponseSlaMinutes())
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.' min'),
                    ])->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListConsultationRequests::route('/'),
            'create' => Pages\CreateConsultationRequest::route('/create'),
            'view'   => Pages\ViewConsultationRequest::route('/{record}'),
            'edit'   => Pages\EditConsultationRequest::route('/{record}/edit'),
        ];
    }
}
