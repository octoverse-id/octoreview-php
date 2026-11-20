<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/** A success response whose body lacks a field this SDK needs, or has the wrong type. */
final class MalformedResponseException extends OctoreviewException {}
