<?php

namespace Modules\Analytics\Services;

use Modules\Analytics\Models\ProjectInsight;
use Modules\Cafca\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BudgetAssistantService
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function simulateOffer(array $requestData, string $locale = 'nl'): string
    {
        $zipcode = $requestData['zipcode'] ?? '0000';
        $category = $requestData['category'] ?? '';

        $hashKey = 'budget_sim_' . md5($category . $zipcode . $locale);

        if (\Illuminate\Support\Facades\Cache::has($hashKey)) {
            return $hashKey; // Return tracking key immediately
        }

        // Set temporary processing flag
        \Illuminate\Support\Facades\Cache::put($hashKey, 'PROCESSING', now()->addMinutes(10));

        // Dispatch background job
        \Modules\Analytics\Jobs\GenerateBudgetSimulationJob::dispatch($requestData, $locale, $hashKey);

        return $hashKey;
    }
}
