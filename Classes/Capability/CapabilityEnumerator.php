<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Capability;

use Mcp\Capability\Registry;
use Mcp\Server;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\GenericPackage;
use Neos\Flow\Package\PackageManager;
use Psr\SimpleCache\CacheInterface;

/**
 * Stateless helpers to compute the discovery scan paths and to discover MCP capabilities.
 */
#[Flow\Proxy(false)]
final class CapabilityEnumerator
{
    /**
     * The directories to scan for MCP capabilities: the declared autoload roots (PSR-0/PSR-4) of
     * every Flow package, plus/minus the explicit discoveryPaths overrides. Reading the declared
     * roots rather than a hardcoded Classes/ also covers packages deviating from that convention.
     *
     * Packages outside the Flow package set are scanned only when opted in via discoveryPaths,
     * because discovery is not a read-only operation: identifying capabilities requires loading
     * each candidate class, which executes its file. Third-party code is written for its own
     * runtime conditions - it may refuse to load at all when an optional dependency is missing -
     * and the SDK aborts the entire scan on the first file that throws. Scanning everything
     * installed therefore makes our capability set hostage to unrelated code. Exposure is not the
     * concern here (deny-by-default covers that), robustness is.
     *
     * @param array<string,bool> $discoveryPaths
     * @return list<string>
     */
    public static function computeScanPaths(PackageManager $packageManager, array $discoveryPaths): array
    {
        $scanDirs = array_keys(array_filter($discoveryPaths));
        foreach ($packageManager->getFlowPackages() as $package) {
            if (!$package instanceof GenericPackage) {
                continue;
            }
            foreach ($package->getFlattenedAutoloadConfiguration() as $autoloadConfiguration) {
                $classPath = (string)$autoloadConfiguration['classPath'];
                if (!\str_starts_with($classPath, FLOW_PATH_ROOT) || !is_dir($classPath)) {
                    continue;
                }
                $relativeClassPath = rtrim(\mb_substr($classPath, \mb_strlen(FLOW_PATH_ROOT)), '/');
                if (($discoveryPaths[$relativeClassPath] ?? null) !== false) {
                    $scanDirs[] = $relativeClassPath;
                }
            }
        }

        return array_values(array_unique($scanDirs));
    }

    /**
     * Discovers all MCP capabilities by building a throwaway registry (no container needed).
     *
     * @param list<string> $scanDirs
     * @param list<string> $excludeDirs directories (relative to each scan root) to exclude, e.g. "examples"
     */
    public static function discover(
        array $scanDirs,
        PackageKeyResolver $packageKeyResolver,
        array $excludeDirs = [],
        ?CacheInterface $cache = null
    ): CapabilityDescriptors {
        $registry = new Registry();
        Server::builder()
            ->setDiscovery(FLOW_PATH_ROOT, $scanDirs, $excludeDirs, $cache)
            ->setRegistry($registry)
            ->build();

        return CapabilityDescriptors::createFromRegistry($registry, $packageKeyResolver);
    }
}
