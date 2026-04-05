<?php
/**
 * Claesen Intelligence Hub - Microsoft Graph Mailer Transport
 * Path: Modules/Mailing/Mail/Transport/MicrosoftGraphTransport.php
 * 
 * Custom Symfony Mailer transport that uses Microsoft Graph API for email sending.
 */

namespace Modules\Mailing\Mail\Transport;

use Modules\Mailing\Services\MicrosoftGraphService;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Illuminate\Support\Facades\Log;

class MicrosoftGraphTransport extends AbstractTransport
{
    protected MicrosoftGraphService $graphService;

    public function __construct(MicrosoftGraphService $graphService)
    {
        parent::__construct();
        $this->graphService = $graphService;
    }

    /**
     * Send the message using Microsoft Graph API.
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $payload = $this->getPayload($email);
        $from = $this->getSenderEmail($email);

        if (!$this->graphService->sendMail($from, $payload)) {
            Log::error("Microsoft Graph Transport: Failed to send email via Graph API.");
            throw new \Exception("Failed to send email via Microsoft Graph API.");
        }
    }

    /**
     * Convert Symfony Email to Microsoft Graph API payload.
     */
    protected function getPayload(Email $email): array
    {
        $bodyType = $email->getHtmlBody() ? 'html' : 'text';
        $bodyContent = $email->getHtmlBody() ?: $email->getTextBody();

        $payload = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $bodyType,
                    'content' => $bodyContent,
                ],
                'toRecipients' => [],
                'replyTo' => [],
                'from' => [
                    'emailAddress' => [
                        'address' => $email->getFrom()[0]->getAddress() ?? config('mail.from.address'),
                        'name' => config('mail.from.name'),
                    ],
                ],
                'sender' => [
                    'emailAddress' => [
                        'address' => $email->getFrom()[0]->getAddress() ?? config('mail.from.address'),
                        'name' => config('mail.from.name'),
                    ],
                ],
            ],
            'saveToSentItems' => 'true',
        ];

        // Add To Recipients
        foreach ($email->getTo() as $to) {
            $payload['message']['toRecipients'][] = [
                'emailAddress' => ['address' => $to->getAddress(), 'name' => $to->getName()],
            ];
        }

        // Add Reply-To Recipients
        foreach ($email->getReplyTo() as $replyTo) {
            $payload['message']['replyTo'][] = [
                'emailAddress' => ['address' => $replyTo->getAddress(), 'name' => $replyTo->getName()],
            ];
        }

        // Add Carbon Copy (CC) if present
        if ($email->getCc()) {
            foreach ($email->getCc() as $cc) {
                $payload['message']['ccRecipients'][] = [
                    'emailAddress' => ['address' => $cc->getAddress()],
                ];
            }
        }

        return $payload;
    }

    /**
     * Get the sender email address.
     */
    protected function getSenderEmail(Email $email): string
    {
        $from = $email->getFrom();
        return count($from) > 0 ? $from[0]->getAddress() : config('mail.from.address');
    }

    /**
     * String representation of the transport.
     */
    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
