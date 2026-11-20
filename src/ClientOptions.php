<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

use Psr\Http\Client\ClientInterface;

/**
 * Client defaults. Immutable: every `with*` returns a copy.
 */
final class ClientOptions
{
    public function __construct(
        public readonly ?string $applicationId = null,
        public readonly ?string $namespaceType = null,
        public readonly ?string $namespaceId = null,
        public readonly ?string $actorId = null,
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?ClientInterface $uploadHttpClient = null,
        public readonly int $maxAttempts = 3,
        public readonly float $maxRetryAfterSeconds = 30.0,
        public readonly float $timeoutSeconds = 30.0,
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('octoreview: maxAttempts must be at least 1');
        }
        if (($namespaceType === null) !== ($namespaceId === null)) {
            throw new \InvalidArgumentException('octoreview: a namespace needs both a type and an id');
        }
    }

    /** The default application, sent wherever an operation takes application_id. */
    public function withApplication(string $applicationId): self
    {
        return $this->copy(['applicationId' => $applicationId]);
    }

    /** The default namespace (X-Namespace-Type and X-Namespace-ID). */
    public function withNamespace(string $type, string $id): self
    {
        return $this->copy(['namespaceType' => $type, 'namespaceId' => $id]);
    }

    /** The default X-Actor-ID recorded in audit logs for writes. */
    public function withActor(string $actorId): self
    {
        return $this->copy(['actorId' => $actorId]);
    }

    /** A PSR-18 client for API calls (default: Guzzle). */
    public function withHttpClient(ClientInterface $client): self
    {
        return $this->copy(['httpClient' => $client]);
    }

    /** A PSR-18 client for the media PUT; it must not follow redirects. */
    public function withUploadHttpClient(ClientInterface $client): self
    {
        return $this->copy(['uploadHttpClient' => $client]);
    }

    /** Attempts for a retry-safe call, and the longest Retry-After waited for. */
    public function withRetry(int $maxAttempts, float $maxRetryAfterSeconds): self
    {
        return $this->copy(['maxAttempts' => $maxAttempts, 'maxRetryAfterSeconds' => $maxRetryAfterSeconds]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
