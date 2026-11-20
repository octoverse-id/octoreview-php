<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Exception\MalformedResponseException;
use Octoverse\Octoreview\Model\Page;
use Octoverse\Octoreview\Model\Pagination;
use Octoverse\Octoreview\Operations;
use Octoverse\Octoreview\ResponseMeta;
use Octoverse\Octoreview\Transport;

/**
 * Shared plumbing of the resource services: send one operation, decode its data.
 *
 * @internal
 */
abstract class Service
{
    public function __construct(protected readonly Transport $transport) {}

    /**
     * @template T
     *
     * @param list<string>                                    $pathValues
     * @param array<string, mixed>                            $params
     * @param \Closure(array<string, mixed>, ResponseMeta): T $build
     *
     * @return T
     */
    protected function one(
        string $operation,
        array $pathValues,
        array $params,
        ?CallOptions $options,
        \Closure $build,
        ?int $acceptStatus = null,
    ): mixed {
        $result = $this->transport->call(Operations::get($operation), $pathValues, $params, $options, $acceptStatus);

        return $build(self::object($result['data'], $operation), $result['meta']);
    }

    /**
     * @template T
     *
     * @param list<string>                     $pathValues
     * @param array<string, mixed>             $params
     * @param \Closure(array<string, mixed>): T $build
     *
     * @return Page<T>
     */
    protected function page(string $operation, array $pathValues, array $params, ?CallOptions $options, \Closure $build): Page
    {
        $result = $this->transport->call(Operations::get($operation), $pathValues, $params, $options);
        if ($result['pagination'] === null) {
            throw new MalformedResponseException(sprintf('octoreview: %s answered without pagination', $operation));
        }

        return new Page(
            $this->items($result['data'], $operation, $build),
            Pagination::fromArray($result['pagination']),
            $result['meta'],
        );
    }

    /**
     * @template T
     *
     * @param list<string>                     $pathValues
     * @param array<string, mixed>             $params
     * @param \Closure(array<string, mixed>): T $build
     *
     * @return list<T>
     */
    protected function many(string $operation, array $pathValues, array $params, ?CallOptions $options, \Closure $build): array
    {
        $result = $this->transport->call(Operations::get($operation), $pathValues, $params, $options);

        return $this->items($result['data'], $operation, $build);
    }

    /**
     * @template T
     *
     * @param \Closure(array<string, mixed>): T $build
     *
     * @return list<T>
     */
    private function items(mixed $data, string $operation, \Closure $build): array
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new MalformedResponseException(sprintf('octoreview: %s answered data that is not a list', $operation));
        }

        return array_map(static fn(mixed $item): mixed => $build(self::object($item, $operation)), $data);
    }

    /** @return array<string, mixed> */
    private static function object(mixed $data, string $operation): array
    {
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new MalformedResponseException(sprintf('octoreview: %s answered data that is not an object', $operation));
        }

        return $data;
    }
}
