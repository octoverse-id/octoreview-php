<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use Octoverse\Octoreview\Client;
use Octoverse\Octoreview\ClientOptions;
use Octoverse\Octoreview\ErrorCode;
use Octoverse\Octoreview\Exception\ApiException;
use Octoverse\Octoreview\Exception\UploadException;
use Octoverse\Octoreview\Model\ApplicationSettings;
use Octoverse\Octoreview\UploadOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The integration suite against a real server (`make integration`, CI `integration`). It reads
 * the harness's .octoreview-dev.env from OCTOREVIEW_DEV_ENV. With OCTOREVIEW_WRITE_FIXTURES=1 it
 * also saves one response per operation into testdata/fixtures (`make fixtures`), which the
 * contract gate decodes offline.
 */
#[Group('integration')]
final class IntegrationTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n" . "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";

    private static function client(): Client
    {
        $path = getenv('OCTOREVIEW_DEV_ENV');
        if ($path === false || $path === '') {
            self::markTestSkipped('set OCTOREVIEW_DEV_ENV to a harness .octoreview-dev.env (make integration)');
        }
        $env = [];
        foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $pair = explode('=', (string) $line, 2);
            if (count($pair) === 2) {
                $env[$pair[0]] = $pair[1];
            }
        }
        $http = new GuzzleClient(['timeout' => 60]);
        $options = (new ClientOptions())
            ->withActor('php-integration')
            ->withHttpClient(getenv('OCTOREVIEW_WRITE_FIXTURES') === '1' ? new FixtureRecorder($http) : $http);

        return Client::fromEnv($options, $env);
    }

    public function testTheMainFlows(): void
    {
        $client = self::client();
        $run = 'php-' . bin2hex(random_bytes(6));

        self::assertSame('ok', $client->health()->live()->status);
        self::assertSame('ok', $client->health()->ready()->database);

        // An entity allowing two images: the third is refused (the epic's acceptance check).
        $entity = $client->entities()->upsert('product', $run, ['metadata' => ['source' => 'php'], 'max_images' => 2]);
        $review = $client->reviews()->create("{$run}-a", 4, ['entity_id' => $entity->id, 'title' => 'Good', 'is_verified_purchase' => true]);
        self::assertSame('published', $review->status, 'auto-publish');
        $fetch = new GuzzleClient(['timeout' => 30, 'http_errors' => false]);
        foreach ([0, 1] as $position) {
            $media = $client->media()->uploadBytes($review->id, self::PNG, 'image/png', UploadOptions::create()->withPosition($position));
            self::assertSame('ready', $media->status);
            self::assertNotNull($media->url);
            self::assertSame(200, $fetch->get($media->url)->getStatusCode(), 'the photo is served');
        }
        try {
            $client->media()->uploadBytes($review->id, self::PNG, 'image/png');
            self::fail('the third image must be refused');
        } catch (UploadException $e) {
            self::assertSame(UploadException::STAGE_INTENT, $e->stage);
            $previous = $e->getPrevious();
            self::assertInstanceOf(ApiException::class, $previous);
            self::assertSame(ErrorCode::MEDIA_LIMIT_EXCEEDED, $previous->errorCode);
        }

        $summary = $client->ratings()->get('product', $run);
        $batch = $client->ratings()->query('product', [$run, "{$run}-missing"]);
        $forEntity = $client->ratings()->forEntity($entity->id);
        self::assertSame(1, $summary->ratingCount);
        self::assertCount(2, $batch);
        self::assertNull($batch[1]->entityId);
        self::assertSame(1, $forEntity->ratingCount);
        $listed = $client->reviews()->list(['entity_id' => $entity->id, 'has_media' => true]);
        self::assertSame(1, $listed->pagination->count);
        self::assertCount(2, $listed->items[0]->media);
        $media = $client->media()->list($review->id);
        self::assertSame(2, $media->pagination->count);
        self::assertCount(2, $media->items);
        $second = $client->media()->list($review->id, ['limit' => 1, 'offset' => 1]);
        self::assertSame(1, $second->pagination->limit);
        self::assertCount(1, $second->items);
        self::assertSame($media->items[1]->id, $second->items[0]->id);

        // Pre-moderation: a new review waits; the version guard protects publishing.
        $app = $client->settings()->get();
        $client->settings()->put(null, ['moderation_mode' => 'pre_moderation'] + self::settings($app));
        try {
            $pending = $client->reviews()->create("{$run}-b", 2, ['entity_id' => $entity->id]);
            self::assertSame('pending', $pending->status);
            try {
                $client->reviews()->publish($pending->id, $pending->version + 1, $pending->mediaVersion);
                self::fail('a stale version must be refused');
            } catch (ApiException $e) {
                self::assertTrue($e->isReviewChanged());
            }
            $published = $client->reviews()->publish($pending->id, $pending->version, $pending->mediaVersion);
            self::assertSame('published', $published->status);

            // An edit of a published review under pre-moderation becomes a revision.
            $edited = $client->reviews()->update($published->id, ['rating' => 3]);
            $revision = $client->reviews()->getRevision($edited->id);
            $approved = $client->reviews()->approveRevision($edited->id, $revision->id, $edited->version, $edited->mediaVersion);
            self::assertSame(3, $approved->rating);

            // Media on a published review waits for moderation under pre-moderation.
            $photo = $client->media()->uploadFile($approved->id, __DIR__ . '/../../examples/photo.png');
            $current = $client->reviews()->get($approved->id);
            $moderated = $client->media()->approve($approved->id, $photo->id, $current->version, $current->mediaVersion);
            self::assertSame('approved', $moderated->moderationStatus);
            $client->media()->reject($approved->id, $photo->id, 'blurry');
            $client->media()->delete($approved->id, $photo->id);

            $client->reviews()->hide($approved->id, 'spam');
            $rejectable = $client->reviews()->create("{$run}-c", 1, ['entity_id' => $entity->id]);
            $client->reviews()->reject($rejectable->id);
            $client->reviews()->remove($rejectable->id);
            $revised = $client->reviews()->update($review->id, ['body' => 'an edit to drop']);
            $dropped = $client->reviews()->getRevision($revised->id);
            $client->reviews()->rejectRevision($revised->id, $dropped->id);
        } finally {
            $client->settings()->put(null, self::settings($app));
        }

        self::assertGreaterThan(0, $client->auditLogs()->list(['aggregate_id' => $review->id])->pagination->count);
        self::assertSame(1, $client->authors()->erase("{$run}-a")->reviewsRedacted);
        $found = $client->entities()->lookup('product', $run);
        $got = $client->entities()->get($found->id);
        $listedEntities = $client->entities()->list(['external_id' => $run]);
        $updated = $client->entities()->update($got->id, ['max_images' => null]);
        $deactivated = $client->entities()->deactivate($updated->id);
        self::assertSame(1, $listedEntities->pagination->count);
        self::assertNull($updated->mediaPolicy->maxImages);
        self::assertFalse($deactivated->isActive);
    }

    /** @return array<string, mixed> every setting as it was, for put() (which replaces them all) */
    private static function settings(ApplicationSettings $app): array
    {
        return [
            'max_images' => $app->maxImages,
            'max_videos' => $app->maxVideos,
            'max_image_bytes' => $app->maxImageBytes,
            'max_video_bytes' => $app->maxVideoBytes,
            'allowed_content_types' => $app->allowedContentTypes,
            'moderation_mode' => $app->moderationMode,
            'max_storage_bytes' => $app->maxStorageBytes,
        ];
    }
}
