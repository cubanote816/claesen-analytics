<?php
/**
 * Claesen Outdoor Lighting Platform - Microsoft Graph Service
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
        // Never use Cache::remember() here — it caches null on auth failure, locking
        // out retries for ~58 min even after credentials are corrected.
        $cached = Cache::get('microsoft_graph_token');
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::asForm()->timeout(15)->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'client_id'     => $this->clientId,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'client_secret' => $this->clientSecret,
                    'grant_type'    => 'client_credentials',
                ]
            );

            if ($response->failed()) {
                Log::error('Microsoft Graph: OAuth token request failed.', [
                    'status'    => $response->status(),
                    'error'     => $response->json('error'),
                    'error_desc' => $response->json('error_description'),
                    'tenant_id' => $this->tenantId,
                ]);
                Cache::forget('microsoft_graph_token');
                return null;
            }

            $token = $response->json('access_token');

            if (! $token) {
                Log::error('Microsoft Graph: OAuth response succeeded but access_token is missing.', [
                    'tenant_id' => $this->tenantId,
                ]);
                Cache::forget('microsoft_graph_token');
                return null;
            }

            Cache::put('microsoft_graph_token', $token, 3500);
            return $token;
        } catch (\Exception $e) {
            Log::error('Microsoft Graph: OAuth token request threw exception.', [
                'message'   => $e->getMessage(),
                'tenant_id' => $this->tenantId,
            ]);
            Cache::forget('microsoft_graph_token');
            return null;
        }
    }

    /**
     * Fetch unread messages from a mailbox inbox.
     * Throws RuntimeException on 403/404 so the caller can log and abort cleanly.
     *
     * @return array<int, array{id: string, subject: string, body: array, receivedDateTime: string}>
     */
    public function fetchUnreadMessages(string $mailbox, int $limit = 50): array
    {
        $token = $this->getAccessToken();

        if (! $token) {
            throw new \RuntimeException('Microsoft Graph: failed to obtain access token for NDR fetch.');
        }

        $url = "{$this->baseUrl}/users/{$mailbox}/mailFolders/inbox/messages"
             . '?$filter=isRead eq false'
             . "&\$top={$limit}"
             . '&$select=id,subject,body,from,receivedDateTime';

        $response = Http::withToken($token)->timeout(30)->get($url);

        if ($response->status() === 403) {
            throw new \RuntimeException(
                "Microsoft Graph: 403 Forbidden reading mailbox {$mailbox}. "
                . 'Grant Mail.Read application permission for this mailbox.'
            );
        }

        if ($response->status() === 404) {
            throw new \RuntimeException(
                "Microsoft Graph: 404 Not Found for mailbox {$mailbox}. Verify the mailbox exists."
            );
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                "Microsoft Graph: failed to fetch messages from {$mailbox}. "
                . "Status: {$response->status()}. Body: {$response->body()}"
            );
        }

        return $response->json('value', []);
    }

    /**
     * Mark a single message as read (non-destructive; preserves the NDR for audit).
     */
    public function markMessageRead(string $mailbox, string $messageId): void
    {
        $token = $this->getAccessToken();

        if (! $token) {
            Log::warning("Microsoft Graph: cannot mark message {$messageId} as read — no token.");
            return;
        }

        $response = Http::withToken($token)
            ->timeout(15)
            ->patch("{$this->baseUrl}/users/{$mailbox}/messages/{$messageId}", ['isRead' => true]);

        if ($response->failed()) {
            Log::warning(
                "Microsoft Graph: failed to mark message {$messageId} as read. "
                . "Status: {$response->status()}"
            );
        }
    }

    /**
     * Send an email via Microsoft Graph API.
     */
    public function sendMail(string $senderEmail, array $payload): bool
    {
        $token = $this->getAccessToken();

        if (! $token) {
            Log::error('Microsoft Graph: sendMail aborted — no access token available.', [
                'sender' => $senderEmail,
            ]);
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/users/{$senderEmail}/sendMail", $payload);

            if ($response->failed()) {
                Log::error('Microsoft Graph: sendMail failed.', [
                    'status'  => $response->status(),
                    'sender'  => $senderEmail,
                    // 'error' key from Graph (e.g. ErrorSendAsDenied, ErrorInvalidRecipients)
                    'code'    => $response->json('error.code'),
                    'message' => $response->json('error.message'),
                    // 403 = Mail.Send not granted; 404 = sender mailbox not found in tenant
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Microsoft Graph: sendMail threw exception.', [
                'message' => $e->getMessage(),
                'sender'  => $senderEmail,
            ]);
            return false;
        }
    }
}
