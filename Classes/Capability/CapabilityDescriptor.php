<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Capability;

use Neos\Flow\Annotations as Flow;

/**
 * An immutable descriptor identifying a single MCP capability by the {@see CapabilityType}, the
 * Flow package key of the package that provides it, and its MCP name (e.g. "find-children").
 *
 * The $packageKey qualifies the capability identity so that two packages exposing an identically
 * named capability do not collide on the privilege side. $registryKey is the key the capability is
 * registered under: the name for tools and prompts, the URI / URI template for resources and
 * resource templates.
 */
#[Flow\Proxy(false)]
final readonly class CapabilityDescriptor
{
    public function __construct(
        public CapabilityType $type,
        public string $packageKey,
        public string $name,
        public string $registryKey,
    ) {
    }

    /**
     * Canonical string that identifies this capability, used both as the privilege
     * matcher and as the value carried by a {@see \Sitegeist\Pandora\Security\McpCapabilityPrivilegeSubject},
     * e.g. "Sitegeist.Pandora:tool:find-children".
     */
    public function getMatcher(): string
    {
        return $this->packageKey . ':' . $this->type->value . ':' . $this->name;
    }

    /**
     * Fully qualified privilege target identifier, owned by the providing package,
     * e.g. "Sitegeist.Pandora:Capability.Tool.find-children".
     */
    public function getPrivilegeTargetIdentifier(): string
    {
        return $this->packageKey . ':Capability.' . $this->type->getIdentifierSegment() . '.' . $this->name;
    }

    /**
     * Human-readable label shown for the privilege target.
     */
    public function getLabel(): string
    {
        return sprintf('MCP %s "%s" (%s)', $this->type->value, $this->name, $this->packageKey);
    }
}
