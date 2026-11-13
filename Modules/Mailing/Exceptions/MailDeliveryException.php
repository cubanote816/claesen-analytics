<?php

namespace Modules\Mailing\Exceptions;

/**
 * CLA-681: a mail transport rejected a send for a known, non-localized reason
 * (e.g. "graph 503 UnknownError"). The message is stored verbatim in
 * campaign_messages.error_message by ExecuteCampaignJob, so it must never
 * carry tokens, headers or other secrets.
 */
class MailDeliveryException extends \RuntimeException {}
