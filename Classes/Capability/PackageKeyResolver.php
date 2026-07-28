<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Capability;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\PackageKeyAwareInterface;
use Neos\Flow\Package\PackageManager;

/**
 * Resolves the Flow package key of the package that provides a discovered MCP capability, based on
 * the capability's handler class.
 *
 * Flow derives a privilege-key-conform package key for *every* package - Flow package and generic
 * composer package alike (see {@see \Neos\Flow\Package\FlowPackageKey::deriveFromManifestOrPath()}) -
 * so the resolved key can be reused verbatim as the package segment of a capability's matcher and
 * privilege target identifier. This is what makes capability identities globally unique across
 * packages, so two packages exposing e.g. a "search" tool no longer collide on the privilege side.
 *
 * Not a Flow-managed object: it is constructed manually during policy loading (early bootstrap,
 * before DI is available), so it takes its {@see PackageManager} explicitly.
 */
#[Flow\Proxy(false)]
final class PackageKeyResolver
{
    /**
     * Trimmed autoload namespace (no leading/trailing backslash) => package key.
     * Longest matching namespace wins. Built lazily on first resolution.
     *
     * @var array<string, string>|null
     */
    private ?array $namespaceToPackageKey = null;

    /**
     * Package key returned when a handler cannot be attributed to a package (e.g. a closure handler,
     * or a class outside any known autoload namespace). Discovered capabilities always live inside a
     * scanned package's namespace, so this is a defensive fallback that should not occur in practice.
     */
    public const UNKNOWN_PACKAGE_KEY = 'Unknown.Package';

    public function __construct(
        private readonly PackageManager $packageManager,
    ) {
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler a registry reference handler
     */
    public function resolveForHandler(\Closure|array|string $handler): string
    {
        return $this->resolveForClass(self::handlerClassName($handler));
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private static function handlerClassName(\Closure|array|string $handler): ?string
    {
        if (\is_array($handler)) {
            $target = $handler[0];

            return \is_object($target) ? $target::class : $target;
        }

        if (\is_string($handler) && (class_exists($handler) || interface_exists($handler))) {
            return $handler;
        }

        return null;
    }

    private function resolveForClass(?string $className): string
    {
        if ($className === null) {
            return self::UNKNOWN_PACKAGE_KEY;
        }

        $className = ltrim($className, '\\');

        $bestNamespace = null;
        foreach ($this->getNamespaceMap() as $namespace => $packageKey) {
            if ($className === $namespace || str_starts_with($className, $namespace . '\\')) {
                if ($bestNamespace === null || \strlen($namespace) > \strlen($bestNamespace)) {
                    $bestNamespace = $namespace;
                }
            }
        }

        return $bestNamespace !== null ? $this->getNamespaceMap()[$bestNamespace] : self::UNKNOWN_PACKAGE_KEY;
    }

    /**
     * @return array<string, string>
     */
    private function getNamespaceMap(): array
    {
        if ($this->namespaceToPackageKey !== null) {
            return $this->namespaceToPackageKey;
        }

        $map = [];
        foreach ($this->packageManager->getAvailablePackages() as $package) {
            if (!$package instanceof PackageKeyAwareInterface) {
                continue;
            }
            $packageKey = $package->getPackageKey();
            foreach ($package->getNamespaces() as $namespace) {
                $map[trim($namespace, '\\')] = $packageKey;
            }
        }

        return $this->namespaceToPackageKey = $map;
    }
}
