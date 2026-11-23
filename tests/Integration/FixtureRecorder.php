<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests\Integration;

use GuzzleHttp\Psr7\Utils;
use Octoverse\Octoreview\Operations;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Saves the first successful response body of each operation into testdata/fixtures. */
final class FixtureRecorder implements ClientInterface
{
    /** @var array<string, true> */
    private array $saved = [];

    public function __construct(private readonly ClientInterface $next) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->next->sendRequest($request);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }
        $body = (string) $response->getBody();
        $path = substr($request->getUri()->getPath(), strlen('/api/v1'));
        foreach (Operations::all() as $op) {
            $pattern = '#^' . preg_replace('/\\\{[a-z_]+\\\}/', '[^/]+', preg_quote($op->path, '#')) . '$#';
            if ($op->method === $request->getMethod() && preg_match($pattern, $path) === 1 && !isset($this->saved[$op->id])) {
                $this->saved[$op->id] = true;
                file_put_contents(__DIR__ . "/../../testdata/fixtures/{$op->id}.json", $body);
            }
        }

        return $response->withBody(Utils::streamFor($body));
    }
}
