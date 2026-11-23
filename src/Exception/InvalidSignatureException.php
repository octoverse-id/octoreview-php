<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/**
 * A webhook whose signature header is missing, malformed, from the wrong secret, or outside the
 * tolerance. Reject the delivery with a 4xx.
 */
final class InvalidSignatureException extends OctoreviewException {}
