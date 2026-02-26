<?php

namespace Modules\Website\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotifyAstroFrontendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $url = env('GITHUB_ACTION_WEBHOOK_URL');
        $token = env('GITHUB_ACTION_TOKEN');

        if (!$url || !$token) {
            Log::warning('GitHub Action variables missing in .env (GITHUB_ACTION_WEBHOOK_URL or GITHUB_ACTION_TOKEN)');
            return;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'Authorization' => 'token ' . $token,
        ])->post($url, [
            'event_type' => 'update_portfolio',
        ]);

        if ($response->failed()) {
            Log::error('Failed to trigger Astro frontend webhook: ' . $response->body());
        } else {
            Log::info('Successfully triggered Astro frontend webhook');
        }
    }
}
