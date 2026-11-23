<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Upload;

use Octoverse\Octoreview\Exception\OctoreviewException;

/**
 * The storage PUT answered an error; MediaService turns it into an UploadException.
 *
 * @internal
 */
final class PutFailedException extends OctoreviewException
{
    public function __construct(public readonly int $status, public readonly ?string $providerCode, public readonly ?string $fix)
    {
        parent::__construct($providerCode === null
            ? sprintf('the storage PUT answered HTTP %d', $status)
            : sprintf('the storage PUT answered HTTP %d (%s)', $status, $providerCode));
    }
}
