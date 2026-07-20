<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Capability;

use Mcp\Capability\Registry;
use Mcp\Server;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\PackageManager;
use Psr\SimpleCache\CacheInterface;

/**
 * Stateless helpers to compute the discovery scan paths and to discover MCP capabilities.
 */
#[Flow\Proxy(false)]
final class CapabilityEnumerator
{
    /**
     * The class directories to scan, mirroring McpServerBuilderFactory: every non-library Flow
     * package with a Classes directory, plus/minus the explicit discoveryPaths overrides.
     *
     * @param array<string,bool> $discoveryPaths
     * @return list<string>
     */
    public static function computeScanPaths(PackageManager $packageManager, array $discoveryPaths): array
    {
        $scanDirs = array_keys(array_filter($discoveryPaths));
        foreach ($packageManager->getAvailablePackages() as $package) {
            $relativePackagePath = \mb_substr($package->getPackagePath(), \mb_strlen(FLOW_PATH_ROOT));
            $relativeClassPath = $relativePackagePath . 'Classes';
            if (
                !\str_starts_with($relativePackagePath, 'Packages/Libraries')
                && is_dir($package->getPackagePath() . 'Classes')
                && ($discoveryPaths[$relativeClassPath] ?? null) !== false
            ) {
                $scanDirs[] = $relativeClassPath;
            }
        }

        return $scanDirs;
    }

    /**
     * Discovers all MCP capabilities by building a throwaway registry (no container needed).
     *
     * @param list<string> $scanDirs
     */
    public static function discover(array $scanDirs, ?CacheInterface $cache = null): CapabilityDescriptors
    {
        $registry = new Registry();
        Server::builder()
            ->setDiscovery(FLOW_PATH_ROOT, $scanDirs, [], $cache)
            ->setRegistry($registry)
            ->build();

        return CapabilityDescriptors::createFromRegistry($registry);
    }
}
