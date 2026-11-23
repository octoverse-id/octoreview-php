<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Client;
use Octoverse\Octoreview\ClientOptions;
use Octoverse\Octoreview\ErrorCode;
use Octoverse\Octoreview\Exception\ApiException;
use Octoverse\Octoreview\Exception\UploadException;
use Octoverse\Octoreview\Tests\Support\RecordingClient;
use Octoverse\Octoreview\UploadOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class UploadTest extends TestCase
{
    private const SIGNED_URL = 'https://storage.example.com/bucket/media/key.png?X-Amz-Signature=secret-sig';

    private RecordingClient $api;
    private RecordingClient $storage;

    /** @var list<string> the PUT bodies, as a real client would send them */
    private array $putBodies = [];

    /** @var list<ResponseInterface> */
    private array $putAnswers = [];

    /** @var list<string> */
    private array $deleted = [];

    /** @var list<float> */
    private array $slept = [];

    private ?ResponseInterface $completeAnswer = null;
    private int $completeCalls = 0;
    private ?ResponseInterface $intentAnswer = null;
    private string $expiresAt;

    protected function setUp(): void
    {
        $this->expiresAt = (new \DateTimeImmutable('+15 minutes'))->format(DATE_ATOM);
        $this->api = new RecordingClient(fn(RequestInterface $r): ResponseInterface => $this->answerApi($r));
        $this->storage = new RecordingClient(function (RequestInterface $request): ResponseInterface {
            $this->putBodies[] = (string) $request->getBody();

            return array_shift($this->putAnswers) ?? new Response(200);
        });
    }

    private function answerApi(RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $headers = ['Content-Type' => 'application/json', 'X-Request-ID' => 'req-' . $request->getMethod()];
        if ($request->getMethod() === 'POST' && str_ends_with($path, '/media')) {
            if ($this->intentAnswer !== null) {
                return $this->intentAnswer;
            }
            $intent = json_decode((string) $request->getBody(), true);
            $fixture = self::fixture('createMediaUpload');
            $fixture['data']['media']['status'] = 'pending';
            $fixture['data']['upload'] = [
                'method' => 'PUT',
                'url' => self::SIGNED_URL,
                'headers' => ['Content-Type' => $intent['content_type']],
                'expires_at' => $this->expiresAt,
            ];

            return new Response(201, $headers, json_encode($fixture, JSON_THROW_ON_ERROR));
        }
        if (str_ends_with($path, '/complete')) {
            $this->completeCalls++;

            return $this->completeAnswer ?? new Response(200, $headers, json_encode(self::fixture('completeMediaUpload'), JSON_THROW_ON_ERROR));
        }
        if ($request->getMethod() === 'DELETE') {
            $this->deleted[] = $path;

            return new Response(200, $headers, json_encode(self::fixture('deleteMedia'), JSON_THROW_ON_ERROR));
        }

        throw new \LogicException("unexpected {$request->getMethod()} {$path}");
    }

    private function client(): Client
    {
        $client = new Client('https://reviews.example.com', 'ocr_live_secret', 'example', (new ClientOptions())
            ->withApplication('storefront')
            ->withNamespace('store', 's-1')
            ->withActor('admin-7')
            ->withHttpClient($this->api)
            ->withUploadHttpClient($this->storage));
        $transport = (new \ReflectionProperty(Client::class, 'transport'))->getValue($client);
        (new \ReflectionProperty($transport, 'sleep'))->setValue($transport, function (float $seconds): void {
            $this->slept[] = $seconds;
        });

        return $client;
    }

    /** @return array<string, mixed> */
    private static function fixture(string $id): array
    {
        return json_decode((string) file_get_contents(__DIR__ . "/../testdata/fixtures/{$id}.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function apiError(int $status, string $code): Response
    {
        return RecordingClient::error($status, $code);
    }

    public function testUploadBytesSendsOnlyTheSignedHeaders(): void
    {
        $media = $this->client()->media()->uploadBytes('r-1', 'png-bytes', 'image/png', UploadOptions::create()->withPosition(2));
        self::assertSame('ready', $media->status);

        $put = $this->storage->last();
        self::assertSame('PUT', $put->getMethod());
        self::assertSame(self::SIGNED_URL, (string) $put->getUri());
        self::assertSame('image/png', $put->getHeaderLine('Content-Type'));
        self::assertSame('9', $put->getHeaderLine('Content-Length'));
        foreach (['Authorization', 'X-Tenant-ID', 'X-Namespace-Type', 'X-Namespace-ID', 'X-Actor-ID'] as $credential) {
            self::assertFalse($put->hasHeader($credential), "the PUT must not carry {$credential}");
        }
        self::assertSame(['png-bytes'], $this->putBodies);

        $intent = json_decode((string) $this->api->requests[0]->getBody(), true);
        self::assertSame('image', $intent['kind']);
        self::assertSame(9, $intent['size_bytes']);
        self::assertSame(2, $intent['position']);
        self::assertNotEmpty($intent['client_upload_id']);
        self::assertSame('admin-7', $this->api->requests[0]->getHeaderLine('X-Actor-ID'));
    }

    public function testUploadFileInfersTypeAndSizeAndStreamsLargeBodies(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'octoreview') . '.MP4';
        $size = 11 * 1024 * 1024;
        file_put_contents($path, str_repeat('v', $size));
        $reports = [];
        try {
            $this->client()->media()->uploadFile('r-1', $path, UploadOptions::create()->withProgress(
                static function (int $sent, int $total) use (&$reports): void {
                    $reports[] = [$sent, $total];
                },
            )->withDuration(12.5));
        } finally {
            unlink($path);
        }
        $intent = json_decode((string) $this->api->requests[0]->getBody(), true);
        self::assertSame(['video', 'video/mp4', $size, 12.5], [$intent['kind'], $intent['content_type'], $intent['size_bytes'], $intent['duration_seconds']]);
        self::assertSame((string) $size, $this->storage->last()->getHeaderLine('Content-Length'));
        self::assertSame($size, strlen($this->putBodies[0]));
        self::assertNotEmpty($reports);
        self::assertSame([$size, $size], end($reports));
    }

    public function testAFailedPutIsRetriedFromTheStart(): void
    {
        $this->putAnswers = [new Response(503, ['Retry-After' => '1'])];
        $media = $this->client()->media()->uploadBytes('r-1', 'abcdef', 'image/webp');
        self::assertSame('ready', $media->status);
        self::assertSame(['abcdef', 'abcdef'], $this->putBodies, 'each attempt sends the whole body');
        self::assertSame([1.0], $this->slept);
    }

    public function testAPutRetryNeverOutlivesTheUrl(): void
    {
        $this->expiresAt = (new \DateTimeImmutable('+10 seconds'))->format(DATE_ATOM);
        $this->putAnswers = [new Response(503, ['Retry-After' => '20'])];
        $this->completeAnswer = self::apiError(409, ErrorCode::UPLOAD_NOT_FOUND);
        try {
            $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png');
            self::fail('expected the PUT failure');
        } catch (UploadException $e) {
            self::assertSame(UploadException::STAGE_PUT, $e->stage);
        }
        self::assertCount(1, $this->putBodies);
        self::assertSame([], $this->slept);
    }

    public function testATerminalPutFailureExplainsAndCleansUp(): void
    {
        $this->putAnswers = [new Response(
            403,
            ['Content-Type' => 'application/xml'],
            '<?xml version="1.0"?><Error><Code>SignatureDoesNotMatch</Code><Message>no</Message></Error>',
        )];
        $this->completeAnswer = self::apiError(409, ErrorCode::UPLOAD_NOT_FOUND); // no bytes landed: the only case that deletes
        try {
            $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png');
            self::fail('expected the PUT failure');
        } catch (UploadException $e) {
            self::assertSame(UploadException::STAGE_PUT, $e->stage);
            self::assertSame('r-1', $e->reviewId);
            self::assertNotNull($e->mediaId);
            self::assertNotNull($e->uploadExpiresAt);
            self::assertSame('SignatureDoesNotMatch', $e->providerCode);
            self::assertStringContainsString('Content-Length', (string) $e->fix);
            self::assertSame(['req-POST'], $e->requestIds);
            foreach (['secret-sig', 'X-Amz-Signature', 'ocr_live_secret'] as $secret) {
                self::assertStringNotContainsString($secret, (string) $e);
            }
        }
        self::assertCount(1, $this->putBodies, 'a 403 is not retried');
        self::assertCount(1, $this->deleted);
    }

    public function testRedirectsAreNeverFollowed(): void
    {
        $this->putAnswers = [new Response(307, ['Location' => 'https://elsewhere.example.com/'])];
        $this->completeAnswer = self::apiError(409, ErrorCode::UPLOAD_NOT_FOUND);
        try {
            $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png');
            self::fail('expected the redirect to fail');
        } catch (UploadException $e) {
            self::assertSame(UploadException::STAGE_PUT, $e->stage);
            self::assertStringContainsString('redirected', (string) $e->fix);
        }
        self::assertCount(1, $this->storage->requests);
    }

    public function testAFailedPutNeverDeletesMediaAnotherCallCompleted(): void
    {
        // Two helpers share a client upload id; the other one PUT and completed first, so this
        // PUT meets upload_already_completed. The media is ready: return it, delete nothing.
        $this->putAnswers = [self::apiError(409, ErrorCode::UPLOAD_ALREADY_COMPLETED)];
        $media = $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png', UploadOptions::create()->withClientUploadId('shared'));
        self::assertSame('ready', $media->status);
        self::assertSame([], $this->deleted);
    }

    public function testAFailedPutKeepsTheRowWhenCompleteCannotTell(): void
    {
        $this->putAnswers = [new Response(403)];
        $this->completeAnswer = self::apiError(409, ErrorCode::UPLOAD_EXPIRED);
        try {
            $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png');
            self::fail('the PUT failure must be reported');
        } catch (UploadException $e) {
            self::assertSame(UploadException::STAGE_PUT, $e->stage);
        }
        self::assertSame([], $this->deleted, "the server's cleanup owns an undecidable row");
    }

    public function testACompleteRejectionReportsItsStage(): void
    {
        $this->completeAnswer = self::apiError(409, ErrorCode::UPLOAD_EXPIRED);
        try {
            $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png');
            self::fail('expected the complete failure');
        } catch (UploadException $e) {
            self::assertSame(UploadException::STAGE_COMPLETE, $e->stage);
            self::assertInstanceOf(ApiException::class, $e->getPrevious());
            self::assertSame(['req-POST', 'req-upload_expired'], $e->requestIds);
        }
    }

    public function testAnIntentFailureReportsItsStageWithoutAPut(): void
    {
        $this->intentAnswer = self::apiError(409, ErrorCode::MEDIA_LIMIT_EXCEEDED);
        try {
            $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png', UploadOptions::create()->withCallOptions(CallOptions::create()->forApplication('backoffice')));
            self::fail('expected the intent failure');
        } catch (UploadException $e) {
            self::assertSame(UploadException::STAGE_INTENT, $e->stage);
            self::assertNull($e->mediaId);
            $previous = $e->getPrevious();
            self::assertInstanceOf(ApiException::class, $previous);
            self::assertSame(ErrorCode::MEDIA_LIMIT_EXCEEDED, $previous->errorCode);
        }
        self::assertSame('application_id=backoffice', $this->api->last()->getUri()->getQuery());
        self::assertSame([], $this->storage->requests);
    }

    public function testInputsAreCheckedBeforeAnyCall(): void
    {
        $client = $this->client();
        $cases = [
            'unknown extension' => fn() => $client->media()->uploadFile('r-1', '/tmp/notes.txt'),
            'missing file' => fn() => $client->media()->uploadFile('r-1', '/nonexistent/photo.png'),
            'neither image nor video' => fn() => $client->media()->uploadBytes('r-1', 'x', 'text/plain'),
        ];
        foreach ($cases as $name => $call) {
            try {
                $call();
                self::fail("{$name}: expected a refusal");
            } catch (UploadException $e) {
                self::assertSame(UploadException::STAGE_INTENT, $e->stage, $name);
            }
        }
        $unseekable = new NoSeekStream(Utils::streamFor('abc'));
        try {
            $client->media()->uploadStream('r-1', $unseekable, 'image/png', 3);
            self::fail('a stream that cannot rewind must be refused');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('seekable', $e->getMessage());
        }
        self::assertSame([], $this->api->requests);
    }

    public function testAReplayedIntentThatAlreadyCompletedReturnsTheMedia(): void
    {
        $fixture = self::fixture('createMediaUpload');
        $fixture['data']['media']['status'] = 'ready';
        $fixture['data']['upload'] = null;
        $this->intentAnswer = new Response(200, ['Content-Type' => 'application/json'], json_encode($fixture, JSON_THROW_ON_ERROR));
        $media = $this->client()->media()->uploadBytes('r-1', 'abc', 'image/png', UploadOptions::create()->withClientUploadId('replayed'));
        self::assertSame('ready', $media->status);
        self::assertSame([], $this->storage->requests);
    }
}
