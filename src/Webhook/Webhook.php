<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Webhook;

use Octoverse\Octoreview\Exception\InvalidSignatureException;
use Octoverse\Octoreview\Exception\MalformedResponseException;

/** Webhook verification (the server's docs/events.md). */
final class Webhook
{
    /** The header carrying `t=<unix seconds>,v1=<hex HMAC-SHA256>`. */
    public const SIGNATURE_HEADER = 'X-Octoreview-Signature';

    /** How far a signature's timestamp may be from the clock, in seconds. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Checks a delivery's X-Octoreview-Signature against the raw request body and the webhook
     * secret (constant time, 300 s tolerance), then decodes the event. Verify before you parse,
     * and pass the body exactly as received (`file_get_contents('php://input')`).
     *
     * @param int|null $now the clock in unix seconds, for tests
     *
     * @throws InvalidSignatureException
     */
    public static function verify(string $body, string $signatureHeader, string $secret, int $toleranceSeconds = self::DEFAULT_TOLERANCE, ?int $now = null): Event
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('octoreview: the webhook secret is empty');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }
        if ($timestamp === null || !ctype_digit($timestamp) || $signatures === []) {
            throw new InvalidSignatureException('octoreview: invalid webhook signature: the header is not t=<seconds>,v1=<hex>');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $matched = false;
        foreach ($signatures as $candidate) {
            // hash_equals is constant time; compare every candidate so timing reveals nothing.
            $matched = hash_equals($expected, strtolower($candidate)) || $matched;
        }
        if (!$matched) {
            throw new InvalidSignatureException('octoreview: invalid webhook signature: no v1 signature matches this secret and body');
        }
        $skew = ($now ?? time()) - (int) $timestamp;
        if (abs($skew) > $toleranceSeconds) {
            throw new InvalidSignatureException(sprintf(
                'octoreview: invalid webhook signature: the timestamp is %d s from this clock (tolerance %d s)',
                $skew,
                $toleranceSeconds,
            ));
        }
        $event = json_decode($body, true);
        if (!is_array($event)) {
            throw new MalformedResponseException('octoreview: the webhook body is not a JSON object');
        }

        return Event::fromArray($event);
    }
}
