<?php

namespace Modules\Safety\Observers;

use Modules\Safety\Models\Checklist;
use Modules\Core\Models\User;
use Filament\Notifications\Notification;

class ChecklistObserver
{
    /**
     * Handle the Checklist "saved" event.
     */
    public function saved(Checklist $checklist): void
    {
        // Only notify if it's currently active AND either:
        // 1. It was just created as active.
        // 2. It was just toggled from inactive to active.
        
        $shouldNotify = $checklist->is_active && (
            $checklist->wasRecentlyCreated || 
            ($checklist->isDirty('is_active') && $checklist->getOriginal('is_active') == false)
        );

        if ($shouldNotify) {
            $users = User::role(['project_manager', 'super_admin'])->get();
            
            foreach ($users as $user) {
                $notification = Notification::make()
                    ->title('Nieuwe checklist beschikbaar')
                    ->body("De checklist '**{$checklist->name}**' is nu geactiveerd en beschikbaar.")
                    ->icon('heroicon-o-clipboard-document-check')
                    ->success()
                    ->viewData([
                        'module' => 'safety',
                        'checklist_id' => $checklist->id,
                    ]);

                $user->notifyNow($notification->toDatabase());
            }
        }
    }
}
