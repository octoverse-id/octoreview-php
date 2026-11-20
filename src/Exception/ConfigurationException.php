<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Exception;

/** The client was built with a missing or invalid base URL, key or tenant. */
final class ConfigurationException extends OctoreviewException {}
