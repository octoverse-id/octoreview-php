<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

/**
 * One REST operation (see Operations).
 *
 * @internal
 */
final class Operation
{
    /**
     * @param string       $path      relative to /api/v1, with {name} path parameters
     * @param string       $retry     safe, idempotent, keyed or unsafe (docs/sdk-surface.yaml)
     * @param string       $phpMethod the surface name, for example reviews()->create
     * @param list<string> $required  parameters the server requires (path, query or body)
     * @param list<string> $optional  parameters the server accepts besides those
     * @param list<string> $body      the parameters that travel in the JSON body; every other
     *                                non-path parameter is a query parameter
     */
    public function __construct(
        public readonly string $id,
        public readonly string $method,
        public readonly string $path,
        public readonly string $retry,
        public readonly string $phpMethod,
        public readonly array $required,
        public readonly array $optional,
        public readonly array $body,
    ) {}

    public function takes(string $param): bool
    {
        return in_array($param, $this->required, true) || in_array($param, $this->optional, true);
    }

    /** @return list<string> the {placeholders} of the path, in order */
    public function pathParams(): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $this->path, $matches);

        return $matches[1];
    }
}
