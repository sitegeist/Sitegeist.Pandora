<?php

declare(strict_types=1);

namespace Sitegeist\Pandora;

use Mcp\Capability\Registry;
use Mcp\Capability\RegistryInterface;
use Mcp\Server;
use Mcp\Server\Builder as ServerBuilder;
use Mcp\Server\Session\SessionStoreInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Package\PackageManager;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Sitegeist\Pandora\Capability\CapabilityEnumerator;

#[Flow\Scope('singleton')]
final class McpServerBuilderFactory
{
    /**
     * @param array<string,bool> $discoveryPaths
     */
    public function __construct(
        private readonly array $discoveryPaths,
        private readonly CacheInterface $cache,
        private readonly PackageManager $packageManager,
        private readonly LoggerInterface $logger,
        private readonly SessionStoreInterface $sessionStore,
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    public function create(): ServerBuilder
    {
        $scanDirs = CapabilityEnumerator::computeScanPaths($this->packageManager, $this->discoveryPaths);

        return Server::builder()
            ->setDiscovery(FLOW_PATH_ROOT, $scanDirs, [], $this->cache)
            ->setContainer($this->objectManager)
            ->setLogger($this->logger)
            ->setSession($this->sessionStore);
    }

    /**
     * Builds a server together with the registry it is built from.
     *
     * Passing an explicit registry lets the SDK populate it via discovery while we keep the
     * reference - the single seam through which both capability enumeration (security) and
     * capability filtering (server build) operate on the exact same set the server exposes.
     */
    public function buildServer(?RegistryInterface $registry = null): BuiltServer
    {
        $registry ??= new Registry(logger: $this->logger);
        $server = $this->create()
            ->setRegistry($registry)
            ->build();

        return new BuiltServer($server, $registry);
    }
}
