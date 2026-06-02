<?php

namespace Modules\Website\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Website\Models\ConsultationReminder;
use Modules\Website\Services\ConsultationService;

class ProcessConsultationRemindersCommand extends Command
{
    protected $signature   = 'website:process-reminders';
    protected $description = 'Process pending consultation reminders that are due';

    public function handle(ConsultationService $service): int
    {
        $reminders = ConsultationReminder::query()
            ->where('remind_at', '<=', now())
            ->where('status', 'pending')
            ->with(['request', 'user'])
            ->get();

        foreach ($reminders as $reminder) {
            // Atomic claim: skip if another process already picked this reminder up
            $claimed = ConsultationReminder::where('id', $reminder->id)
                ->where('status', 'pending')
                ->update(['status' => 'processing']);

            if (!$claimed) {
                continue;
            }

            // Sync model state so subsequent Eloquent operations reflect 'processing'
            $reminder->refresh();

            try {
                if ($reminder->user) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('website.activities.notifications.reminder_due_title'))
                        ->body(__('website.activities.notifications.reminder_due_body', [
                            'title' => $reminder->title,
                            'name'  => $reminder->request?->name ?? '—',
                        ]))
                        ->warning()
                        ->sendToDatabase($reminder->user);
                }

                if ($reminder->request) {
                    $service->logActivity(
                        $reminder->request,
                        'reminder_triggered',
                        __('website.activities.logs.reminder_triggered', ['title' => $reminder->title]),
                        ['reminder_id' => $reminder->id, 'title' => $reminder->title],
                        $reminder->user_id
                    );
                }

                // Raw update guarded by current status to avoid stale-model races
                ConsultationReminder::whereKey($reminder->id)
                    ->where('status', 'processing')
                    ->update(['status' => 'completed', 'completed_at' => now()]);

            } catch (\Exception $e) {
                // Raw revert: model may have status='processing' or be stale — bypass Eloquent dirty check
                ConsultationReminder::whereKey($reminder->id)
                    ->where('status', 'processing')
                    ->update(['status' => 'pending']);
                Log::error("ProcessConsultationRemindersCommand: failed for reminder #{$reminder->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
