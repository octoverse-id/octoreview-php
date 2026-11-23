<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests;

use Octoverse\Octoreview\Client;
use Octoverse\Octoreview\Exception\InvalidSignatureException;
use Octoverse\Octoreview\Webhook\AuthorErasedEvent;
use Octoverse\Octoreview\Webhook\EventType;
use Octoverse\Octoreview\Webhook\MediaEvent;
use Octoverse\Octoreview\Webhook\RatingChangedEvent;
use Octoverse\Octoreview\Webhook\ReviewEvent;
use Octoverse\Octoreview\Webhook\Webhook;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    // Signed by the server's own octoreview.events.dispatch.webhook_signature (secret whsec-test).
    private const BODY = '{"id": "8d0c5a2e-7f0a-4b8e-9d55-2f4f8d6f3a10", "type": "review.published", "occurred_at": "2026-11-19T10:15:00.123456+00:00", "tenant_id": "mbizmarket", "application_id": "storefront", "namespace_type": null, "namespace_id": null, "resource_type": "review", "resource_id": "1f9e7c5a-0b2d-4c8e-a6f1-3d5b7e9c1a20", "request_id": "req_1", "data": {"review_id": "1f9e7c5a-0b2d-4c8e-a6f1-3d5b7e9c1a20", "entity_id": "2a8f6d4b-1c3e-4f5a-9b7d-0e2c4a6b8d10", "entity_type": "product", "external_id": "sku-1", "status": "published", "previous_status": null, "rating": 5, "is_verified_purchase": true, "version": 2, "media_version": 1}}';
    private const SIGNATURE = 't=1795000000,v1=7b1afd134873f95a6cfb82cbaaa586b5d3ef2e4ed05fb00976c683f0fb9faf9b';
    private const SECRET = 'whsec-test';
    private const NOW = 1795000000 + 60;

    public function testAServerSignedWebhookVerifiesAndDecodes(): void
    {
        $event = Webhook::verify(self::BODY, self::SIGNATURE, self::SECRET, now: self::NOW);
        self::assertSame(EventType::REVIEW_PUBLISHED, $event->type);
        self::assertSame('8d0c5a2e-7f0a-4b8e-9d55-2f4f8d6f3a10', $event->id);
        self::assertNull($event->namespaceType);
        $review = $event->review();
        self::assertSame('sku-1', $review->externalId);
        self::assertNull($review->previousStatus);
        self::assertTrue($review->isVerifiedPurchase);
        self::assertSame([2, 1], [$review->version, $review->mediaVersion]);

        $this->expectException(\LogicException::class);
        $event->media();
    }

    public function testATamperedOrStaleWebhookIsRejected(): void
    {
        $cases = [
            'another body' => fn() => Webhook::verify(str_replace('"rating": 5', '"rating": 1', self::BODY), self::SIGNATURE, self::SECRET, now: self::NOW),
            'another secret' => fn() => Webhook::verify(self::BODY, self::SIGNATURE, 'whsec-other', now: self::NOW),
            'too old' => fn() => Webhook::verify(self::BODY, self::SIGNATURE, self::SECRET, now: 1795000000 + 301),
            'from the future' => fn() => Webhook::verify(self::BODY, self::SIGNATURE, self::SECRET, now: 1795000000 - 301),
            'malformed' => fn() => Webhook::verify(self::BODY, 'sha256=abc', self::SECRET, now: self::NOW),
            'missing' => fn() => Webhook::verify(self::BODY, '', self::SECRET, now: self::NOW),
        ];
        foreach ($cases as $name => $verify) {
            try {
                $verify();
                self::fail("{$name}: accepted");
            } catch (InvalidSignatureException $e) {
                self::assertStringContainsString('invalid webhook signature', $e->getMessage(), $name);
            }
        }
        // A rotated secret: the server sends one v1 per active secret.
        $rotated = 't=1795000000,v1=' . str_repeat('0', 64) . ',' . substr(self::SIGNATURE, 13);
        self::assertSame('review.published', Webhook::verify(self::BODY, $rotated, self::SECRET, now: self::NOW)->type);
        $client = new Client('https://reviews.example.com', 'ocr_x', 't');
        self::assertSame('review.published', $client->webhooks()->verify(self::BODY, self::SIGNATURE, self::SECRET, now: self::NOW)->type);
    }

    public function testAnEmptySecretIsAProgrammingError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Webhook::verify(self::BODY, self::SIGNATURE, '');
    }

    #[Group('contract')]
    public function testEveryEventPayloadMatchesTheContract(): void
    {
        $documented = self::documentedEvents();
        $families = [EventType::ENTITY_RATING_CHANGED => RatingChangedEvent::class, EventType::AUTHOR_ERASED => AuthorErasedEvent::class];
        foreach (EventType::REVIEW_EVENTS as $type) {
            $families[$type] = ReviewEvent::class;
        }
        foreach (EventType::MEDIA_EVENTS as $type) {
            $families[$type] = MediaEvent::class;
        }
        self::assertEqualsCanonicalizing(array_keys($documented), array_keys($families), 'event types: events.md against EventType');
        foreach ($documented as $type => $fields) {
            $declared = self::fields($families[$type]);
            foreach ($fields as $field) {
                self::assertContains($field, $declared, "{$type}: {$families[$type]} lacks {$field}");
            }
        }
        // Each payload field appears in at least one documented type of its family.
        foreach (array_unique($families) as $class) {
            $documentedFields = [];
            foreach ($families as $type => $family) {
                if ($family === $class) {
                    $documentedFields = array_merge($documentedFields, $documented[$type]);
                }
            }
            foreach (self::fields($class) as $field) {
                self::assertContains($field, $documentedFields, "{$class}::{$field} is documented by no event type");
            }
        }
    }

    /** @return array<string, list<string>> event type => its payload field names (the vendored events.md) */
    private static function documentedEvents(): array
    {
        $raw = (string) file_get_contents(__DIR__ . '/../testdata/contract/events.md');
        preg_match_all('/^### ([a-z_.]+)$/m', $raw, $headings, PREG_OFFSET_CAPTURE);
        $out = [];
        foreach ($headings[1] as $i => [$name, $offset]) {
            $end = $headings[0][$i + 1][1] ?? strlen($raw);
            preg_match_all('/^\| `([a-z_]+)` \|/m', substr($raw, $offset, $end - $offset), $fields);
            $out[$name] = $fields[1];
        }

        return $out;
    }

    /**
     * @param class-string $class
     *
     * @return list<string> the payload's constructor parameters, as wire names
     */
    private static function fields(string $class): array
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        return array_map(
            static fn(\ReflectionParameter $p): string => strtolower((string) preg_replace('/[A-Z]/', '_$0', $p->getName())),
            $constructor->getParameters(),
        );
    }
}
