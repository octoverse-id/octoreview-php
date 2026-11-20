<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that records every request and answers from a queue (or a responder). Unit
 * tests never touch the network.
 */
final class RecordingClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    private array $queue = [];

    /** @param (\Closure(RequestInterface): ResponseInterface)|null $responder */
    public function __construct(private ?\Closure $responder = null) {}

    public function push(ResponseInterface|\Throwable ...$answers): self
    {
        array_push($this->queue, ...$answers);

        return $this;
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    public static function json(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed>  $details
     * @param array<string, string> $headers
     */
    public static function error(int $status, string $code, array $details = [], array $headers = []): Response
    {
        return self::json($status, ['error' => [
            'code' => $code,
            'message' => "the server said {$code}",
            'details' => $details,
            'request_id' => 'req-' . $code,
            'doc_url' => "https://docs.example/errors#{$code}",
            'retryable' => in_array($status, [429, 503], true),
        ]], $headers);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->queue !== []) {
            $answer = array_shift($this->queue);
            if ($answer instanceof \Throwable) {
                throw $answer;
            }

            return $answer;
        }
        if ($this->responder !== null) {
            return ($this->responder)($request);
        }

        throw new \LogicException('RecordingClient: no answer queued for ' . $request->getMethod() . ' ' . $request->getUri());
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)] ?? throw new \LogicException('no request was sent');
    }
}
