<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Security;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authorization\Privilege\PrivilegeSubjectInterface;
use Sitegeist\Pandora\Capability\CapabilityDescriptor;

/**
 * The subject an {@see McpCapabilityPrivilege} is asked about: a single MCP capability,
 * identified by its canonical matcher string (e.g. "tool:find-children").
 */
#[Flow\Proxy(false)]
final class McpCapabilityPrivilegeSubject implements PrivilegeSubjectInterface
{
    public function __construct(
        private readonly string $capabilityIdentifier,
    ) {
    }

    public static function createFromDescriptor(CapabilityDescriptor $descriptor): self
    {
        return new self($descriptor->getMatcher());
    }

    public function getCapabilityIdentifier(): string
    {
        return $this->capabilityIdentifier;
    }
}
