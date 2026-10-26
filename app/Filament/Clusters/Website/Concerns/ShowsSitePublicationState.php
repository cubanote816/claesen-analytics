<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Concerns;

use App\Filament\Clusters\Website\Actions\PublishSiteNowAction;
use App\Filament\Clusters\Website\Widgets\PublicationStatusWidget;

/**
 * "There is something waiting to be published, and this is the button."
 *
 * Deliberate publishing means saving no longer publishes, so the editing screen is the only
 * place an editor can tell a saved change from a live one. Without this, someone changes the
 * address, sees the usual "saved" toast, and has no way of knowing the website still shows
 * the old one.
 *
 * A trait rather than a copied widget and action per screen: the wording of "publish" and
 * the behaviour behind it are exactly the things that must not drift between screens.
 *
 * Screens that already contribute their own header actions override getHeaderActions() and
 * include PublishSiteNowAction::make() in the list — a trait method cannot be extended.
 */
trait ShowsSitePublicationState
{
    /** @return array<class-string> */
    public function getHeaderWidgets(): array
    {
        return [PublicationStatusWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [PublishSiteNowAction::make()];
    }
}
