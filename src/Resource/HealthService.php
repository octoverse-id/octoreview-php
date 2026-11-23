<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\HealthLive;
use Octoverse\Octoreview\Model\HealthReady;

/** The unauthenticated probes. */
final class HealthService extends Service
{
    /** The server process serves. */
    public function live(?CallOptions $options = null): HealthLive
    {
        return $this->one('getHealthLive', [], [], $options, HealthLive::fromArray(...));
    }

    /** Whether the server and its database serve; `status` is "unavailable" (not an exception) when the database is down. */
    public function ready(?CallOptions $options = null): HealthReady
    {
        return $this->one('getHealthReady', [], [], $options, HealthReady::fromArray(...), 503);
    }
}
