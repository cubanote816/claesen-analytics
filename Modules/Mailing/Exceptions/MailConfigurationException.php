<?php

namespace Modules\Mailing\Exceptions;

/**
 * CLA-532: raised when a mailing transport is selected but not usable —
 * an unknown mailing driver, missing Microsoft Graph credentials, or the
 * simulation driver invoked in production.
 *
 * Extends RuntimeException so the existing `catch (\Exception)` blocks in the
 * campaign send loop (ExecuteCampaignJob::sendToProspects) and the consultation
 * e-mail guard (ConsultationService::createRequest) handle it as a controlled
 * failure — unlike the previous TypeError from assigning null to a typed
 * property, which those blocks did not catch.
 */
class MailConfigurationException extends \RuntimeException {}
