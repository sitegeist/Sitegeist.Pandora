<?php

declare(strict_types=1);

namespace Sitegeist\Pandora;

use Mcp\Server;
use Mcp\Server\Builder as ServerBuilder;
use Mcp\Server\Session\SessionStoreInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Package\PackageManager;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

#[Flow\Scope('singleton')]
final readonly class McpServerBuilderFactory
{
    /**
     * @param array<string,bool> $discoveryPaths
     * @param CacheInterface $cache
     */
    public function __construct(
        private array $discoveryPaths,
        private CacheInterface $cache,
        private PackageManager $packageManager,
        private LoggerInterface $logger,
        private SessionStoreInterface $sessionStore,
        private ObjectManagerInterface $objectManager,
    ) {;
    }

    public function create(): ServerBuilder
    {
        $scanDirs = array_keys(array_filter($this->discoveryPaths));
        foreach ($this->packageManager->getAvailablePackages() as $package) {
            $relativePackagePath = \mb_substr($package->getPackagePath(), \mb_strlen(FLOW_PATH_ROOT));
            $relativeClassPath = $relativePackagePath . 'Classes';
            if (
                // libraries are not included by default as we don't know where their classes are located
                !\str_starts_with($relativePackagePath, 'Packages/Libraries')
                // Flow packages without a Classes directory are omitted
                && is_dir($package->getPackagePath() . 'Classes')
                // Explicitly excluded paths are ignored
                && ($this->discoveryPaths[$relativeClassPath] ?? null) !== false
            ) {
                $scanDirs[] = $relativeClassPath;
            }
        }

        return Server::builder()
            ->setDiscovery(FLOW_PATH_ROOT, $scanDirs, [], $this->cache)
            ->setContainer($this->objectManager)
            ->setLogger($this->logger)
            ->setSession($this->sessionStore);
    }
}
