<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\ResponseMeta;

/** An upload intent's answer; `upload` is null when a replayed intent is no longer uploadable. */
final class MediaUpload
{
    public function __construct(
        public readonly Media $media,
        public readonly ?UploadTarget $upload,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta()): self
    {
        return new self(
            Media::fromArray(Data::map($data, 'media')),
            isset($data['upload']) && is_array($data['upload']) ? UploadTarget::fromArray($data['upload']) : null,
            $meta,
        );
    }
}
