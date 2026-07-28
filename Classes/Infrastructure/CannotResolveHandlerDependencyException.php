<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Infrastructure;

use Psr\Container\ContainerExceptionInterface;

/**
 * Thrown by {@see CapabilityHandlerContainer} when a capability handler class cannot be built
 * because one of its constructor parameters is not resolvable.
 */
final class CannotResolveHandlerDependencyException extends \RuntimeException implements ContainerExceptionInterface
{
}
