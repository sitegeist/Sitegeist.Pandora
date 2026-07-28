<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Security;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Loader\LoaderInterface;
use Neos\Flow\Core\ApplicationContext;
use Neos\Flow\Package\PackageManager;
use Psr\SimpleCache\CacheInterface;
use Sitegeist\Pandora\Capability\CapabilityEnumerator;
use Sitegeist\Pandora\Capability\PackageKeyResolver;

/**
 * A Policy configuration loader that adds one privilege target per available MCP capability.
 *
 * Wraps the default PolicyLoader and appends the discovered capability targets to the loaded policy
 * configuration, so roles can grant capabilities as ordinary Policy.yaml privileges.
 */
#[Flow\Proxy(false)]
final class CapabilityPolicyLoader implements LoaderInterface
{
    /**
     * @param \Closure(): CacheInterface $capabilitiesCacheFactory resolves the shared MCP_Capabilities
     *        cache lazily: this loader is built during the configuration boot step, before the
     *        CacheManager early instance exists, but load() only runs once POLICY is requested (after
     *        boot), by which point the factory can hand out the same cache the server build uses.
     */
    public function __construct(
        private readonly LoaderInterface $innerLoader,
        private readonly ConfigurationManager $configurationManager,
        private readonly PackageManager $packageManager,
        private readonly \Closure $capabilitiesCacheFactory,
    ) {
    }

    /**
     * @param array<string, \Neos\Flow\Package\FlowPackageInterface> $packages
     * @return array<string, mixed>
     */
    public function load(array $packages, ApplicationContext $context): array
    {
        $configuration = $this->innerLoader->load($packages, $context);

        try {
            $discoveryPaths = $this->configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                'Sitegeist.Pandora.capabilities.discoveryPaths'
            ) ?? [];
            $excludeDirs = $this->configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                'Sitegeist.Pandora.capabilities.excludeDirs'
            ) ?? [];
            $scanDirs = CapabilityEnumerator::computeScanPaths($this->packageManager, $discoveryPaths);

            // Reuse the server build's discovery cache so a policy (re)build does not re-scan every
            // package's autoload roots uncached. Falls back to uncached if the cache is not yet
            // resolvable, rather than losing discovery (and thus all capability targets) entirely.
            $cache = null;
            try {
                $cache = ($this->capabilitiesCacheFactory)();
            } catch (\Throwable) {
            }

            $packageKeyResolver = new PackageKeyResolver($this->packageManager);
            foreach (CapabilityEnumerator::discover($scanDirs, $packageKeyResolver, $excludeDirs, $cache) as $capability) {
                $configuration['privilegeTargets'][McpCapabilityPrivilege::class][$capability->getPrivilegeTargetIdentifier()] ??= [
                    'matcher' => $capability->getMatcher(),
                    'label' => $capability->getLabel(),
                ];
            }
        } catch (\Throwable) {
            // Discovery must never break policy loading; on failure capabilities simply stay ungrantable.
        }

        return $configuration;
    }
}
