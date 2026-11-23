<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Upload;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\LimitStream;
use Octoverse\Octoreview\ErrorCode;
use Octoverse\Octoreview\Exception\TransportException;
use Octoverse\Octoreview\Model\UploadTarget;
use Octoverse\Octoreview\Redactor;
use Octoverse\Octoreview\Transport;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Sends a media file to its signed upload URL: only the signed headers plus an explicit
 * Content-Length (never the service key, tenant or namespace headers), retried on the same URL
 * until it expires.
 *
 * @internal
 */
final class Uploader
{
    private const ATTEMPTS = 3;

    /** What to change, by the storage provider's (or the local backend's) error code. */
    private const FIXES = [
        'SignatureDoesNotMatch' => 'send exactly upload.headers and the declared Content-Length; the URL signs both, and the clock must be right',
        'AccessDenied' => 'the URL expired or its signer cannot write to the bucket; create a new upload',
        'ExpiredToken' => "the URL's signing credential expired; create a new upload",
        'RequestTimeTooSkewed' => "this machine's clock is off; sync it",
        'EntityTooLarge' => 'the body is larger than the declared size',
        'NoSuchBucket' => "the server's bucket setting is wrong; tell its operator",
        ErrorCode::UPLOAD_TOO_LARGE => 'the body is larger than the declared size',
        ErrorCode::UPLOAD_TOKEN_EXPIRED => 'the upload URL expired; create a new upload',
        ErrorCode::UPLOAD_TOKEN_INVALID => 'the upload URL is not valid; create a new upload',
        ErrorCode::UPLOAD_ALREADY_COMPLETED => 'this upload already completed; create a new one to upload another file',
    ];

    /** @var \Closure(): \DateTimeImmutable */
    private readonly \Closure $clock;

    /** @param (\Closure(): \DateTimeImmutable)|null $clock the time, for tests */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly Transport $transport,
        private readonly RequestFactoryInterface $factory = new HttpFactory(),
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): \DateTimeImmutable => new \DateTimeImmutable();
    }

    /**
     * @param \Closure(int, int): void|null $progress
     *
     * @throws PutFailedException|TransportException
     */
    public function put(UploadTarget $target, StreamInterface $stream, int $size, ?\Closure $progress): void
    {
        for ($attempt = 1; ; $attempt++) {
            $stream->rewind();
            $wait = $this->attempt($target, $stream, $size, $progress);
            if ($wait === null) {
                return;
            }
            [$seconds, $failure] = $wait;
            if ($seconds === null || $attempt >= self::ATTEMPTS) {
                throw $failure;
            }
            $seconds = $seconds > 0 ? $seconds : $this->transport->retryDelay($attempt);
            $resume = ($this->clock)()->modify(sprintf('+%d milliseconds', (int) ceil($seconds * 1000)));
            if ($resume > $target->expiresAt) {
                throw $failure; // the URL would expire before the retry
            }
            $this->transport->pause($seconds);
        }
    }

    /**
     * One PUT. Null on success; otherwise the failure and when to retry (0 for the default
     * backoff, null for never).
     *
     * @param \Closure(int, int): void|null $progress
     *
     * @return array{0: float|null, 1: PutFailedException|TransportException}|null
     */
    private function attempt(UploadTarget $target, StreamInterface $stream, int $size, ?\Closure $progress): ?array
    {
        $body = new LimitStream($stream, $size, 0);
        $request = $this->factory->createRequest($target->method !== '' ? $target->method : 'PUT', $target->url)
            ->withBody($progress === null ? $body : new ProgressStream($body, $size, $progress));
        // Only the signed headers: never Authorization, X-Tenant-ID or namespace headers.
        foreach ($target->headers as $name => $value) {
            $request = $request->withHeader((string) $name, is_scalar($value) ? (string) $value : '');
        }
        $request = $request->withHeader('Content-Length', (string) $size); // explicit, never chunked

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Not chained: the client's exception carries the signed URL unredacted.
            $failure = new TransportException(
                sprintf('octoreview: %s %s: %s', $request->getMethod(), Redactor::url($target->url), Redactor::text($e->getMessage())),
                $e instanceof NetworkExceptionInterface,
            );

            return [$failure->networkFailure ? 0.0 : null, $failure];
        }
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return null;
        }
        $code = self::providerCode(substr((string) $response->getBody(), 0, 65536));
        if ($status >= 300 && $status < 400) {
            return [null, new PutFailedException($status, $code, 'the upload URL redirected, which uploads never follow; check the storage endpoint')];
        }
        $failure = new PutFailedException($status, $code, $code === null ? null : (self::FIXES[$code] ?? null));
        if ($status !== 429 && $status < 500) {
            return [null, $failure];
        }
        $retryAfter = $response->getHeaderLine('Retry-After');
        if (ctype_digit($retryAfter)) {
            $seconds = (float) $retryAfter;

            return [$seconds > $this->transport->options()->maxRetryAfterSeconds ? null : $seconds, $failure];
        }

        return [0.0, $failure];
    }

    /** S3's XML <Code>, or the local backend's JSON error code. */
    private static function providerCode(string $raw): ?string
    {
        if (preg_match('#<Code>([^<]{1,128})</Code>#', $raw, $match) === 1) {
            return $match[1];
        }
        $decoded = json_decode($raw, true);
        $code = is_array($decoded) && is_array($decoded['error'] ?? null) ? ($decoded['error']['code'] ?? null) : null;

        return is_string($code) && $code !== '' ? $code : null;
    }
}
