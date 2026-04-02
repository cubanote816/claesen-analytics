<?php

namespace Modules\Website\Observers;

use Modules\Website\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProjectObserver
{
    /**
     * Trigger GitHub Action Webhook
     */
    private function triggerPortfolioUpdate(): void
    {
        $token = config('services.github.action_token');
        $url = config('services.github.action_webhook_url');

        if (empty($token) || empty($url)) {
            Log::warning('GitHub PAT or Webhook URL for portfolio update is missing in .env');
            return;
        }

        try {
            // Disparar después del commit de la transacción en la BD
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => 'token ' . $token,
                'X-GitHub-Api-Version' => '2022-11-28'
            ])->post($url, [
                'event_type' => 'backend_update'
            ]);

            if ($response->successful()) {
                Log::info('Portfolio webhook triggered successfully.', [
                    'status' => $response->status(),
                ]);
            } else {
                Log::error('Fallo al avisar a GitHub para el re-despliegue del Frontend', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while triggering portfolio webhook: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        $this->triggerPortfolioUpdate();
    }

    /**
     * Handle the Project "updated" event.
     */
    public function updated(Project $project): void
    {
        $this->triggerPortfolioUpdate();
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        $this->triggerPortfolioUpdate();
    }

    /**
     * Handle the Project "restored" event.
     */
    public function restored(Project $project): void
    {
        $this->triggerPortfolioUpdate();
    }

    /**
     * Handle the Project "force deleted" event.
     */
    public function forceDeleted(Project $project): void
    {
        $this->triggerPortfolioUpdate();
    }
}
