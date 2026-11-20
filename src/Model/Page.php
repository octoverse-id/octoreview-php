<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/**
 * One page of a list operation.
 *
 * @template T
 */
final class Page
{
    /** @param list<T> $items */
    public function __construct(
        public readonly array $items,
        public readonly Pagination $pagination,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}
}
