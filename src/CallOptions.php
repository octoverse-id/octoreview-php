<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

/**
 * Per-call overrides of the client defaults. Immutable: `CallOptions::create()->forApplication('x')`.
 */
final class CallOptions
{
    private function __construct(
        public readonly ?string $applicationId = null,
        public readonly ?string $namespaceType = null,
        public readonly ?string $namespaceId = null,
        public readonly bool $globalScope = false,
        public readonly bool $includeGlobal = false,
        public readonly ?string $ifNoneMatch = null,
        public readonly ?string $actorId = null,
        public readonly ?string $requestId = null,
    ) {}

    public static function create(): self
    {
        return new self();
    }

    /** Overrides the client's default application. */
    public function forApplication(string $applicationId): self
    {
        return $this->copy(['applicationId' => $applicationId]);
    }

    /** Overrides the client's default namespace. */
    public function inNamespace(string $type, string $id): self
    {
        return $this->copy(['namespaceType' => $type, 'namespaceId' => $id, 'globalScope' => false]);
    }

    /** Sends no namespace, even when the client has a default one. */
    public function inGlobalScope(): self
    {
        return $this->copy(['namespaceType' => null, 'namespaceId' => null, 'globalScope' => true]);
    }

    /** Also returns global rows on a namespaced read. */
    public function includeGlobal(): self
    {
        return $this->copy(['includeGlobal' => true]);
    }

    /** Makes a read conditional: an unchanged resource throws NotModifiedException. */
    public function ifNoneMatch(string $etag): self
    {
        return $this->copy(['ifNoneMatch' => $etag]);
    }

    /** X-Actor-ID for this write. */
    public function asActor(string $actorId): self
    {
        return $this->copy(['actorId' => $actorId]);
    }

    /** X-Request-ID, to find the call in the server's logs. */
    public function withRequestId(string $requestId): self
    {
        return $this->copy(['requestId' => $requestId]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
