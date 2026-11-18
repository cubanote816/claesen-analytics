<?php

declare(strict_types=1);

namespace Modules\Knx\Observers;

use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Services\KnxNoticeMailer;

/**
 * A created notice is what triggers the email decision (KNX-54).
 *
 * It lives in an observer and not at the six call sites so the rule cannot drift: the
 * immediate kinds are mailed when they happen, everything else waits for the digest.
 * `created` fires only on a real insert, which is exactly the idempotency the field
 * retries need — a retry returns the existing row and never fires again.
 *
 * The send is direct and the mailable is queued: with a real queue the worker picks the
 * mail up after the request, so it is not tied to the writing transaction, and with the
 * `sync` driver (tests) it is deterministic and observable.
 */
class KnxNotificationObserver
{
    public function __construct(private readonly KnxNoticeMailer $mailer) {}

    public function created(KnxNotification $notification): void
    {
        if (! $this->mailer->isImmediate($notification)) {
            return;
        }

        $this->mailer->sendImmediate($notification);
    }
}
