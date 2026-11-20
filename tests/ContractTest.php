<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests;

use GuzzleHttp\Psr7\Response;
use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Client;
use Octoverse\Octoreview\ClientOptions;
use Octoverse\Octoreview\ErrorCode;
use Octoverse\Octoreview\Operations;
use Octoverse\Octoreview\Tests\Support\Contract;
use Octoverse\Octoreview\Tests\Support\RecordingClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The offline contract gate (`make contract`): the operation table, the PHP surface, the error
 * codes and every request this SDK sends are checked against the vendored server contract in
 * testdata/contract, in both directions.
 */
#[Group('contract')]
final class ContractTest extends TestCase
{
    /** Surface helpers that arrive with the next PR (uploads and webhooks); none may exist yet. */
    private const PENDING_HELPERS = ['media()->uploadFile', 'media()->uploadBytes', 'media()->uploadStream', 'webhooks()->verify'];

    private const RETRY_BEHAVIOR = [
        'safe' => 'a read: retried on timeouts, 503 and 429, honoring Retry-After',
        'idempotent' => 'repeats to the same state: retried like a read',
        'keyed' => 'idempotent with its `client_upload_id`, which the SDK fills in: retried like a read',
        'unsafe' => 'never retried; after a lost response, read the resource to learn the outcome',
    ];

    public function testTheOperationTableMatchesTheOpenApiContract(): void
    {
        $contract = Contract::operations();
        $sdkNone = array_column(array_filter(
            Contract::surface()['operations'],
            static fn(array $row): bool => ($row['sdk'] ?? null) === 'none',
        ), 'operation');
        self::assertEqualsCanonicalizing(
            array_values(array_diff(array_keys($contract), $sdkNone)),
            array_keys(Operations::all()),
            'every contract operation (except sdk: none) has exactly one row',
        );
        foreach (Operations::all() as $id => $op) {
            $want = $contract[$id];
            self::assertSame($want['method'], $op->method, "{$id} method");
            self::assertSame($want['path'], $op->path, "{$id} path");
            self::assertEqualsCanonicalizing($want['body'], $op->body, "{$id} body fields");
            $query = array_values(array_diff(array_merge($op->required, $op->optional), $op->pathParams(), $op->body));
            self::assertEqualsCanonicalizing($want['query'], $query, "{$id} query parameters");
        }
    }

    public function testTheOperationTableMatchesTheSurface(): void
    {
        foreach (Contract::surface()['operations'] as $row) {
            if (($row['sdk'] ?? null) === 'none') {
                continue;
            }
            $op = Operations::get($row['operation']);
            self::assertSame($row['php'], $op->phpMethod, "{$op->id} PHP name");
            self::assertSame($row['retry'], $op->retry, "{$op->id} retry class");
            self::assertSame($row['required'] ?? [], $op->required, "{$op->id} required");
            self::assertSame($row['optional'] ?? [], $op->optional, "{$op->id} optional");
        }
    }

    public function testEverySurfaceMethodExistsAndNothingElse(): void
    {
        $surface = Contract::surface();
        $named = [];
        foreach ($surface['operations'] as $row) {
            if (($row['sdk'] ?? null) !== 'none') {
                $named[] = $row['php'];
            }
        }
        foreach ($surface['helpers'] as $helper) {
            $named[] = $helper['php'];
        }
        $declared = self::declaredMethods();
        foreach ($named as $name) {
            if (in_array($name, self::PENDING_HELPERS, true)) {
                self::assertNotContains($name, $declared, "{$name} exists now: remove it from PENDING_HELPERS");
                continue;
            }
            self::assertContains($name, $declared, "the surface names {$name}, which the client lacks");
        }
        foreach ($declared as $name) {
            self::assertContains($name, $named, "the client declares {$name}, which the surface does not name");
        }
    }

    public function testEveryErrorCodeIsDeclared(): void
    {
        $enum = Contract::openapi()['components']['schemas']['ErrorCode']['enum'];
        self::assertEqualsCanonicalizing($enum, ErrorCode::ALL);
        $constants = (new \ReflectionClass(ErrorCode::class))->getConstants();
        unset($constants['ALL']);
        self::assertEqualsCanonicalizing($enum, array_values($constants));
    }

    /** @return iterable<string, array{string}> */
    public static function operationIds(): iterable
    {
        foreach (array_keys(Operations::all()) as $id) {
            yield $id => [$id];
        }
    }

    #[DataProvider('operationIds')]
    public function testTheRequestMatchesTheContract(string $id): void
    {
        $calls = self::calls();
        self::assertArrayHasKey($id, $calls, "{$id} has no recorded call");
        $want = Contract::operations()[$id];
        $http = new RecordingClient();
        $http->push(new Response(200, ['Content-Type' => 'application/json'], self::fixture($id)));
        $client = new Client('https://reviews.example.com/', 'ocr_secret', 'v:tenant', (new ClientOptions())
            ->withHttpClient($http)
            ->withApplication('v:application_id')
            ->withNamespace('v:namespace_type', 'v:namespace_id')
            ->withActor('v:actor'));
        $options = CallOptions::create();
        if (Operations::get($id)->takes('include_global')) {
            $options = $options->includeGlobal();
        }

        $calls[$id]($client, $options); // decodes the captured server response
        $request = $http->last();

        self::assertSame($want['method'], $request->getMethod());
        $path = preg_replace_callback('/\{([a-z_]+)\}/', static fn(array $m): string => rawurlencode((string) self::value($m[1])), $want['path']);
        self::assertSame('/api/v1' . $path, $request->getUri()->getPath());

        parse_str($request->getUri()->getQuery(), $query);
        self::assertEqualsCanonicalizing($want['query'], array_keys($query), 'query parameters');
        foreach ($query as $name => $value) {
            $expected = self::value((string) $name);
            self::assertSame(is_bool($expected) ? ($expected ? 'true' : 'false') : (string) $expected, $value, "query {$name}");
        }

        $raw = (string) $request->getBody();
        if ($want['body'] === []) {
            self::assertSame('', $raw, 'an operation without body fields sends no body');
        } else {
            self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
            $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            self::assertEqualsCanonicalizing($want['body'], array_keys($body), 'body fields');
            foreach ($body as $name => $value) {
                self::assertEquals(self::value($name), $value, "body {$name}");
            }
        }

        $authenticated = in_array('X-Tenant-ID', $want['headers'], true);
        self::assertSame($authenticated ? 'Bearer ocr_secret' : '', $request->getHeaderLine('Authorization'));
        self::assertSame($authenticated ? 'v:tenant' : '', $request->getHeaderLine('X-Tenant-ID'));
        $namespaced = in_array('X-Namespace-Type', $want['headers'], true);
        self::assertSame($namespaced ? 'v:namespace_type' : '', $request->getHeaderLine('X-Namespace-Type'));
        self::assertSame($namespaced ? 'v:namespace_id' : '', $request->getHeaderLine('X-Namespace-ID'));
        self::assertSame(in_array('X-Actor-ID', $want['headers'], true) ? 'v:actor' : '', $request->getHeaderLine('X-Actor-ID'));
        self::assertStringStartsWith('octoreview-php/', $request->getHeaderLine('User-Agent'));
    }

    public function testTheReadmeRetryTableMatchesTheSurface(): void
    {
        $readme = (string) file_get_contents(__DIR__ . '/../README.md');
        $start = "<!-- retry-table:start -->\n";
        $end = '<!-- retry-table:end -->';
        $i = strpos($readme, $start);
        $j = strpos($readme, $end);
        self::assertNotFalse($i, 'README.md lost its retry-table markers');
        self::assertNotFalse($j);
        $table = self::retryTable();
        self::assertSame($table, substr($readme, $i + strlen($start), $j - $i - strlen($start)), "README.md's retry table is stale; replace it with:\n{$table}");
    }

    /** The README's retry table, rendered from the operation table. */
    public static function retryTable(): string
    {
        $byClass = [];
        foreach (Operations::all() as $op) {
            $byClass[$op->retry][] = '`' . $op->phpMethod . '`';
        }
        $out = "| Retry class | Behavior | Methods |\n| --- | --- | --- |\n";
        foreach (self::RETRY_BEHAVIOR as $class => $behavior) {
            $methods = $byClass[$class] ?? [];
            sort($methods);
            $out .= "| `{$class}` | {$behavior} | " . implode(', ', $methods) . " |\n";
        }

        return $out;
    }

    /**
     * One call per operation with every parameter set to a self-describing value.
     *
     * @return array<string, \Closure(Client, CallOptions): mixed>
     */
    private static function calls(): array
    {
        $v = self::value(...);
        $p = self::pick(...);

        return [
            'getHealthLive' => static fn(Client $c, CallOptions $o) => $c->health()->live($o),
            'getHealthReady' => static fn(Client $c, CallOptions $o) => $c->health()->ready($o),
            'listEntities' => static fn(Client $c, CallOptions $o) => $c->entities()->list($p('entity_type', 'external_id', 'is_active', 'limit', 'offset'), $o),
            'upsertEntity' => static fn(Client $c, CallOptions $o) => $c->entities()->upsert($v('entity_type'), $v('external_id'), $p('metadata', 'max_images', 'max_videos', 'max_image_bytes', 'max_video_bytes'), $o),
            'lookupEntity' => static fn(Client $c, CallOptions $o) => $c->entities()->lookup($v('entity_type'), $v('external_id'), $o),
            'getEntity' => static fn(Client $c, CallOptions $o) => $c->entities()->get($v('entity_id'), $o),
            'updateEntity' => static fn(Client $c, CallOptions $o) => $c->entities()->update($v('entity_id'), $p('metadata', 'max_images', 'max_videos', 'max_image_bytes', 'max_video_bytes', 'is_active'), $o),
            'deactivateEntity' => static fn(Client $c, CallOptions $o) => $c->entities()->deactivate($v('entity_id'), $o),
            'getEntityRating' => static fn(Client $c, CallOptions $o) => $c->ratings()->forEntity($v('entity_id'), $o),
            'queryRatings' => static fn(Client $c, CallOptions $o) => $c->ratings()->query($v('entity_type'), $v('external_ids'), $o),
            'getApplicationSettings' => static fn(Client $c, CallOptions $o) => $c->settings()->get(null, $o),
            'putApplicationSettings' => static fn(Client $c, CallOptions $o) => $c->settings()->put(null, $p('max_images', 'max_videos', 'max_image_bytes', 'max_video_bytes', 'allowed_content_types', 'moderation_mode', 'max_storage_bytes'), $o),
            'listReviews' => static fn(Client $c, CallOptions $o) => $c->reviews()->list($p('entity_id', 'entity_type', 'external_id', 'author_id', 'include_unpublished', 'status', 'rating', 'has_media', 'is_verified_purchase', 'has_pending_revision', 'media_moderation_status', 'sort', 'limit', 'offset'), $o),
            'createReview' => static fn(Client $c, CallOptions $o) => $c->reviews()->create($v('author_id'), $v('rating'), $p('entity_id', 'entity_type', 'external_id', 'title', 'body', 'metadata', 'author_metadata', 'source_ref', 'is_verified_purchase'), $o),
            'getReview' => static fn(Client $c, CallOptions $o) => $c->reviews()->get($v('review_id'), $p('author_id', 'include_unpublished'), $o),
            'updateReview' => static fn(Client $c, CallOptions $o) => $c->reviews()->update($v('review_id'), $p('rating', 'title', 'body', 'metadata', 'author_metadata', 'is_verified_purchase'), $o),
            'removeReview' => static fn(Client $c, CallOptions $o) => $c->reviews()->remove($v('review_id'), $o),
            'publishReview' => static fn(Client $c, CallOptions $o) => $c->reviews()->publish($v('review_id'), $v('expected_version'), $v('expected_media_version'), $v('reason'), $o),
            'rejectReview' => static fn(Client $c, CallOptions $o) => $c->reviews()->reject($v('review_id'), $v('reason'), $o),
            'hideReview' => static fn(Client $c, CallOptions $o) => $c->reviews()->hide($v('review_id'), $v('reason'), $o),
            'getReviewRevision' => static fn(Client $c, CallOptions $o) => $c->reviews()->getRevision($v('review_id'), $o),
            'approveReviewRevision' => static fn(Client $c, CallOptions $o) => $c->reviews()->approveRevision($v('review_id'), $v('revision_id'), $v('expected_version'), $v('expected_media_version'), $o),
            'rejectReviewRevision' => static fn(Client $c, CallOptions $o) => $c->reviews()->rejectRevision($v('review_id'), $v('revision_id'), $o),
            'listReviewMedia' => static fn(Client $c, CallOptions $o) => $c->media()->list($v('review_id'), $p('author_id', 'include_unpublished'), $o),
            'createMediaUpload' => static fn(Client $c, CallOptions $o) => $c->media()->createUpload($v('review_id'), $v('kind'), $v('content_type'), $v('size_bytes'), $p('client_upload_id', 'position', 'width', 'height', 'duration_seconds'), $o),
            'completeMediaUpload' => static fn(Client $c, CallOptions $o) => $c->media()->complete($v('review_id'), $v('media_id'), $o),
            'deleteMedia' => static fn(Client $c, CallOptions $o) => $c->media()->delete($v('review_id'), $v('media_id'), $o),
            'approveMedia' => static fn(Client $c, CallOptions $o) => $c->media()->approve($v('review_id'), $v('media_id'), $v('expected_version'), $v('expected_media_version'), $v('reason'), $o),
            'rejectMedia' => static fn(Client $c, CallOptions $o) => $c->media()->reject($v('review_id'), $v('media_id'), $v('reason'), $o),
            'eraseAuthor' => static fn(Client $c, CallOptions $o) => $c->authors()->erase($v('author_id'), $o),
            'listAuditLogs' => static fn(Client $c, CallOptions $o) => $c->auditLogs()->list($p('action', 'aggregate_type', 'aggregate_id', 'actor_id', 'limit', 'offset'), $o),
        ];
    }

    /** A value that names its parameter, so a misrouted one shows up by name. */
    private static function value(string $name): mixed
    {
        return match ($name) {
            // path values carry a slash, which the SDK must escape
            'entity_id', 'review_id', 'media_id' => "v/{$name}",
            'application_id' => 'v:application_id',
            'include_global' => true,
            'is_active', 'include_unpublished', 'has_media', 'is_verified_purchase', 'has_pending_revision' => false,
            'limit' => 11, 'offset' => 12, 'rating' => 4,
            'max_images' => 21, 'max_videos' => 22, 'max_image_bytes' => 23, 'max_video_bytes' => 24, 'max_storage_bytes' => 25,
            'size_bytes' => 31, 'position' => 32, 'width' => 33, 'height' => 34,
            'expected_version' => 41, 'expected_media_version' => 42,
            'duration_seconds' => 3.5,
            'external_ids' => ['v:external_ids'],
            'allowed_content_types' => ['v:allowed_content_types'],
            'metadata', 'author_metadata' => ['key' => "v:{$name}"],
            default => "v:{$name}",
        };
    }

    /** @return array<string, mixed> */
    private static function pick(string ...$names): array
    {
        return array_combine($names, array_map(self::value(...), $names));
    }

    private static function fixture(string $id): string
    {
        $raw = file_get_contents(__DIR__ . "/../testdata/fixtures/{$id}.json");
        self::assertNotFalse($raw, "testdata/fixtures/{$id}.json is missing");

        return $raw;
    }

    /** @return list<string> every public service method, as `service()->method` */
    private static function declaredMethods(): array
    {
        $out = [];
        $client = new \ReflectionClass(Client::class);
        foreach ($client->getMethods(\ReflectionMethod::IS_PUBLIC) as $accessor) {
            $type = $accessor->getReturnType();
            if ($accessor->isStatic() || $accessor->isConstructor() || !$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            $class = $type->getName();
            if (!class_exists($class)) {
                continue;
            }
            $service = new \ReflectionClass($class);
            foreach ($service->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!$method->isConstructor() && $method->getDeclaringClass()->getName() === $service->getName()) {
                    $out[] = $accessor->getName() . '()->' . $method->getName();
                }
            }
        }

        return $out;
    }
}
