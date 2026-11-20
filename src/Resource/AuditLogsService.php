<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\AuditLog;
use Octoverse\Octoreview\Model\Page;

/** The record of every change. */
final class AuditLogsService extends Service
{
    /**
     * @param array{action?: string, aggregate_type?: string, aggregate_id?: string, actor_id?: string, limit?: int, offset?: int} $filters
     *
     * @return Page<AuditLog>
     */
    public function list(array $filters = [], ?CallOptions $options = null): Page
    {
        return $this->page('listAuditLogs', [], $filters, $options, static fn(array $data): AuditLog => AuditLog::fromArray($data));
    }
}
