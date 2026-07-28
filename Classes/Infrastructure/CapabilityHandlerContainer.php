<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Infrastructure;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\ObjectManagement\Proxy\ProxyInterface;
use Psr\Container\ContainerInterface;

/**
 * The container the MCP SDK resolves capability handler instances from.
 *
 * Capability classes must carry #[Flow\Proxy(false)]: the SDK's discoverer skips every method whose
 * declaring class differs from the reflected class, and a Flow proxy declares the annotated methods
 * in a generated "<Class>_Original" parent - so a proxied capability class silently loses ALL of its
 * capabilities. Without a proxy, though, Flow cannot inject constructor dependencies either, because
 * that injection is generated INTO the proxy: the ObjectManager falls back to "new $className()" and
 * a handler with constructor dependencies dies with an ArgumentCountError at the first tool call.
 *
 * This container closes that gap. Everything Flow can build itself is delegated to it - only classes
 * it demonstrably cannot build, non-proxied ones with required constructor parameters, are
 * instantiated here, with their dependencies resolved through this same container so a non-proxied
 * dependency of a non-proxied handler works too.
 *
 * Note that a class instantiated here bypasses Flow's scope handling and is therefore built afresh
 * per resolution: an explicit #[Flow\Scope('singleton')] on a non-proxied capability class would not
 * be honoured (the handler classes are stateless services, so this costs nothing today). Ids that
 * resolve to nothing at all surface as Flow's own UnknownObjectException rather than a PSR-11
 * not-found exception; the SDK consults has() before get(), so that path is unreachable from it.
 */
#[Flow\Scope('singleton')]
final class CapabilityHandlerContainer implements ContainerInterface
{
    /**
     * Class names currently being resolved, so a dependency cycle becomes a readable error instead
     * of an exhausted stack.
     *
     * @var array<string, true>
     */
    private array $classesBeingResolved = [];

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    public function has(string $id): bool
    {
        return $this->objectManager->has($id) || class_exists($id) || interface_exists($id);
    }

    public function get(string $id): object
    {
        if (!class_exists($id) || is_a($id, ProxyInterface::class, true)) {
            // Interfaces, aliases and proxied classes: Flow resolves all of those correctly by
            // itself - the interface via its configured implementation, the proxy via the
            // constructor injection generated into it.
            return $this->objectManager->get($id);
        }

        $constructor = (new \ReflectionClass($id))->getConstructor();
        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            // Nothing that needs injecting, so leave it to Flow and keep its scope handling.
            return $this->objectManager->get($id);
        }

        return $this->instantiate($id, $constructor);
    }

    private function instantiate(string $className, \ReflectionMethod $constructor): object
    {
        if (isset($this->classesBeingResolved[$className])) {
            throw new CannotResolveHandlerDependencyException(sprintf(
                'Circular dependency detected while resolving the capability handler "%s".',
                $className
            ), 1785258001);
        }

        $this->classesBeingResolved[$className] = true;
        try {
            $arguments = [];
            foreach ($constructor->getParameters() as $parameter) {
                $arguments[] = $this->resolveParameter($className, $parameter);
            }

            return new $className(...$arguments);
        } finally {
            unset($this->classesBeingResolved[$className]);
        }
    }

    private function resolveParameter(string $className, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new CannotResolveHandlerDependencyException(sprintf(
            'Cannot resolve constructor parameter $%s of the capability handler "%s". A class Flow '
            . 'cannot build itself only supports object types and parameters carrying a default '
            . 'value here - settings and other scalar arguments cannot be injected, so give the '
            . 'parameter a default value and read the value inside the handler instead.',
            $parameter->getName(),
            $className
        ), 1785258002);
    }
}
