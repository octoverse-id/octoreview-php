<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Client;
use Octoverse\Octoreview\ClientOptions;
use Octoverse\Octoreview\ErrorCode;
use Octoverse\Octoreview\Exception\ApiException;
use Octoverse\Octoreview\Exception\ConfigurationException;
use Octoverse\Octoreview\Exception\MalformedResponseException;
use Octoverse\Octoreview\Exception\NotModifiedException;
use Octoverse\Octoreview\Exception\OctoreviewException;
use Octoverse\Octoreview\Exception\TransportException;
use Octoverse\Octoreview\Tests\Support\RecordingClient;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private RecordingClient $http;

    /** @var list<float> */
    private array $slept = [];

    protected function setUp(): void
    {
        $this->http = new RecordingClient();
        $this->slept = [];
    }

    private function client(?ClientOptions $options = null): Client
    {
        $client = new Client(
            'https://reviews.example.com',
            'ocr_live_secret',
            'mbizmarket',
            ($options ?? (new ClientOptions())->withApplication('storefront'))->withHttpClient($this->http),
        );
        // Tests never sleep: record the waits instead.
        $transport = (new \ReflectionProperty(Client::class, 'transport'))->getValue($client);
        (new \ReflectionProperty($transport, 'sleep'))->setValue($transport, function (float $seconds): void {
            $this->slept[] = $seconds;
        });

        return $client;
    }

    /** @param array<string, string> $headers */
    private static function fixture(string $id, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, (string) file_get_contents(__DIR__ . "/../testdata/fixtures/{$id}.json"));
    }

    public function testAReadIsRetriedOn503AndHonorsRetryAfter(): void
    {
        $this->http->push(
            RecordingClient::error(503, ErrorCode::STORAGE_UNAVAILABLE, [], ['Retry-After' => '2']),
            RecordingClient::error(429, 'throttled'),
            self::fixture('getEntity'),
        );
        $entity = $this->client()->entities()->get('e-1');
        self::assertSame('product', $entity->entityType);
        self::assertCount(3, $this->http->requests);
        self::assertSame(2.0, $this->slept[0]);
        self::assertCount(2, $this->slept);
    }

    public function testARetryAfterLongerThanTheBoundIsNotWaitedFor(): void
    {
        $this->http->push(RecordingClient::error(503, ErrorCode::STORAGE_UNAVAILABLE, [], ['Retry-After' => '120']));
        try {
            $this->client()->entities()->get('e-1');
            self::fail('expected the 503');
        } catch (ApiException $e) {
            self::assertSame(120, $e->retryAfter);
            self::assertTrue($e->retryable);
        }
        self::assertCount(1, $this->http->requests);
        self::assertSame([], $this->slept);
    }

    public function testAnUnsafeWriteIsNeverRetried(): void
    {
        $this->http->push(RecordingClient::error(503, ErrorCode::STORAGE_UNAVAILABLE));
        $this->expectException(ApiException::class);
        try {
            $this->client()->reviews()->create('user-1', 5, ['entity_type' => 'product', 'external_id' => 'sku-1']);
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testANetworkFailureIsRetriedForAnIdempotentCallOnly(): void
    {
        $failure = new ConnectException('connection refused', new Request('GET', 'https://reviews.example.com'));
        $this->http->push($failure, self::fixture('deactivateEntity'));
        $this->client()->entities()->deactivate('e-1');
        self::assertCount(2, $this->http->requests);

        $this->http->push($failure);
        try {
            $this->client()->reviews()->remove('r-1');
            self::fail('expected the transport failure');
        } catch (TransportException $e) {
            self::assertStringContainsString('connection refused', $e->getMessage());
        }
        self::assertCount(3, $this->http->requests);
    }

    public function testCompleteRetriesItsDocumentedConflicts(): void
    {
        $this->http->push(
            RecordingClient::error(409, ErrorCode::COMPLETE_IN_PROGRESS, [], ['Retry-After' => '1']),
            RecordingClient::error(409, ErrorCode::UPLOAD_CHANGED),
            self::fixture('completeMediaUpload'),
        );
        $media = $this->client()->media()->complete('r-1', 'm-1');
        self::assertSame('ready', $media->status);
        self::assertCount(3, $this->http->requests);

        $this->http->push(RecordingClient::error(409, ErrorCode::UPLOAD_EXPIRED));
        $this->expectException(ApiException::class);
        $this->client()->media()->complete('r-1', 'm-1');
    }

    public function testRetryAttemptsAreBounded(): void
    {
        $this->http->push(...array_fill(0, 3, RecordingClient::error(503, ErrorCode::STORAGE_UNAVAILABLE)));
        $this->expectException(ApiException::class);
        try {
            $this->client()->entities()->get('e-1');
        } finally {
            self::assertCount(3, $this->http->requests);
        }
    }

    public function testCreateUploadGeneratesAClientUploadIdThatRetriesReuse(): void
    {
        $this->http->push(RecordingClient::error(503, ErrorCode::STORAGE_UNAVAILABLE), self::fixture('createMediaUpload'));
        $upload = $this->client()->media()->createUpload('r-1', 'image', 'image/png', 1024);
        self::assertNotNull($upload->upload);
        [$first, $second] = array_map(
            static fn($r): string => json_decode((string) $r->getBody(), true)['client_upload_id'],
            $this->http->requests,
        );
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
        self::assertSame($first, $second, 'a retry must send the same key');
    }

    public function testTheCallApplicationWinsOverTheClientDefault(): void
    {
        $this->http->push(self::fixture('getEntity'), self::fixture('createReview'));
        $client = $this->client();
        $client->entities()->get('e-1', CallOptions::create()->forApplication('backoffice'));
        self::assertSame('application_id=backoffice', $this->http->last()->getUri()->getQuery());

        $client->reviews()->create('u-1', 4, ['entity_id' => 'e-1']);
        self::assertSame('storefront', json_decode((string) $this->http->last()->getBody(), true)['application_id']);
    }

    public function testAMissingApplicationFailsBeforeSending(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('needs an application');
        try {
            $this->client(new ClientOptions())->reviews()->create('u-1', 4, ['entity_id' => 'e-1']);
        } finally {
            self::assertSame([], $this->http->requests);
        }
    }

    public function testNamespaceDefaultsOverridesAndGlobalScope(): void
    {
        $this->http->push(self::fixture('getEntity'), self::fixture('getEntity'), self::fixture('getEntity'));
        $client = $this->client((new ClientOptions())->withApplication('storefront')->withNamespace('store', 's-1'));

        $client->entities()->get('e-1');
        self::assertSame(['store', 's-1'], [$this->http->last()->getHeaderLine('X-Namespace-Type'), $this->http->last()->getHeaderLine('X-Namespace-ID')]);

        $client->entities()->get('e-1', CallOptions::create()->inNamespace('store', 's-2'));
        self::assertSame('s-2', $this->http->last()->getHeaderLine('X-Namespace-ID'));

        $client->entities()->get('e-1', CallOptions::create()->inGlobalScope());
        self::assertFalse($this->http->last()->hasHeader('X-Namespace-Type'));
        self::assertFalse($this->http->last()->hasHeader('X-Namespace-ID'));
    }

    public function testParametersAreCheckedBeforeSending(): void
    {
        $cases = [
            'unknown key' => [fn() => $this->client()->entities()->list(['entityType' => 'product']), 'does not take entityType'],
            'application as a parameter' => [fn() => $this->client()->entities()->list(['application_id' => 'x']), 'CallOptions::forApplication()'],
            'include_global as a parameter' => [fn() => $this->client()->entities()->list(['include_global' => true]), 'CallOptions::includeGlobal()'],
            'include_global where not taken' => [fn() => $this->client()->reviews()->getRevision('r-1', CallOptions::create()->includeGlobal()), 'does not take includeGlobal'],
            'an empty path value' => [fn() => $this->client()->entities()->get(''), 'needs entity_id'],
            'a path value as a parameter' => [fn() => $this->client()->reviews()->get('r-1', ['review_id' => 'r-2']), 'does not take review_id'],
        ];
        foreach ($cases as $name => [$call, $message]) {
            try {
                $call();
                self::fail("{$name}: expected a refusal");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($message, $e->getMessage(), $name);
            }
        }
        self::assertSame([], $this->http->requests);
    }

    public function testBodiesKeepNullsAndEncodeEmptyMapsAsObjects(): void
    {
        $this->http->push(self::fixture('updateEntity'), self::fixture('completeMediaUpload'));
        $client = $this->client();
        $client->entities()->update('e-1', ['metadata' => [], 'max_images' => null]);
        self::assertSame('{"metadata":{},"max_images":null}', (string) $this->http->requests[0]->getBody());

        $client->media()->complete('r-1', 'm-1');
        self::assertSame('', (string) $this->http->last()->getBody());
        self::assertFalse($this->http->last()->hasHeader('Content-Type'));
    }

    public function testSettingsPutSendsEveryFieldAndUsesTheDefaultApplication(): void
    {
        $this->http->push(self::fixture('putApplicationSettings'));
        $this->client()->settings()->put(null, ['max_images' => 3]);
        $request = $this->http->last();
        self::assertSame('/api/v1/applications/storefront/settings', $request->getUri()->getPath());
        self::assertSame([
            'max_images' => 3, 'max_videos' => null, 'max_image_bytes' => null, 'max_video_bytes' => null,
            'allowed_content_types' => null, 'moderation_mode' => null, 'max_storage_bytes' => null,
        ], json_decode((string) $request->getBody(), true));
    }

    public function testTheActorGoesOnWritesOnly(): void
    {
        $this->http->push(self::fixture('queryRatings'), self::fixture('upsertEntity'), self::fixture('upsertEntity'));
        $client = $this->client((new ClientOptions())->withApplication('storefront')->withActor('admin-7'));
        $client->ratings()->query('product', ['sku-1']);
        self::assertFalse($this->http->last()->hasHeader('X-Actor-ID'), 'queryRatings is a POST but a read');

        $client->entities()->upsert('product', 'sku-1');
        self::assertSame('admin-7', $this->http->last()->getHeaderLine('X-Actor-ID'));

        $client->entities()->upsert('product', 'sku-1', [], CallOptions::create()->asActor('moderator-2')->withRequestId('req-x'));
        self::assertSame('moderator-2', $this->http->last()->getHeaderLine('X-Actor-ID'));
        self::assertSame('req-x', $this->http->last()->getHeaderLine('X-Request-ID'));
    }

    public function testResultsCarryTheEtagAndConditionalReadsThrowNotModified(): void
    {
        $this->http->push(self::fixture('getEntity', 200, ['ETag' => '"v1"', 'X-Request-ID' => 'req-1']), new Response(304));
        $client = $this->client();
        $entity = $client->entities()->get('e-1');
        self::assertSame('"v1"', $entity->meta->etag);
        self::assertSame('req-1', $entity->meta->requestId);

        $this->expectException(NotModifiedException::class);
        try {
            $client->entities()->get('e-1', CallOptions::create()->ifNoneMatch('"v1"'));
        } finally {
            self::assertSame('"v1"', $this->http->last()->getHeaderLine('If-None-Match'));
        }
    }

    public function testAnErrorEnvelopeBecomesATypedException(): void
    {
        $this->http->push(RecordingClient::error(409, ErrorCode::REVIEW_CHANGED, ['current_version' => 3]));
        try {
            $this->client()->reviews()->publish('r-1', 2, 1);
            self::fail('expected the conflict');
        } catch (ApiException $e) {
            self::assertSame(409, $e->status);
            self::assertSame(ErrorCode::REVIEW_CHANGED, $e->errorCode);
            self::assertTrue($e->isReviewChanged());
            self::assertSame(['current_version' => 3], $e->details);
            self::assertSame('req-review_changed', $e->requestId);
            self::assertStringContainsString('review_changed (HTTP 409, request id req-review_changed)', $e->getMessage());
        }
    }

    public function testAnErrorWithoutAnEnvelopeIsStillTyped(): void
    {
        $this->http->push(new Response(502, ['X-Request-ID' => 'req-gw'], '<html>bad gateway</html>'));
        try {
            $this->client()->reviews()->get('r-1');
            self::fail('expected the error');
        } catch (ApiException $e) {
            self::assertSame(502, $e->status);
            self::assertSame('unexpected_response', $e->errorCode);
            self::assertSame('req-gw', $e->requestId);
        }
    }

    public function testMalformedSuccessResponsesAreTyped(): void
    {
        $cases = [
            'no envelope' => [new Response(200, [], '{"id": "e-1"}'), TransportException::class],
            'not an object' => [new Response(200, [], '{"data": [1, 2]}'), MalformedResponseException::class],
            'missing field' => [new Response(200, [], '{"data": {"id": "e-1"}}'), MalformedResponseException::class],
        ];
        foreach ($cases as $name => [$response, $class]) {
            $this->http->push($response);
            try {
                $this->client()->entities()->get('e-1');
                self::fail("{$name}: expected an exception");
            } catch (OctoreviewException $e) {
                self::assertInstanceOf($class, $e, $name);
            }
        }
    }

    public function testHealthReadyDecodesTheUnavailableAnswer(): void
    {
        $this->http->push(RecordingClient::json(503, ['data' => ['status' => 'unavailable', 'database' => 'down']]));
        $ready = $this->client()->health()->ready();
        self::assertSame('unavailable', $ready->status);
        self::assertFalse($this->http->last()->hasHeader('Authorization'), 'the probes are unauthenticated');
    }

    public function testMessagesNeverCarryTheKeyOrASignedUrl(): void
    {
        $sentinel = 'ocr_live_secret';
        $signed = 'https://bucket.s3.amazonaws.com/media/x.png?X-Amz-Signature=deadbeef';
        $this->http->push(
            new ConnectException("failed for Bearer {$sentinel} at {$signed}", new Request('GET', $signed)),
            RecordingClient::json(400, ['error' => [
                'code' => 'validation_failed', 'message' => "bad {$sentinel} {$signed} /api/v1/media-uploads/tok123",
                'details' => [], 'request_id' => 'r', 'doc_url' => '', 'retryable' => false,
            ]]),
        );
        $client = $this->client((new ClientOptions())->withApplication('storefront')->withRetry(1, 30));
        foreach ([fn() => $client->entities()->get('e-1'), fn() => $client->entities()->get('e-1')] as $call) {
            try {
                $call();
                self::fail('expected an exception');
            } catch (OctoreviewException $e) {
                foreach ([$sentinel, 'X-Amz-Signature', 'deadbeef', 'tok123'] as $secret) {
                    self::assertStringNotContainsString($secret, $e->getMessage());
                }
            }
        }
    }

    public function testConstructionIsValidated(): void
    {
        $invalid = [['ftp://x', 'k', 't'], ['/relative', 'k', 't'], ['https://x', '', 't'], ['https://x', 'k', '']];
        $refused = 0;
        foreach ($invalid as [$url, $key, $tenant]) {
            try {
                new Client($url, $key, $tenant);
                self::fail("accepted {$url}, {$key}, {$tenant}");
            } catch (ConfigurationException) {
                $refused++;
            }
        }
        self::assertSame(count($invalid), $refused);
        $this->expectException(\InvalidArgumentException::class);
        new ClientOptions(namespaceType: 'store');
    }

    public function testFromEnvReadsTheFourVariables(): void
    {
        $env = [
            'OCTOREVIEW_BASE_URL' => 'https://reviews.example.com',
            'OCTOREVIEW_API_KEY' => 'ocr_x',
            'OCTOREVIEW_TENANT_ID' => 'demo',
            'OCTOREVIEW_APPLICATION_ID' => 'storefront',
        ];
        $this->http->push(self::fixture('getEntity'));
        Client::fromEnv((new ClientOptions())->withHttpClient($this->http), $env)->entities()->get('e-1');
        $request = $this->http->last();
        self::assertSame('demo', $request->getHeaderLine('X-Tenant-ID'));
        self::assertSame('application_id=storefront', $request->getUri()->getQuery());

        try {
            Client::fromEnv(new ClientOptions(), ['OCTOREVIEW_BASE_URL' => 'https://x']);
            self::fail('expected a configuration error');
        } catch (ConfigurationException $e) {
            self::assertStringContainsString('OCTOREVIEW_API_KEY, OCTOREVIEW_TENANT_ID', $e->getMessage());
        }
    }

    public function testOptionsAreImmutable(): void
    {
        $base = new ClientOptions();
        $withApp = $base->withApplication('a');
        self::assertNull($base->applicationId);
        self::assertSame('a', $withApp->applicationId);
        $call = CallOptions::create();
        $call->forApplication('b');
        self::assertNull($call->applicationId);
    }
}
