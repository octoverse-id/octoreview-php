<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/** Every exception this SDK throws. Messages never contain the API key or a signed URL. */
abstract class OctoreviewException extends \RuntimeException {}
