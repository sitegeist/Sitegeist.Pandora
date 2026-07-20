<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Flow\Security\Exception\NoSuchRoleException;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Flow\Security\Policy\Role;
use Sitegeist\Pandora\Capability\CapabilityDescriptors;
use Sitegeist\Pandora\McpServerBuilderFactory;
use Sitegeist\Pandora\Security\CapabilityAuthorization;
use Sitegeist\Pandora\Security\McpCapabilityPrivilege;

/**
 * Diagnostics for the MCP capabilities available in this installation.
 */
class CapabilityCommandController extends CommandController
{
    public function __construct(
        private readonly CapabilityAuthorization $capabilityAuthorization,
        private readonly McpServerBuilderFactory $serverBuilderFactory,
        private readonly PolicyService $policyService,
    ) {
        parent::__construct();
    }

    /**
     * Lists MCP capabilities and whether they would be exposed for the given role(s).
     *
     * Without --role you see the deny-by-default baseline (nothing granted); pass a role to see
     * what that role unlocks.
     *
     * @param string|null $role Role identifier to simulate, e.g. "Sitegeist.Pandora:ContentAgent"
     */
    public function listCommand(?string $role = null): void
    {
        try {
            $roles = $this->rolesToSimulate($role);
        } catch (NoSuchRoleException $exception) {
            $this->outputLine('<error>%s</error>', [$exception->getMessage()]);
            $this->quit(1);
        }

        $registry = $this->serverBuilderFactory->buildServer()->registry;
        $allCapabilities = CapabilityDescriptors::createFromRegistry($registry);

        // Apply the real filter, then re-read the registry: the survivors are what the server exposes.
        $this->capabilityAuthorization->restrictToGrantedForRoles($registry, $roles);
        $grantedKeys = array_fill_keys(CapabilityDescriptors::createFromRegistry($registry)->getMatchers(), true);

        $rows = [];
        foreach ($allCapabilities as $capability) {
            $rows[] = [
                $capability->type->value,
                $capability->name,
                $capability->getPrivilegeTargetIdentifier(),
                isset($grantedKeys[$capability->getMatcher()]) ? '<success>exposed</success>' : 'hidden',
            ];
        }

        $this->output->outputTable($rows, ['Type', 'Name', 'Privilege target', 'For these roles']);
        $this->outputLine();
        $this->outputLine('Simulated roles: <info>%s</info>', [implode(', ', array_map(
            static fn (Role $r) => $r->getIdentifier(),
            $roles
        ))]);
        $this->outputLine(
            '<info>%d</info> of <info>%d</info> capabilities exposed (privilege type "%s").',
            [count($grantedKeys), count($allCapabilities), McpCapabilityPrivilege::class]
        );
    }

    /**
     * @return array<Role>
     * @throws NoSuchRoleException
     */
    private function rolesToSimulate(?string $roleIdentifier): array
    {
        $roles = [$this->policyService->getRole('Neos.Flow:Everybody')];
        if ($roleIdentifier !== null) {
            $role = $this->policyService->getRole($roleIdentifier);
            $roles[] = $role;
            $roles = array_merge($roles, $role->getAllParentRoles());
        }

        return $roles;
    }
}
