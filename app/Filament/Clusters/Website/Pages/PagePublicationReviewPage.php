<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Concerns\NamesValuesForHumans;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Models\Site;
use Modules\Website\Models\PagePublication;

/**
 * CLA-611 (gap G2/G8): the screen where a page × locale stops being a draft.
 *
 * `website_page_publications` had a reader (the public manifest) and no writer,
 * so the build could only ever be told what it already knew. This is the writer,
 * and it mirrors TranslationReviewPage on purpose: same cluster, same action
 * vocabulary, same audit — one place per kind of content, one way of approving.
 *
 * It differs from that screen in one deliberate way: there is **no edit** and no
 * retranslate, because the copy of these eight pages lives in Git under PR review
 * (decision D-F). The client marks state; the text never leaves the repository.
 * That is also why nothing here writes a translation value: this table holds the
 * decision, not the words.
 *
 * Every transition records **who** did it, in the row itself
 * (`reviewed_by_user_id` + `reviewed_at`) and in the activity log. The client who
 * approves is the responsible party automatically — that decision (2026-09-29) is
 * the reason those columns exist, and it is never assigned by hand.
 *
 * Scoped to the panel's own site (CLA-598 pattern, fail-closed): a row belonging
 * to another site is not merely hidden, it is never in the query.
 */
class PagePublicationReviewPage extends Page implements HasTable
{
    use InteractsWithTable;
    use NamesValuesForHumans;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $slug = 'page-publications';

    protected string $view = 'website::filament.pages.page-publication-review';

    public static function canAccess(): bool
    {
        return parent::canAccess() && Site::forPanel() !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('website.page_publication_review.label');
    }

    public function getTitle(): string
    {
        return __('website.page_publication_review.label');
    }

    public function table(Table $table): Table
    {
        $siteId = Site::forPanel()?->id;

        return $table
            ->query(
                PagePublication::query()
                    ->when($siteId !== null, fn ($query) => $query->where('site_id', $siteId))
                    ->orderBy('page')
                    ->orderBy('locale')
            )
            ->columns([
                TextColumn::make('page')
                    ->label(__('website.page_publication_review.fields.page'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::human('website.page_publication_review.pages', $state)),
                TextColumn::make('locale')
                    ->label(__('website.page_publication_review.fields.locale'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::human('website.page_publication_review.locales', $state)),
                TextColumn::make('status')
                    ->label(__('website.page_publication_review.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::human('website.page_publication_review.statuses', $state))
                    ->color(fn (string $state): string => match ($state) {
                        PagePublication::STATUS_PUBLISHED => 'success',
                        PagePublication::STATUS_REVIEWED => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('reviewed_at')
                    ->label(__('website.page_publication_review.fields.reviewed_at'))
                    ->placeholder('—')
                    // `->dateTime()` trae un formato fijo en inglés: en un panel en
                    // neerlandés la fecha salía «sep. 30, 2026». Se traduce con el
                    // idioma del panel, que es el que está leyendo quien aprueba.
                    ->formatStateUsing(fn ($state): ?string => $state?->locale(app()->getLocale())->translatedFormat('d M Y H:i'))
                    ->sortable(),
                TextColumn::make('reviewedBy.name')
                    ->label(__('website.page_publication_review.fields.reviewed_by'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('website.page_publication_review.fields.status'))
                    // Los mismos nombres que la columna: el filtro no debe hablar
                    // el vocabulario interno (`machine`) si la tabla ya no lo hace.
                    ->options([
                        PagePublication::STATUS_MACHINE => self::human('website.page_publication_review.statuses', PagePublication::STATUS_MACHINE),
                        PagePublication::STATUS_REVIEWED => self::human('website.page_publication_review.statuses', PagePublication::STATUS_REVIEWED),
                        PagePublication::STATUS_PUBLISHED => self::human('website.page_publication_review.statuses', PagePublication::STATUS_PUBLISHED),
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('website.page_publication_review.actions.approve'))
                    ->icon('heroicon-m-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (PagePublication $record): bool => $record->status !== PagePublication::STATUS_REVIEWED
                        && $record->status !== PagePublication::STATUS_PUBLISHED)
                    ->action(fn (PagePublication $record) => $this->applyTransition($record, PagePublication::STATUS_REVIEWED)),
                Action::make('publish')
                    ->label(__('website.page_publication_review.actions.publish'))
                    ->icon('heroicon-m-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('website.page_publication_review.actions.publish_confirm'))
                    // Approve and publish stay two deliberate acts, never one
                    // implicit step: publishing what nobody read is the exact
                    // mistake this table exists to prevent.
                    ->visible(fn (PagePublication $record): bool => $record->status === PagePublication::STATUS_REVIEWED)
                    ->action(fn (PagePublication $record) => $this->applyTransition($record, PagePublication::STATUS_PUBLISHED)),
                Action::make('retire')
                    ->label(__('website.page_publication_review.actions.retire'))
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('website.page_publication_review.actions.retire_confirm'))
                    ->visible(fn (PagePublication $record): bool => $record->status === PagePublication::STATUS_PUBLISHED)
                    ->action(fn (PagePublication $record) => $this->applyTransition($record, PagePublication::STATUS_REVIEWED)),
            ]);
    }

    /**
     * The activity logger for this screen.
     *
     * `activity('name')` and not `Activity::causedBy()`: in spatie/activitylog v5 the
     * log name is the helper's argument. Passing it to `log()` instead — as this
     * codebase does elsewhere — silently files every entry under "default", which
     * looks like an audit trail but cannot be filtered by event.
     */
    private function approval(): \Spatie\Activitylog\Support\ActivityLogger
    {
        return activity('page_publication_review');
    }

    /**
     * The only path that changes a row's status.
     *
     * Retiring goes back to `reviewed` and not to `machine` on purpose: the
     * effect the build sees is identical (only `published` is indexable), and
     * keeping the approval recorded is truer than pretending nobody ever read
     * the text. Who acted is stamped on every move, since publishing and
     * retiring are both decisions someone owns.
     */
    private function applyTransition(PagePublication $record, string $to): void
    {
        $from = $record->status;

        if ($from === $to) {
            return;
        }

        $record->forceFill([
            'status' => $to,
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        $this->approval()->causedBy(auth()->user())
            ->performedOn($record)
            ->withProperties([
                'page' => $record->page,
                'locale' => $record->locale,
                'from' => $from,
                'to' => $to,
                'event' => match ($to) {
                    PagePublication::STATUS_PUBLISHED => 'published',
                    PagePublication::STATUS_REVIEWED => $from === PagePublication::STATUS_PUBLISHED ? 'retired' : 'approved',
                    default => 'transitioned',
                },
            ])
            ->log('page publication reviewed');

        Notification::make()
            ->title(__('website.page_publication_review.notifications.updated'))
            ->success()
            ->send();
    }
}
