<?php

namespace TecClub\ReleaseKit\Exceptions;

use RuntimeException;

/**
 * Base class for every rule violation the package reports.
 * Messages are safe to show to admins in a host UI.
 */
abstract class ReleaseKitException extends RuntimeException {}
