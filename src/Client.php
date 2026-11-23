<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

use GuzzleHttp\Client as GuzzleClient;
use Octoverse\Octoreview\Exception\ConfigurationException;
use Octoverse\Octoreview\Resource\AuditLogsService;
use Octoverse\Octoreview\Resource\AuthorsService;
use Octoverse\Octoreview\Resource\EntitiesService;
use Octoverse\Octoreview\Resource\HealthService;
use Octoverse\Octoreview\Resource\MediaService;
use Octoverse\Octoreview\Resource\RatingsService;
use Octoverse\Octoreview\Resource\ReviewsService;
use Octoverse\Octoreview\Resource\SettingsService;
use Octoverse\Octoreview\Resource\WebhooksService;
use Octoverse\Octoreview\Upload\Uploader;

/**
 * The Octoreview client. One instance per service key; it is immutable and safe to share.
 *
 * ```php
 * $client = new Client('https://reviews.example.com', getenv('OCTOREVIEW_API_KEY'), 'mbizmarket',
 *     (new ClientOptions())->withApplication('storefront'));
 * $review = $client->reviews()->create('user-42', 5, ['entity_type' => 'product', 'external_id' => 'sku-1']);
 * ```
 */
final class Client
{
    private readonly Transport $transport;
    private readonly EntitiesService $entities;
    private readonly Uploader $uploader;

    public function __construct(string $baseUrl, string $apiKey, string $tenantId, ClientOptions $options = new ClientOptions())
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
            throw new ConfigurationException(sprintf('octoreview: base URL "%s" must be an absolute http(s) URL', Redactor::url($baseUrl)));
        }
        if ($apiKey === '') {
            throw new ConfigurationException('octoreview: the API key is empty; pass a service key (ocr_...)');
        }
        if ($tenantId === '') {
            throw new ConfigurationException('octoreview: the tenant id is empty');
        }
        $http = $options->httpClient ?? new GuzzleClient([
            'timeout' => $options->timeoutSeconds,
            'connect_timeout' => min(10.0, $options->timeoutSeconds),
        ]);
        $this->transport = new Transport($baseUrl, $apiKey, $tenantId, $options, $http);
        $this->entities = new EntitiesService($this->transport);
        $this->uploader = new Uploader($options->uploadHttpClient ?? self::uploadClient(), $this->transport);
    }

    /**
     * The media PUT's client: no redirects (PSR-18 sendRequest never follows them), no total
     * timeout so a large video can take its time, but a stalled transfer (under 1 byte/s for
     * 60 s) fails, and never `Expect: 100-continue`.
     */
    private static function uploadClient(): GuzzleClient
    {
        $config = ['connect_timeout' => 10, 'timeout' => 0, 'expect' => false];
        if (defined('CURLOPT_LOW_SPEED_LIMIT') && defined('CURLOPT_LOW_SPEED_TIME')) {
            $config['curl'] = [CURLOPT_LOW_SPEED_LIMIT => 1, CURLOPT_LOW_SPEED_TIME => 60];
        }

        return new GuzzleClient($config);
    }

    /**
     * Builds a client from OCTOREVIEW_BASE_URL, OCTOREVIEW_API_KEY, OCTOREVIEW_TENANT_ID and the
     * optional OCTOREVIEW_APPLICATION_ID (the server's compose stack writes all four with
     * `make dev-env`). An application set in `$options` wins over the variable.
     *
     * @param array<string, string>|null $env the variables to read; null reads the process environment
     */
    public static function fromEnv(ClientOptions $options = new ClientOptions(), ?array $env = null): self
    {
        $value = static function (string $name) use ($env): string {
            $v = $env === null ? getenv($name) : ($env[$name] ?? false);

            return is_string($v) ? trim($v) : '';
        };
        $missing = array_values(array_filter(
            ['OCTOREVIEW_BASE_URL', 'OCTOREVIEW_API_KEY', 'OCTOREVIEW_TENANT_ID'],
            static fn(string $name): bool => $value($name) === '',
        ));
        if ($missing !== []) {
            throw new ConfigurationException('octoreview: set ' . implode(', ', $missing));
        }
        $application = $value('OCTOREVIEW_APPLICATION_ID');
        if ($options->applicationId === null && $application !== '') {
            $options = $options->withApplication($application);
        }

        return new self($value('OCTOREVIEW_BASE_URL'), $value('OCTOREVIEW_API_KEY'), $value('OCTOREVIEW_TENANT_ID'), $options);
    }

    public function health(): HealthService
    {
        return new HealthService($this->transport);
    }

    public function entities(): EntitiesService
    {
        return $this->entities;
    }

    public function ratings(): RatingsService
    {
        return new RatingsService($this->transport, $this->entities);
    }

    public function settings(): SettingsService
    {
        return new SettingsService($this->transport);
    }

    public function reviews(): ReviewsService
    {
        return new ReviewsService($this->transport);
    }

    public function media(): MediaService
    {
        return new MediaService($this->transport, $this->uploader);
    }

    public function authors(): AuthorsService
    {
        return new AuthorsService($this->transport);
    }

    public function auditLogs(): AuditLogsService
    {
        return new AuditLogsService($this->transport);
    }

    /** Webhook verification; it uses no client state, so Webhook::verify() works without a client too. */
    public function webhooks(): WebhooksService
    {
        return new WebhooksService();
    }
}
