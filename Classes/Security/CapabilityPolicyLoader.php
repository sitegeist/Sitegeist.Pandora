<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Security;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Loader\LoaderInterface;
use Neos\Flow\Core\ApplicationContext;
use Neos\Flow\Package\PackageManager;
use Sitegeist\Pandora\Capability\CapabilityEnumerator;

/**
 * A Policy configuration loader that adds one privilege target per available MCP capability.
 *
 * Wraps the default PolicyLoader and appends the discovered capability targets to the loaded policy
 * configuration, so roles can grant capabilities as ordinary Policy.yaml privileges.
 */
#[Flow\Proxy(false)]
final class CapabilityPolicyLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $innerLoader,
        private readonly ConfigurationManager $configurationManager,
        private readonly PackageManager $packageManager,
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
            $scanDirs = CapabilityEnumerator::computeScanPaths($this->packageManager, $discoveryPaths);

            foreach (CapabilityEnumerator::discover($scanDirs) as $capability) {
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
