<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/** A conditional read (CallOptions::ifNoneMatch) found the resource unchanged (HTTP 304). */
final class NotModifiedException extends OctoreviewException {}
