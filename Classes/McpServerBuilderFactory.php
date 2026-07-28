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
use Neos\Flow\Security\Policy\Role;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Sitegeist\Pandora\Capability\CapabilityEnumerator;
use Sitegeist\Pandora\Infrastructure\CapabilityHandlerContainer;
use Sitegeist\Pandora\Security\CapabilityAuthorization;

#[Flow\Scope('singleton')]
final class McpServerBuilderFactory
{
    /**
     * @param array<string,bool> $discoveryPaths
     * @param list<string> $excludeDirs
     */
    public function __construct(
        private readonly array $discoveryPaths,
        private readonly array $excludeDirs,
        private readonly CacheInterface $cache,
        private readonly PackageManager $packageManager,
        private readonly LoggerInterface $logger,
        private readonly SessionStoreInterface $sessionStore,
        private readonly ObjectManagerInterface $objectManager,
        private readonly CapabilityAuthorization $capabilityAuthorization,
        private readonly CapabilityHandlerContainer $capabilityHandlerContainer,
    ) {
    }

    public function create(): ServerBuilder
    {
        $scanDirs = CapabilityEnumerator::computeScanPaths($this->packageManager, $this->discoveryPaths);

        return Server::builder()
            ->setDiscovery(FLOW_PATH_ROOT, $scanDirs, $this->excludeDirs, $this->cache)
            // Not the ObjectManager itself: capability classes are #[Flow\Proxy(false)] (the SDK's
            // discoverer requires it) and Flow cannot inject their constructor dependencies without
            // a proxy - see CapabilityHandlerContainer.
            ->setContainer($this->capabilityHandlerContainer)
            ->setLogger($this->logger)
            ->setSession($this->sessionStore);
    }

    /**
     * Builds a server pruned to the capabilities the CURRENT security context is granted.
     *
     * This is the secure default: the returned server only ever exposes capabilities the
     * authenticated user may access (deny-by-default). Use it for anything serving a real
     * request; reach for {@see self::buildInsecureServer()} only when the unfiltered set is
     * genuinely required. Authorization is baked into the build here so it cannot be forgotten.
     */
    public function buildServer(): BuiltServer
    {
        $builtServer = $this->buildInsecureServer();
        $this->capabilityAuthorization->restrictToGranted($builtServer->registry);

        return $builtServer;
    }

    /**
     * Builds a server pruned to the capabilities granted for an EXPLICIT set of roles, rather than
     * the current security context. For diagnostics/simulation (see the capability command).
     *
     * @param array<Role> $roles
     */
    public function buildServerForRoles(array $roles): BuiltServer
    {
        $builtServer = $this->buildInsecureServer();
        $this->capabilityAuthorization->restrictToGrantedForRoles($builtServer->registry, $roles);

        return $builtServer;
    }

    /**
     * Builds the full, UNFILTERED server together with the registry it is built from: every
     * discovered capability is exposed, no authorization applied. Only for callers that genuinely
     * need the complete set (capability enumeration/diagnostics) - serving this to a request would
     * bypass deny-by-default.
     */
    public function buildInsecureServer(?RegistryInterface $registry = null): BuiltServer
    {
        $registry ??= new Registry(logger: $this->logger);
        $server = $this->create()
            ->setRegistry($registry)
            ->build();

        return new BuiltServer($server, $registry);
    }
}
