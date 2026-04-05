<?php
/**
 * Claesen Intelligence Hub - Microsoft Graph Service
 * Path: Modules/Mailing/Services/MicrosoftGraphService.php
 * 
 * Handles OAuth 2.0 Client Credentials flow and Microsoft Graph API communication.
 */

namespace Modules\Mailing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MicrosoftGraphService
{
    protected string $clientId;
    protected string $tenantId;
    protected string $clientSecret;
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct()
    {
        $this->clientId = config('mail.mailers.microsoft-graph.client_id', env('MICROSOFT_GRAPH_CLIENT_ID'));
        $this->tenantId = config('mail.mailers.microsoft-graph.tenant_id', env('MICROSOFT_GRAPH_TENANT_ID'));
        $this->clientSecret = config('mail.mailers.microsoft-graph.client_secret', env('MICROSOFT_GRAPH_CLIENT_SECRET'));
    }

    /**
     * Get an access token from Microsoft Entra ID.
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember('microsoft_graph_token', 3500, function () {
            try {
                $response = Http::asForm()->timeout(15)->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                    'client_id' => $this->clientId,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ]);

                if ($response->failed()) {
                    Log::error("Microsoft Graph Auth Failure: " . $response->body());
                    return null;
                }

                return $response->json('access_token');
            } catch (\Exception $e) {
                Log::error("Microsoft Graph Auth Exception: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Send an email via Microsoft Graph API.
     */
    public function sendMail(string $senderEmail, array $payload): bool
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/users/{$senderEmail}/sendMail", $payload);

            if ($response->failed()) {
                Log::error("Microsoft Graph Mail Send Failure: " . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Microsoft Graph Mail Send Exception: " . $e->getMessage());
            return false;
        }
    }
}
