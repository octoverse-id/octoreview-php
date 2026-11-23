<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

/**
 * Options of one upload helper call. Immutable: `UploadOptions::create()->withPosition(0)`.
 */
final class UploadOptions
{
    /** @param (\Closure(int, int): void)|null $progress */
    private function __construct(
        public readonly ?\Closure $progress = null,
        public readonly ?string $clientUploadId = null,
        public readonly ?int $position = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?float $durationSeconds = null,
        public readonly ?CallOptions $callOptions = null,
    ) {}

    public static function create(): self
    {
        return new self();
    }

    /** Reports the bytes sent so far during the PUT, and the total: `fn(int $sent, int $total)`. */
    public function withProgress(callable $progress): self
    {
        return $this->copy(['progress' => $progress(...)]);
    }

    /** Makes the intent replayable across processes (one is generated otherwise). */
    public function withClientUploadId(string $clientUploadId): self
    {
        return $this->copy(['clientUploadId' => $clientUploadId]);
    }

    /** Orders the media item within its review. */
    public function withPosition(int $position): self
    {
        return $this->copy(['position' => $position]);
    }

    /** Records an image's or video's pixel size. */
    public function withDimensions(int $width, int $height): self
    {
        return $this->copy(['width' => $width, 'height' => $height]);
    }

    /** Records a video's length. */
    public function withDuration(float $seconds): self
    {
        return $this->copy(['durationSeconds' => $seconds]);
    }

    /** Call options (application, namespace, actor) for the intent, complete and cleanup calls. */
    public function withCallOptions(CallOptions $options): self
    {
        return $this->copy(['callOptions' => $options]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
