<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Resource;

use Octoverse\Octoreview\CallOptions;
use Octoverse\Octoreview\Model\ApplicationSettings;

/** An application's media policy defaults, moderation mode and storage quota. */
final class SettingsService extends Service
{
    private const FIELDS = [
        'max_images', 'max_videos', 'max_image_bytes', 'max_video_bytes',
        'allowed_content_types', 'moderation_mode', 'max_storage_bytes',
    ];

    /** A null application id uses CallOptions::forApplication(), then the client default. */
    public function get(?string $applicationId = null, ?CallOptions $options = null): ApplicationSettings
    {
        return $this->one('getApplicationSettings', [$this->application($applicationId, $options)], [], $options, ApplicationSettings::fromArray(...));
    }

    /**
     * Replaces the settings (retry-safe). A field left out is sent as null and inherits the
     * server default.
     *
     * @param array{max_images?: ?int, max_videos?: ?int, max_image_bytes?: ?int, max_video_bytes?: ?int, allowed_content_types?: ?list<string>, moderation_mode?: ?string, max_storage_bytes?: ?int} $settings
     */
    public function put(?string $applicationId, array $settings, ?CallOptions $options = null): ApplicationSettings
    {
        return $this->one(
            'putApplicationSettings',
            [$this->application($applicationId, $options)],
            $settings + array_fill_keys(self::FIELDS, null),
            $options,
            ApplicationSettings::fromArray(...),
        );
    }

    private function application(?string $applicationId, ?CallOptions $options): string
    {
        $application = $applicationId ?? $this->transport->application($options);
        if ($application === null || $application === '') {
            throw new \InvalidArgumentException('octoreview: settings need an application id, CallOptions::forApplication() or a client default');
        }

        return $application;
    }
}
