<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\Webhook\Event;
use Octoverse\Octoreview\Webhook\Webhook;

/** Webhook verification as a client method; Webhook::verify() is the same without a client. */
final class WebhooksService
{
    /** @see Webhook::verify() */
    public function verify(string $body, string $signatureHeader, string $secret, int $toleranceSeconds = Webhook::DEFAULT_TOLERANCE, ?int $now = null): Event
    {
        return Webhook::verify($body, $signatureHeader, $secret, $toleranceSeconds, $now);
    }
}
