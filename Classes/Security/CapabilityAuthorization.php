<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Security;

use Mcp\Capability\RegistryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Flow\Security\Policy\Role;
use Sitegeist\Pandora\Capability\CapabilityDescriptor;
use Sitegeist\Pandora\Capability\CapabilityDescriptors;

/**
 * Removes MCP capabilities the current user is not granted access to from a built server's registry,
 * pruning it to the granted subset before the server serves any request (deny-by-default).
 */
#[Flow\Scope('singleton')]
final class CapabilityAuthorization
{
    public function __construct(
        private readonly PrivilegeManagerInterface $privilegeManager,
    ) {
    }

    /**
     * Prunes the registry to the capabilities granted for the current security context.
     */
    public function restrictToGranted(RegistryInterface $registry): void
    {
        CapabilityDescriptors::createFromRegistry($registry)
            ->filter(fn (CapabilityDescriptor $descriptor): bool => !$this->privilegeManager->isGranted(
                McpCapabilityPrivilege::class,
                McpCapabilityPrivilegeSubject::createFromDescriptor($descriptor)
            ))
            ->unregisterFrom($registry);
    }

    /**
     * Prunes the registry to the capabilities granted for an explicit set of roles.
     *
     * Used for diagnostics/simulation; the runtime path uses {@see self::restrictToGranted()}.
     *
     * @param array<Role> $roles
     */
    public function restrictToGrantedForRoles(RegistryInterface $registry, array $roles): void
    {
        CapabilityDescriptors::createFromRegistry($registry)
            ->filter(fn (CapabilityDescriptor $descriptor): bool => !$this->privilegeManager->isGrantedForRoles(
                $roles,
                McpCapabilityPrivilege::class,
                McpCapabilityPrivilegeSubject::createFromDescriptor($descriptor)
            ))
            ->unregisterFrom($registry);
    }
}
