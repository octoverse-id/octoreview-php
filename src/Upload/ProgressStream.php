<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Upload;

use Psr\Http\Message\StreamInterface;

/**
 * Reports the bytes read so far while the HTTP client sends a PUT body. Parameters are untyped,
 * as in Guzzle's own streams, so it implements both psr/http-message 1.1 and 2.0.
 *
 * @internal
 */
final class ProgressStream implements StreamInterface
{
    private int $sent = 0;

    /** @param \Closure(int, int): void $report */
    public function __construct(private readonly StreamInterface $inner, private readonly int $total, private readonly \Closure $report) {}

    /** @param int $length */
    public function read($length): string
    {
        $data = $this->inner->read($length);
        if ($data !== '') {
            $this->sent += strlen($data);
            ($this->report)($this->sent, $this->total);
        }

        return $data;
    }

    public function rewind(): void
    {
        $this->sent = 0;
        $this->inner->rewind();
    }

    /**
     * @param int $offset
     * @param int $whence
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->inner->seek($offset, $whence);
        $this->sent = $this->inner->tell();
    }

    public function __toString(): string
    {
        try {
            $this->rewind();

            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function getContents(): string
    {
        $out = '';
        while (!$this->eof()) {
            $chunk = $this->read(65536);
            if ($chunk === '') {
                break;
            }
            $out .= $chunk;
        }

        return $out;
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function detach()
    {
        return $this->inner->detach();
    }

    public function getSize(): ?int
    {
        return $this->inner->getSize();
    }

    public function tell(): int
    {
        return $this->inner->tell();
    }

    public function eof(): bool
    {
        return $this->inner->eof();
    }

    public function isSeekable(): bool
    {
        return $this->inner->isSeekable();
    }

    public function isWritable(): bool
    {
        return false;
    }

    /** @param string $string */
    public function write($string): int
    {
        throw new \RuntimeException('octoreview: an upload body is read-only');
    }

    public function isReadable(): bool
    {
        return true;
    }

    /** @param string|null $key */
    public function getMetadata($key = null)
    {
        return $this->inner->getMetadata($key);
    }
}
