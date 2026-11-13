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
use Illuminate\Support\Sleep;
use Modules\Mailing\Exceptions\MailConfigurationException;

class MicrosoftGraphService
{
    // CLA-532: nullable — the constructor only assigns, it never validates, so
    // a missing credential no longer throws a TypeError while the mailer is
    // being resolved (at boot, inside Mail::extend, or before
    // mailing:parse-bounces reaches its own try/catch). Validation happens at
    // the start of every Graph operation via assertConfigured().
    protected ?string $clientId;
    protected ?string $tenantId;
    protected ?string $clientSecret;
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    /** Longest Retry-After (seconds) we are willing to wait for inside one send. */
    private const MAX_RETRY_WAIT_SECONDS = 10;

    /**
     * CLA-681: absolute (microtime) deadline of the caller's run budget. The
     * campaign job sets it around each send so a retry never overruns the budget;
     * null = no budget (any other caller).
     */
    private static ?float $sendDeadline = null;

    /** Reason of the last failed sendMail() ("graph <status> <code>[: <message>]"), null if none. */
    private ?string $lastFailureReason = null;

    /** @var array<string, string> Folder ids cached per "mailbox|name" for the lifetime of this instance. */
    protected array $folderIds = [];

    public function __construct()
    {
        // CLA-532: read only from config() (config:cache-safe). config/mail.php
        // already resolves these from MICROSOFT_GRAPH_* at config-build time; the
        // previous runtime env() fallbacks here were dead under config:cache.
        $this->clientId = config('mail.mailers.microsoft-graph.client_id');
        $this->tenantId = config('mail.mailers.microsoft-graph.tenant_id');
        $this->clientSecret = config('mail.mailers.microsoft-graph.client_secret');
    }

    /**
     * CLA-532: guard called at the start of every Graph operation, before any
     * HTTP. Missing credentials raise a controlled MailConfigurationException
     * (extends RuntimeException) — never a TypeError.
     */
    private function assertConfigured(): void
    {
        if (empty($this->clientId) || empty($this->tenantId) || empty($this->clientSecret)) {
            throw new MailConfigurationException(
                'Microsoft Graph mailer is not configured '
                .'(MICROSOFT_GRAPH_CLIENT_ID / MICROSOFT_GRAPH_TENANT_ID / MICROSOFT_GRAPH_CLIENT_SECRET).'
            );
        }
    }

    /**
     * Get an access token from Microsoft Entra ID.
     */
    public function getAccessToken(): ?string
    {
        $this->assertConfigured();

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
     * Move a message to a top-level folder of the mailbox (created when missing).
     * The message keeps its read state.
     *
     * @throws \RuntimeException on 403/404 or any other Graph failure.
     */
    public function moveMessageToFolder(string $mailbox, string $messageId, string $folderName): void
    {
        $token = $this->getAccessToken();

        if (! $token) {
            throw new \RuntimeException('Microsoft Graph: failed to obtain access token to move a message.');
        }

        $folderId = $this->resolveFolderId($token, $mailbox, $folderName);

        $response = Http::withToken($token)
            ->timeout(15)
            ->post("{$this->baseUrl}/users/{$mailbox}/messages/{$messageId}/move", ['destinationId' => $folderId]);

        $this->assertFolderResponse($response, $mailbox, "move message {$messageId}");
    }

    private function resolveFolderId(string $token, string $mailbox, string $folderName): string
    {
        $cacheKey = "{$mailbox}|{$folderName}";

        if (isset($this->folderIds[$cacheKey])) {
            return $this->folderIds[$cacheKey];
        }

        $escaped  = str_replace("'", "''", $folderName);
        $response = Http::withToken($token)
            ->timeout(15)
            ->get("{$this->baseUrl}/users/{$mailbox}/mailFolders", ['$filter' => "displayName eq '{$escaped}'"]);

        $this->assertFolderResponse($response, $mailbox, "look up folder '{$folderName}'");

        $id = $response->json('value.0.id');

        if (! $id) {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$this->baseUrl}/users/{$mailbox}/mailFolders", ['displayName' => $folderName]);

            $this->assertFolderResponse($response, $mailbox, "create folder '{$folderName}'");
            $id = $response->json('id');
        }

        if (! $id) {
            throw new \RuntimeException("Microsoft Graph: no id returned for folder '{$folderName}' in {$mailbox}.");
        }

        return $this->folderIds[$cacheKey] = $id;
    }

    private function assertFolderResponse(\Illuminate\Http\Client\Response $response, string $mailbox, string $action): void
    {
        if ($response->status() === 403) {
            throw new \RuntimeException(
                "Microsoft Graph: 403 Forbidden to {$action} in mailbox {$mailbox}. "
                . 'Grant Mail.ReadWrite application permission for this mailbox.'
            );
        }

        if ($response->status() === 404) {
            throw new \RuntimeException(
                "Microsoft Graph: 404 Not Found to {$action} in mailbox {$mailbox}. Verify the mailbox exists."
            );
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                "Microsoft Graph: failed to {$action} in {$mailbox}. Status: {$response->status()}. Body: {$response->body()}"
            );
        }
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
     * Set (or clear with null) the run-budget deadline that sendMail() retries must respect.
     */
    public static function setSendDeadline(?float $deadline): void
    {
        self::$sendDeadline = $deadline;
    }

    /**
     * Stable, non-localized reason of the last failed sendMail(), or null when
     * it failed without a Graph response (no token, connection error).
     */
    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    /**
     * Send an email via Microsoft Graph API.
     *
     * CLA-681: only HTTP 429/503 (Graph did not process the request) are retried,
     * up to mailing.graph_retry_attempts times, within the caller's run budget.
     * 502/504, other errors and connection timeouts are NOT retried: Graph may
     * already have accepted the mail and a retry would deliver it twice.
     */
    public function sendMail(string $senderEmail, array $payload): bool
    {
        $this->lastFailureReason = null;
        $token = $this->getAccessToken();

        if (! $token) {
            Log::error('Microsoft Graph: sendMail aborted — no access token available.', [
                'sender' => $senderEmail,
            ]);
            return false;
        }

        $maxRetries = max(0, (int) config('mailing.graph_retry_attempts', 2));
        // Campaign runs always set a deadline (run budget or run-wide retry window); other
        // callers without one get a per-message window so retries stay bounded.
        $retryUntil = self::$sendDeadline
            ?? microtime(true) + (float) config('mailing.graph_retry_window_seconds', 20);

        try {
            for ($attempt = 0; ; $attempt++) {
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->post("{$this->baseUrl}/users/{$senderEmail}/sendMail", $payload);

                if (! $response->failed()) {
                    return true;
                }

                $status  = $response->status();
                $code    = $response->json('error.code');
                $message = $response->json('error.message');

                $wait = null;
                if ($attempt < $maxRetries && in_array($status, [429, 503], true)) {
                    $wait = $this->retryWaitSeconds($response->header('Retry-After'), $attempt);
                }

                if ($wait !== null && microtime(true) + $wait <= $retryUntil) {
                    Log::warning('Microsoft Graph: sendMail transient failure, retrying.', [
                        'status'       => $status,
                        'attempt'      => $attempt + 1,
                        'wait_seconds' => $wait,
                        'sender'       => $senderEmail,
                    ]);
                    Sleep::usleep((int) round($wait * 1_000_000));
                    continue;
                }

                Log::error('Microsoft Graph: sendMail failed.', [
                    'status'  => $status,
                    'sender'  => $senderEmail,
                    // 'error' key from Graph (e.g. ErrorSendAsDenied, ErrorInvalidRecipients)
                    'code'    => $code,
                    'message' => $message,
                    // 403 = Mail.Send not granted; 404 = sender mailbox not found in tenant
                    'attempts' => $attempt + 1,
                ]);
                $this->lastFailureReason = $this->failureReason($status, $code, $message);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Microsoft Graph: sendMail threw exception.', [
                'message' => $e->getMessage(),
                'sender'  => $senderEmail,
            ]);
            return false;
        }
    }

    /**
     * Seconds to wait before retry number $attempt + 1: the Retry-After header
     * (delta-seconds or HTTP-date, capped), else the configured backoff.
     */
    private function retryWaitSeconds(?string $retryAfter, int $attempt): float
    {
        if ($retryAfter !== null && is_numeric($retryAfter) && (float) $retryAfter >= 0) {
            return min((float) $retryAfter, self::MAX_RETRY_WAIT_SECONDS);
        }

        if ($retryAfter !== null && ($at = strtotime($retryAfter)) !== false) {
            return min(max(0.0, (float) ($at - time())), self::MAX_RETRY_WAIT_SECONDS);
        }

        $backoff = array_values((array) config('mailing.graph_retry_backoff_seconds', [1, 3]));
        if ($backoff === []) {
            return 1.0;
        }

        return (float) ($backoff[$attempt] ?? end($backoff));
    }

    private function failureReason(int $status, mixed $code, mixed $message): string
    {
        $reason = 'graph ' . $status . ' ' . (is_string($code) && $code !== '' ? $code : 'UnknownError');

        if (is_string($message) && trim($message) !== '') {
            $reason .= ': ' . mb_substr(trim($message), 0, 200);
        }

        return $reason;
    }
}
