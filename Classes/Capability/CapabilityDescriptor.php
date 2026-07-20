<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Capability;

use Neos\Flow\Annotations as Flow;

/**
 * An immutable descriptor identifying a single MCP capability by its {@see CapabilityType} and MCP
 * name (e.g. "tool:find-children"). $registryKey is the key the capability is registered under: the
 * name for tools and prompts, the URI / URI template for resources and resource templates.
 */
#[Flow\Proxy(false)]
final readonly class CapabilityDescriptor
{
    public function __construct(
        public CapabilityType $type,
        public string $name,
        public string $registryKey,
    ) {
    }

    /**
     * Canonical string that identifies this capability, used both as the privilege
     * matcher and as the value carried by a {@see \Sitegeist\Pandora\Security\McpCapabilityPrivilegeSubject}.
     */
    public function getMatcher(): string
    {
        return $this->type->value . ':' . $this->name;
    }

    /**
     * Fully qualified privilege target identifier, as referenced from Policy.yaml,
     * e.g. "Sitegeist.Pandora:Capability.Tool.find-children".
     */
    public function getPrivilegeTargetIdentifier(): string
    {
        return 'Sitegeist.Pandora:Capability.' . $this->type->getIdentifierSegment() . '.' . $this->name;
    }

    /**
     * Human readable label shown for the privilege target.
     */
    public function getLabel(): string
    {
        return sprintf('MCP %s "%s"', $this->type->value, $this->name);
    }
}
