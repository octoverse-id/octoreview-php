<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

use GuzzleHttp\Psr7\HttpFactory;
use Octoverse\Octoreview\Exception\ApiException;
use Octoverse\Octoreview\Exception\MalformedResponseException;
use Octoverse\Octoreview\Exception\NotModifiedException;
use Octoverse\Octoreview\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds, sends and decodes API calls: headers, path escaping, application and namespace
 * defaults, retries by retry class, the data envelope, typed errors and redaction.
 *
 * @internal
 */
final class Transport
{
    /** JSON objects that an empty PHP array must still encode as {} rather than []. */
    private const MAP_FIELDS = ['metadata', 'author_metadata'];

    /** Operations the contract declares without credentials. */
    private const UNAUTHENTICATED = ['getHealthLive', 'getHealthReady'];

    /** Operations the contract declares without namespace headers. */
    private const NO_NAMESPACE = [
        'getHealthLive', 'getHealthReady', 'getApplicationSettings', 'putApplicationSettings', 'eraseAuthor',
    ];

    /** 409s an operation retries like a 503 (the contract's completeMediaUpload says so). */
    private const RETRIED_CONFLICTS = [
        'completeMediaUpload' => [ErrorCode::COMPLETE_IN_PROGRESS, ErrorCode::UPLOAD_CHANGED],
    ];

    private const MAX_RESPONSE_BYTES = 16 * 1024 * 1024;

    /** @var \Closure(float): void */
    private \Closure $sleep;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $tenantId,
        private readonly ClientOptions $options,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface&StreamFactoryInterface $factory = new HttpFactory(),
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
    }

    public function options(): ClientOptions
    {
        return $this->options;
    }

    /** The call's application: its own, else the client default. */
    public function application(?CallOptions $call): ?string
    {
        return $call->applicationId ?? $this->options->applicationId;
    }

    /**
     * Sends one operation and returns the decoded envelope.
     *
     * @param list<string>         $pathValues   in the order of the path's placeholders
     * @param array<string, mixed> $params       the caller's parameters, by their wire names;
     *                                           application_id and include_global come from
     *                                           CallOptions and the client defaults instead
     * @param int|null             $acceptStatus a non-2xx status whose body is still a data envelope
     *
     * @return array{data: mixed, pagination: array<string, mixed>|null, meta: ResponseMeta}
     */
    public function call(
        Operation $op,
        array $pathValues = [],
        array $params = [],
        ?CallOptions $call = null,
        ?int $acceptStatus = null,
    ): array {
        [$query, $body] = $this->split($op, $params, $call);
        $url = $this->url($op, $pathValues, $query);
        $payload = $body === null ? null : json_encode(
            $body === [] ? new \stdClass() : $body,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
        $attempts = $op->retry === 'unsafe' ? 1 : $this->options->maxAttempts;
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->attempt($op, $url, $payload, $call, $acceptStatus);
            } catch (TransportException $e) {
                if ($attempt >= $attempts || !$this->retriableNetworkFailure($e)) {
                    throw $e;
                }
                ($this->sleep)(self::backoff($attempt));
            } catch (ApiException $e) {
                $retriable = in_array($e->status, [429, 503], true)
                    || in_array($e->errorCode, self::RETRIED_CONFLICTS[$op->id] ?? [], true);
                if (!$retriable || $attempt >= $attempts) {
                    throw $e;
                }
                if ($e->retryAfter !== null && $e->retryAfter > $this->options->maxRetryAfterSeconds) {
                    throw $e; // longer than this client is willing to wait
                }
                ($this->sleep)($e->retryAfter ?? self::backoff($attempt));
            }
        }
    }

    /**
     * Validates the caller's parameters and splits them into query and body.
     *
     * @param array<string, mixed> $params
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function split(Operation $op, array $params, ?CallOptions $call): array
    {
        $path = $op->pathParams();
        foreach (array_keys($params) as $key) {
            if ($key === 'application_id') {
                throw new \InvalidArgumentException(sprintf(
                    'octoreview: %s takes the application from CallOptions::forApplication() or the client default, not a parameter',
                    $op->phpMethod,
                ));
            }
            if ($key === 'include_global') {
                throw new \InvalidArgumentException(sprintf('octoreview: %s takes include_global from CallOptions::includeGlobal()', $op->phpMethod));
            }
            if (!$op->takes($key) || in_array($key, $path, true)) {
                throw new \InvalidArgumentException(sprintf('octoreview: %s does not take %s', $op->phpMethod, $key));
            }
        }
        if ($op->takes('application_id') && !in_array('application_id', $path, true)) {
            $application = $this->application($call);
            if ($application !== null && $application !== '') {
                $params['application_id'] = $application;
            }
        }
        if ($call?->includeGlobal) {
            if (!$op->takes('include_global')) {
                throw new \InvalidArgumentException(sprintf('octoreview: %s does not take includeGlobal', $op->phpMethod));
            }
            $params['include_global'] = true;
        }
        foreach ($op->required as $name) {
            if (!in_array($name, $path, true) && !array_key_exists($name, $params)) {
                throw new \InvalidArgumentException(sprintf(
                    $name === 'application_id'
                        ? 'octoreview: %s needs an application: set ClientOptions::withApplication() or CallOptions::forApplication()'
                        : 'octoreview: %s needs %s',
                    $op->phpMethod,
                    $name,
                ));
            }
        }
        $query = [];
        $body = [];
        foreach ($params as $key => $value) {
            if (in_array($key, $op->body, true)) {
                if (in_array($key, self::MAP_FIELDS, true) && $value === []) {
                    $value = new \stdClass();
                }
                $body[$key] = $value;
            } elseif ($value !== null) {
                $query[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
        }
        // An operation without body fields (complete, for one) sends no body, as the contract says.
        return [$query, $op->body === [] ? null : $body];
    }

    /**
     * @return array{data: mixed, pagination: array<string, mixed>|null, meta: ResponseMeta}
     */
    private function attempt(Operation $op, string $url, ?string $payload, ?CallOptions $call, ?int $acceptStatus): array
    {
        $request = $this->factory->createRequest($op->method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'octoreview-php/' . Version::VERSION);
        if ($payload !== null) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody($this->factory->createStream($payload));
        }
        if ($call?->requestId !== null) {
            $request = $request->withHeader('X-Request-ID', $call->requestId);
        }
        if (!in_array($op->id, self::UNAUTHENTICATED, true)) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->apiKey)
                ->withHeader('X-Tenant-ID', $this->tenantId);
            if (!in_array($op->id, self::NO_NAMESPACE, true)) {
                [$type, $id] = $this->namespace($call);
                if ($type !== null && $id !== null) {
                    $request = $request->withHeader('X-Namespace-Type', $type)->withHeader('X-Namespace-ID', $id);
                }
            }
            // X-Actor-ID goes on writes only; queryRatings is a POST but a read (retry class safe).
            $actor = $call->actorId ?? $this->options->actorId;
            if ($op->retry !== 'safe' && $actor !== null) {
                $request = $request->withHeader('X-Actor-ID', $actor);
            }
        }
        if ($op->method === 'GET' && $call?->ifNoneMatch !== null) {
            $request = $request->withHeader('If-None-Match', $call->ifNoneMatch);
        }

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Not chained: the client's exception can carry the key or a signed URL unredacted.
            throw new TransportException(
                sprintf('octoreview: %s %s: %s', $op->method, Redactor::url($url), Redactor::text($e->getMessage())),
                $e instanceof NetworkExceptionInterface,
            );
        }
        $meta = new ResponseMeta(self::header($response, 'ETag'), self::header($response, 'X-Request-ID'));
        $raw = (string) $response->getBody();
        if (strlen($raw) > self::MAX_RESPONSE_BYTES) {
            throw new TransportException(sprintf('octoreview: %s answered more than 16 MiB', $op->id), false, $meta->requestId);
        }
        $status = $response->getStatusCode();
        if ($status === 304) {
            throw new NotModifiedException(sprintf('octoreview: %s is unchanged (ETag %s)', $op->id, $call?->ifNoneMatch), $meta->requestId);
        }
        if (($status >= 200 && $status < 300) || $status === $acceptStatus) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !array_key_exists('data', $decoded)) {
                throw new MalformedResponseException(sprintf('octoreview: %s answered without a data envelope', $op->id), $meta->requestId);
            }

            $pagination = is_array($decoded['pagination'] ?? null) ? $decoded['pagination'] : null;

            return ['data' => $decoded['data'], 'pagination' => $pagination, 'meta' => $meta];
        }

        throw self::apiError($response, $raw);
    }

    /**
     * @param list<string>         $pathValues
     * @param array<string, mixed> $query
     */
    private function url(Operation $op, array $pathValues, array $query): string
    {
        $path = $op->path;
        foreach ($op->pathParams() as $i => $name) {
            $value = $pathValues[$i] ?? '';
            if ($value === '') {
                throw new \InvalidArgumentException(sprintf('octoreview: %s needs %s', $op->phpMethod, $name));
            }
            $path = str_replace('{' . $name . '}', rawurlencode($value), $path);
        }

        return rtrim($this->baseUrl, '/') . '/api/v1' . $path
            . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    /** @return array{0: ?string, 1: ?string} */
    private function namespace(?CallOptions $call): array
    {
        if ($call?->globalScope) {
            return [null, null];
        }
        if ($call?->namespaceType !== null) {
            return [$call->namespaceType, $call->namespaceId];
        }

        return [$this->options->namespaceType, $this->options->namespaceId];
    }

    /**
     * Waits before a retry (tests replace the sleep).
     *
     * @internal
     */
    public function pause(float $seconds): void
    {
        ($this->sleep)($seconds);
    }

    /**
     * The default backoff before retry number $attempt: 0.25 s doubling to 5 s, with jitter.
     *
     * @internal
     */
    public function retryDelay(int $attempt): float
    {
        return self::backoff($attempt);
    }

    private function retriableNetworkFailure(TransportException $e): bool
    {
        return $e->networkFailure;
    }

    private static function backoff(int $attempt): float
    {
        $base = min(0.25 * (2 ** ($attempt - 1)), 5.0);

        return $base * (0.8 + mt_rand() / mt_getrandmax() * 0.4);
    }

    private static function header(ResponseInterface $response, string $name): ?string
    {
        $value = $response->getHeaderLine($name);

        return $value === '' ? null : $value;
    }

    private static function apiError(ResponseInterface $response, string $raw): ApiException
    {
        $status = $response->getStatusCode();
        $retryAfter = ctype_digit($response->getHeaderLine('Retry-After')) ? (int) $response->getHeaderLine('Retry-After') : null;
        $decoded = json_decode($raw, true);
        $error = is_array($decoded) && is_array($decoded['error'] ?? null) ? $decoded['error'] : null;
        if ($error === null || !is_string($error['code'] ?? null) || $error['code'] === '') {
            return new ApiException(
                $status,
                'unexpected_response',
                sprintf('the server answered HTTP %d without an error envelope', $status),
                [],
                self::header($response, 'X-Request-ID'),
                null,
                in_array($status, [429, 503], true),
                $retryAfter,
            );
        }
        $requestId = is_string($error['request_id'] ?? null) && $error['request_id'] !== ''
            ? $error['request_id'] : self::header($response, 'X-Request-ID');

        return new ApiException(
            $status,
            $error['code'],
            Redactor::text(is_string($error['message'] ?? null) ? $error['message'] : ''),
            is_array($error['details'] ?? null) ? $error['details'] : [],
            $requestId,
            is_string($error['doc_url'] ?? null) ? $error['doc_url'] : null,
            ($error['retryable'] ?? false) === true,
            $retryAfter,
        );
    }
}
