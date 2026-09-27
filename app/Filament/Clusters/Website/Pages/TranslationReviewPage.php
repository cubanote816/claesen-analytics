<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\WebsiteCluster;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Models\Site;
use Modules\Intelligence\Jobs\TranslateModelAttributesJob;
use Modules\Intelligence\Models\TranslationState;
use Modules\Intelligence\Services\TranslationStateService;

/**
 * CLA-611 (gap G8): the human translation review screen — origin side info
 * plus one row per (model, attribute, locale), with the three legitimate
 * human actions: EDIT the translation value, APPROVE it (reviewed), PUBLISH
 * it (published), and RETRANSLATE (explicit override that queues the AI).
 *
 * Status is never hand-editable as a field: every transition here goes
 * through a deliberate action and is audited to the activity log — the
 * public "approved in locale" rule stays DERIVED
 * (TranslationStateService::entityStatusForLocale), never declared.
 *
 * Scoped to the panel's own site (CLA-598 pattern, fail-closed): rows with
 * site_id = null (FieldOps engine bookkeeping) appear on neither panel's
 * Website review screen.
 */
class TranslationReviewPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $slug = 'translation-review';

    protected string $view = 'website::filament.pages.translation-review';

    public static function canAccess(): bool
    {
        return parent::canAccess() && Site::forPanel() !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('website.translation_review.label');
    }

    public function getTitle(): string
    {
        return __('website.translation_review.label');
    }

    public function table(Table $table): Table
    {
        $siteId = Site::forPanel()?->id;

        return $table
            ->query(
                TranslationState::query()
                    ->when($siteId !== null, fn ($query) => $query->where('site_id', $siteId))
                    ->with('translatable')
                    ->orderBy('updated_at', 'desc')
            )
            ->columns([
                TextColumn::make('translatable_label')
                    ->label(__('website.translation_review.fields.subject'))
                    ->getStateUsing(fn (TranslationState $record): string => class_basename($record->translatable_type).' #'.$record->translatable_id),
                TextColumn::make('attribute')
                    ->label(__('website.translation_review.fields.attribute'))
                    ->badge(),
                TextColumn::make('locale')
                    ->label(__('website.translation_review.fields.locale'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('website.translation_review.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        TranslationState::STATUS_PUBLISHED => 'success',
                        TranslationState::STATUS_REVIEWED => 'info',
                        TranslationState::STATUS_FAILED => 'danger',
                        TranslationState::STATUS_STALE => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('error')
                    ->label(__('website.translation_review.fields.error'))
                    ->limit(50)
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label(__('website.translation_review.fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('website.translation_review.fields.status'))
                    ->options([
                        TranslationState::STATUS_MISSING => 'missing',
                        TranslationState::STATUS_MACHINE => 'machine',
                        TranslationState::STATUS_STALE => 'stale',
                        TranslationState::STATUS_NEEDS_REVIEW => 'needs_review',
                        TranslationState::STATUS_REVIEWED => 'reviewed',
                        TranslationState::STATUS_PUBLISHED => 'published',
                        TranslationState::STATUS_FAILED => 'failed',
                    ]),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('website.translation_review.actions.edit'))
                    ->icon('heroicon-m-pencil-square')
                    ->form(fn (TranslationState $record): array => [
                        Textarea::make('value')
                            ->label(__('website.translation_review.actions.edit_value', ['locale' => $record->locale]))
                            ->required()
                            ->default(fn (): ?string => $this->currentTranslation($record))
                            ->rows(4),
                    ])
                    ->action(function (array $data, TranslationState $record): void {
                        $this->applyEdit($record, (string) $data['value']);
                    }),
                Action::make('approve')
                    ->label(__('website.translation_review.actions.approve'))
                    ->icon('heroicon-m-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (TranslationState $record): bool => $record->status !== TranslationState::STATUS_REVIEWED
                        && $record->status !== TranslationState::STATUS_PUBLISHED)
                    ->action(fn (TranslationState $record) => $this->applyTransition($record, TranslationState::STATUS_REVIEWED)),
                Action::make('publish')
                    ->label(__('website.translation_review.actions.publish'))
                    ->icon('heroicon-m-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TranslationState $record): bool => $record->status === TranslationState::STATUS_REVIEWED)
                    ->action(fn (TranslationState $record) => $this->applyTransition($record, TranslationState::STATUS_PUBLISHED)),
                Action::make('retranslate')
                    ->label(__('website.translation_review.actions.retranslate'))
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('website.translation_review.actions.retranslate_confirm'))
                    ->visible(fn (TranslationState $record): bool => filled($record->translatable))
                    ->action(fn (TranslationState $record) => $this->applyRetranslate($record)),
            ]);
    }

    /**
     * The current human-facing value of the translation this row tracks.
     */
    private function currentTranslation(TranslationState $record): ?string
    {
        $model = $record->translatable;

        if ($model === null || ! method_exists($model, 'getTranslation')) {
            return null;
        }

        return $model->getTranslation($record->attribute, $record->locale, false);
    }

    /**
     * EDIT: a human-typed value replaces the machine value. Recorded as
     * `machine` (unapproved) — approving is always a separate, deliberate
     * action (G5 discipline: authoring and approval never collapse into
     * one implicit step).
     */
    private function applyEdit(TranslationState $record, string $value): void
    {
        $model = $record->translatable()->withoutGlobalScope('site')->first();

        if ($model === null || ! method_exists($model, 'setTranslation')) {
            return;
        }

        $model->setTranslation($record->attribute, $record->locale, $value);
        $model->saveQuietly();

        app(TranslationStateService::class)->recordState(
            $model,
            $record->attribute,
            $record->locale,
            TranslationState::STATUS_MACHINE,
            $record->source_locale,
        );

        $this->audit($record, $record->status, TranslationState::STATUS_MACHINE, 'edited');
    }

    /**
     * APPROVE / PUBLISH — the only two paths to an approved status.
     */
    private function applyTransition(TranslationState $record, string $to): void
    {
        $model = $record->translatable()->withoutGlobalScope('site')->first();

        if ($model === null) {
            return; // the tracked entity was deleted — nothing left to review
        }

        $from = $record->status;

        app(TranslationStateService::class)->recordState(
            $model,
            $record->attribute,
            $record->locale,
            $to,
        );

        $this->audit($record, $from, $to, $to === TranslationState::STATUS_PUBLISHED ? 'published' : 'approved');
    }

    /**
     * RETRANSLATE — the explicit override that lets the AI replace even an
     * approved value (G5: only this deliberate human action may do so).
     * Marks the row stale and requeues the job; the result lands as
     * `machine` (never published) and needs approval again.
     */
    private function applyRetranslate(TranslationState $record): void
    {
        $model = $record->translatable()->withoutGlobalScope('site')->first();

        if ($model === null) {
            return;
        }

        app(TranslationStateService::class)->recordState(
            $model,
            $record->attribute,
            $record->locale,
            TranslationState::STATUS_STALE,
            $record->source_locale,
        );

        TranslateModelAttributesJob::dispatch(
            get_class($model),
            $model->getKey(),
            method_exists($model, 'getAiTranslatableAttributes')
                ? $model->getAiTranslatableAttributes()
                : [],
            app()->getLocale(),
        );

        $this->audit($record, $record->status, TranslationState::STATUS_STALE, 'retranslate_queued');
    }

    private function audit(TranslationState $record, string $from, string $to, string $event): void
    {
        \Spatie\Activitylog\Facades\Activity::causedBy(auth()->user())
            ->performedOn($record)
            ->withProperties([
                'translatable' => $record->translatable_type.'#'.$record->translatable_id,
                'attribute' => $record->attribute,
                'locale' => $record->locale,
                'from' => $from,
                'to' => $to,
                'event' => $event,
            ])
            ->log('translation_review');

        Notification::make()
            ->title(__('website.translation_review.notifications.updated'))
            ->success()
            ->send();
    }
}
